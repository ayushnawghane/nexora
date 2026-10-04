<?php

namespace App\Actions\Security;

use App\Actions\Documents\StoresDocumentFiles;
use App\Enums\ConditionStatus;
use App\Enums\DiligenceKind;
use App\Models\DealDiligenceItem;
use App\Models\DocumentFile;
use App\Models\EmpanelledAgency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Due diligence items work like CP/CS items: add, upload files (which sends the item for checking),
 * and a different person verifies it or sends it back. Verified is final.
 */
class ManageDiligence
{
    use StoresDocumentFiles;

    /**
     * @param  array<string, mixed>  $data  validated DiligenceItemRequest data
     */
    public function add(Transaction $deal, DiligenceKind $kind, array $data, User $actor): DealDiligenceItem
    {
        return DB::transaction(function () use ($deal, $kind, $data, $actor) {
            $locked = $this->lockOpenDeal($deal, 'title');

            $securityId = $data['deal_security_id'] ?? null;
            if ($kind->needsSecurity() && ! $securityId) {
                throw ValidationException::withMessages(['deal_security_id' => 'Choose the security this is about.']);
            }
            if ($securityId && $locked->securities()->whereKey($securityId)->doesntExist()) {
                throw ValidationException::withMessages(['deal_security_id' => 'Choose one of this deal\'s securities.']);
            }
            if ($kind->needsAssetOwner() && empty($data['asset_owner'])) {
                throw ValidationException::withMessages(['asset_owner' => 'Say whose ROC search this is.']);
            }
            $agencyId = $data['empanelled_agency_id'] ?? null;
            if ($agencyId && EmpanelledAgency::query()->active()->whereKey($agencyId)->doesntExist()) {
                throw ValidationException::withMessages(['empanelled_agency_id' => 'This empanelled agency is inactive.']);
            }

            return $locked->diligenceItems()->create([
                'kind' => $kind,
                'title' => $data['title'],
                'deal_security_id' => $kind->needsSecurity() || $kind === DiligenceKind::Other ? $securityId : null,
                'asset_owner' => $kind->needsAssetOwner() ? $data['asset_owner'] : null,
                'empanelled_agency_id' => $agencyId,
                'reference' => $data['reference'] ?? null,
                'status' => ConditionStatus::Pending,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function upload(DealDiligenceItem $item, array $files, User $actor): DealDiligenceItem
    {
        if ($files === []) {
            throw ValidationException::withMessages(['files' => 'Choose at least one file.']);
        }

        return $this->withFiles(function () use ($item, $files, $actor) {
            [$deal, $locked] = $this->lockItem($item, 'files');
            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['files' => "This item is marked {$locked->status->label()}; no more files can be added."]);
            }
            if ($locked->currentFiles()->count() + count($files) > DocumentFile::MAX_PER_ITEM) {
                throw ValidationException::withMessages(['files' => 'An item can hold at most '.DocumentFile::MAX_PER_ITEM.' files. Remove some first.']);
            }

            foreach ($files as $file) {
                $this->attach($locked, $deal, 'diligence', $file, $actor);
            }
            $locked->update([
                'status' => ConditionStatus::Submitted,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'checker_id' => null,
                'checked_at' => null,
            ]);

            return $locked;
        });
    }

    public function check(DealDiligenceItem $item, User $checker, ConditionStatus $decision, ?string $comment): DealDiligenceItem
    {
        if (! in_array($decision, [ConditionStatus::Verified, ConditionStatus::Returned], true)) {
            throw ValidationException::withMessages(['decision' => 'Verify the item or send it back.']);
        }

        return DB::transaction(function () use ($item, $checker, $decision, $comment) {
            [, $locked] = $this->lockItem($item, 'decision');
            if ($locked->status !== ConditionStatus::Submitted) {
                throw ValidationException::withMessages(['decision' => 'This item isn\'t waiting for a check.']);
            }
            if ($locked->submitted_by === $checker->id) {
                throw ValidationException::withMessages(['decision' => 'You uploaded these files, so someone else has to check them.']);
            }
            if ($decision === ConditionStatus::Returned && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Say what needs fixing.']);
            }

            $locked->update([
                'status' => $decision,
                'checker_id' => $checker->id,
                'checker_comment' => trim((string) $comment) ?: null,
                'checked_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Removes an item added by mistake: only one that never had a file uploaded.
     */
    public function remove(DealDiligenceItem $item): void
    {
        DB::transaction(function () use ($item) {
            [, $locked] = $this->lockItem($item, 'item');
            if ($locked->status !== ConditionStatus::Pending || $locked->files()->exists()) {
                throw ValidationException::withMessages(['item' => 'Files have been uploaded to this item, so it can\'t be removed.']);
            }
            $locked->delete();
        });
    }

    private function lockOpenDeal(Transaction $deal, string $field): Transaction
    {
        /** @var Transaction $locked */
        $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
        if (! $locked->isOpenDeal()) {
            throw ValidationException::withMessages([$field => 'The due diligence of a closed deal can\'t be changed.']);
        }

        return $locked;
    }

    /**
     * @return array{0: Transaction, 1: DealDiligenceItem}
     */
    private function lockItem(DealDiligenceItem $item, string $field): array
    {
        $deal = $this->lockOpenDeal(Transaction::query()->findOrFail($item->transaction_id), $field);

        return [$deal, DealDiligenceItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail()];
    }
}
