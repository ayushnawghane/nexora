<?php

namespace App\Legacy\Importers;

use App\Enums\DealDocumentKind;
use App\Enums\ExecutionStatus;
use App\Enums\SignatoryType;
use App\Legacy\Importer;
use App\Legacy\Models\LegacyRecord;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\DocumentFile;
use App\Models\LegalDocumentType;
use App\Models\PoaHolder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * POA holders and the execution of the imported DT deals' documents, with the executed copies.
 * Needs the documents area first.
 *
 * - Stack can hold several execution rows for one document of a deal; Nexora keeps one per
 *   document: the verified one if any, else the one with an executed copy, else the latest. The
 *   others are reported.
 * - Status: verified → verified; executed copy uploaded → waiting for a check; date set →
 *   scheduled; otherwise → to schedule.
 * - Stack let the uploader verify their own copy; those verifications are kept as they happened.
 * - Stack's "External" signatory is a POA master id, a user id (POA holders who were also Stack
 *   users, e.g. user 67 = POA #4) or the text "Client". User ids and names are matched to the POA
 *   holder of the same name; one that isn't in the POA master is added, inactive.
 * - Executed copies arrive as records and are copied from LEGACY_UPLOADS_PATH when it's set.
 */
class ExecutionImporter extends Importer
{
    private const PRODUCT = 4;

    private int $importUser;

    /** @var array<string, int> lower-case POA holder name => id */
    private array $poaByName = [];

    /** @var array<int, true> Stack poa_master ids */
    private array $poaIds = [];

    public function area(): string
    {
        return 'execution';
    }

    public function description(): string
    {
        return 'POA holders; DT deals\' documents in execution with their executed copies';
    }

    protected function import(): void
    {
        $user = $this->importUser();
        if ($user === null) {
            return;
        }
        $this->importUser = $user;

        $this->poaHolders();

        $dealIds = LegacyRecord::from('transaction')->where('product_id', self::PRODUCT)->where('is_deleted', 0)->pluck('id')->all();
        $deals = Transaction::query()->whereIn('legacy_id', $dealIds)->get(['id', 'ulid', 'legacy_id', 'created_at'])->keyBy('legacy_id');
        $this->executions($deals);
    }

    private function poaHolders(): void
    {
        $byName = [];
        foreach (LegacyRecord::from('poa_master')->orderBy('id')->get() as $row) {
            $this->report->read('POA holders');
            $name = $row->text('poa_name');
            if ($name === null) {
                $this->report->reject('POA holders', $row->id, 'No name.');

                continue;
            }
            $key = Str::lower($name);
            if (isset($byName[$key])) {
                $this->alias(PoaHolder::class, (int) $row->id, $byName[$key], "Same name as another POA holder: {$name}", 'POA holders');

                continue;
            }
            $email = Str::lower((string) $row->text('email'));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->report->warn('POA holders', $row->id, "{$name}: \"{$email}\" isn't an email address; left empty.");
                $email = '';
            }
            $mobile = preg_replace('/[^0-9+]/', '', (string) $row->text('mobile')) ?: null;

            $this->claim(PoaHolder::class, (int) $row->id, 'name', $name);
            $holder = $this->upsert(PoaHolder::class, (int) $row->id, [
                'name' => $name,
                'email' => $email ?: null,
                'mobile' => $mobile && preg_match('/^\+?[0-9]{10,15}$/', $mobile) ? $mobile : null,
                'valid_from' => $row->date('valid_from'),
                'valid_till' => $row->date('valid_till'),
                'is_active' => $row->isLegacyActive(),
            ], 'POA holders');
            $byName[$key] = (int) $holder->id;
        }
        $this->poaByName = $byName;
        $this->poaIds = array_fill_keys(LegacyRecord::from('poa_master')->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
    }

    /**
     * The POA holder an "External" signatory value points at (see the class notes).
     */
    private function externalSignatory(?string $value, int $rowId): ?int
    {
        if ($value === null) {
            return null;
        }
        if (ctype_digit($value) && isset($this->poaIds[(int) $value])) {
            return $this->idFor(PoaHolder::class, (int) $value);
        }

        $name = ctype_digit($value)
            ? LegacyRecord::from('users')->whereKey((int) $value)->value('name')
            : $value;
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));
        if ($name === '') {
            return null;
        }

        $key = Str::lower($name);
        if (! isset($this->poaByName[$key])) {
            $holder = PoaHolder::withTrashed()->firstOrCreate(['name' => $name], ['is_active' => false]);
            $this->poaByName[$key] = (int) $holder->id;
            $this->report->warn('POA holders', $rowId, "{$name}: a signatory in Stack's executions but not in its POA master; added as an inactive POA holder.");
        }

        return $this->poaByName[$key];
    }

    /**
     * @param  Collection<int|string, Transaction>  $deals  by legacy id
     */
    private function executions(Collection $deals): void
    {
        $rows = LegacyRecord::from('execution_details')->whereIn('con_id', $deals->keys()->all())->orderBy('id')->get();
        $uploads = [];
        foreach ($rows->pluck('upload_id')->filter()->unique()->chunk(5000) as $chunk) {
            foreach (LegacyRecord::from('upload_file')->whereIn('id', $chunk->all())->get(['id', 'name', 'path', 'created_by', 'created_at']) as $upload) {
                $uploads[(int) $upload->id] = $upload;
            }
        }
        $perDealDocuments = DealDocument::withTrashed()->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();

        foreach ($rows->groupBy(fn (LegacyRecord $r) => $r->ref('con_id').'|'.$r->ref('doc_id')) as $group) {
            $live = $group->filter(fn (LegacyRecord $r) => $r->isLegacyActive());
            foreach ($group->diff($live) as $row) {
                $this->report->read('executions');
                $this->report->reject('executions', $row->id, 'Removed in Stack.');
            }
            if ($live->isEmpty()) {
                continue;
            }

            // One per document: verified first, then one with an executed copy, then the latest.
            $kept = $live->sortByDesc(fn (LegacyRecord $r) => [(int) $r->getAttribute('is_verified'), $r->ref('upload_id') ? 1 : 0, (int) $r->id])->first();
            foreach ($live as $row) {
                $this->report->read('executions');
                if ($row !== $kept) {
                    $this->report->reject('executions', $row->id, "Another execution row of the same document was kept (#{$kept->id}).");
                }
            }

            /** @var Transaction $deal */
            $deal = $deals->get($kept->ref('con_id'));
            $document = $this->dealDocument($deal, (int) $kept->ref('doc_id'), $perDealDocuments, $kept);
            if ($document === null) {
                continue;
            }
            $this->execution($deal, $document, $kept, $uploads[$kept->ref('upload_id') ?? 0] ?? null);
        }
    }

    /**
     * The deal document an execution row is about: a per-deal document by its Stack id, or the
     * deal's standard document of a master type (added without a file if the deal doesn't have it).
     *
     * @param  array<int, int>  $perDealDocuments  Stack legal document id => deal document id
     */
    private function dealDocument(Transaction $deal, int $stackDocId, array $perDealDocuments, LegacyRecord $row): ?DealDocument
    {
        if (isset($perDealDocuments[$stackDocId])) {
            $document = DealDocument::withTrashed()->find($perDealDocuments[$stackDocId]);
            if ($document && $document->transaction_id === $deal->id) {
                return $document;
            }
        }

        $type = LegalDocumentType::withTrashed()->where('legacy_id', $stackDocId)->first();
        if ($type === null) {
            $this->report->reject('executions', $row->id, "Its document (Stack #{$stackDocId}) isn't in the legal document master.");

            return null;
        }

        $key = ['transaction_id' => $deal->id, 'legal_document_type_id' => $type->id, 'kind' => DealDocumentKind::Standard, 'sequence' => 0];
        $document = DealDocument::withTrashed()->where($key)->first();
        if ($document === null) {
            $document = (new DealDocument)->forceFill([...$key, 'name' => $type->name, 'created_by' => $this->importUser]);
            $document->save();
            $this->report->warn('executions', $row->id, "{$type->name} wasn't on the deal's documentation in Stack; added there.");
        }

        return $document;
    }

    private function execution(Transaction $deal, DealDocument $document, LegacyRecord $row, ?LegacyRecord $upload): void
    {
        $verified = (int) $row->getAttribute('is_verified') === 1;
        $hasCopy = $row->ref('upload_id') !== null;
        $scheduledOn = $row->date('exe_date');
        $status = match (true) {
            $verified => ExecutionStatus::Verified,
            $hasCopy => ExecutionStatus::Executed,
            $scheduledOn !== null => ExecutionStatus::Scheduled,
            default => ExecutionStatus::ToSchedule,
        };

        $signType = Str::lower((string) $row->text('sign_type'));
        $signatoryType = match ($signType) {
            'internal' => SignatoryType::Internal,
            'external' => SignatoryType::External,
            default => null,
        };
        $signatoryRef = is_numeric($row->text('sign_name')) ? (int) $row->text('sign_name') : null;
        $poaHolderId = $signatoryType === SignatoryType::External ? $this->externalSignatory($row->text('sign_name'), (int) $row->id) : null;
        $uploaderRef = is_numeric($row->text('uploaded_by')) ? (int) $row->text('uploaded_by') : null;
        $executedOn = $row->date('execution_date') ?? ($hasCopy || $verified ? $scheduledOn : null);
        $fallback = $deal->created_at?->toDateTimeString();

        /** @var DealExecution $execution */
        $execution = DealExecution::query()->where('deal_document_id', $document->id)->first()
            ?? DealExecution::query()->where('legacy_id', $row->id)->first()
            ?? new DealExecution;
        $execution->forceFill([
            'legacy_id' => (int) $row->id,
            'transaction_id' => $deal->id,
            'deal_document_id' => $document->id,
            'status' => $status,
            'place' => $row->text('exe_place') ? mb_substr((string) $row->text('exe_place'), 0, 100) : null,
            'scheduled_at' => $scheduledOn ? trim($scheduledOn.' '.($row->text('exe_time') ?? '00:00:00')) : null,
            'signatory_type' => $signatoryType,
            'signatory_user_id' => $signatoryType === SignatoryType::Internal ? $this->idFor(User::class, $signatoryRef) : null,
            'poa_holder_id' => $poaHolderId,
            'document_date' => $row->date('document_date'),
            'executed_on' => $executedOn,
            'comments' => $row->text('comments'),
            'uploaded_by' => $hasCopy ? ($this->idFor(User::class, $uploaderRef) ?? $this->importUser) : null,
            'uploaded_at' => $hasCopy ? ($row->date('uploaded_date') ?? $row->date('updated_at') ?? $fallback) : null,
            'checker_id' => $verified ? ($this->idFor(User::class, $row->ref('verified_by')) ?? $this->importUser) : null,
            'checked_at' => $verified ? ($row->date('verified_datetime') ?? $row->date('updated_at') ?? $fallback) : null,
            'created_by' => $this->idFor(User::class, $row->ref('created_by')) ?? $this->importUser,
            'created_at' => $row->date('created_date') ?? $row->date('created_at') ?? $fallback,
            'updated_at' => $row->date('updated_at') ?? $row->date('created_date') ?? $fallback,
        ]);
        if ($signatoryType !== null && $execution->signatory_user_id === null && $execution->poa_holder_id === null) {
            $this->report->warn('executions', $row->id, "{$document->name}: signatory \"{$row->text('sign_name')}\" not found.");
        }
        $outcome = ! $execution->exists ? 'created' : ($execution->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $execution->save();
        }
        $this->report->saved('executions', $outcome);

        if ($hasCopy) {
            $this->executedCopy($execution, $deal, $upload, $row);
        }
    }

    private function executedCopy(DealExecution $execution, Transaction $deal, ?LegacyRecord $upload, LegacyRecord $row): void
    {
        $this->report->read('executed copies');
        if ($upload === null) {
            $this->report->reject('executed copies', $row->id, "Upload #{$row->ref('upload_id')} isn't in Stack's file list.");

            return;
        }

        $key = ['attachable_type' => DealExecution::class, 'attachable_id' => $execution->id, 'legacy_id' => (int) $upload->id];
        $file = DocumentFile::query()->where($key)->first() ?? (new DocumentFile)->forceFill($key);
        $file->forceFill([
            'original_name' => mb_substr($upload->text('name') ?? basename((string) $upload->text('path')) ?: 'executed.pdf', 0, 255),
            'uploaded_by' => $execution->uploaded_by ?? $this->importUser,
            'created_at' => $file->created_at ?? $execution->uploaded_at ?? $deal->created_at,
        ]);
        $outcome = ! $file->exists ? 'created' : ($file->isDirty() ? 'updated' : 'unchanged');
        if ($outcome !== 'unchanged') {
            $file->save();
        }
        $this->report->saved('executed copies', $outcome);

        $root = config('legacy.uploads_path');
        if ($file->isAvailable() || ! $root) {
            return;
        }
        $relative = ltrim(str_replace('\\', '/', (string) $upload->text('path')), '/');
        $source = rtrim((string) $root, '/\\').DIRECTORY_SEPARATOR.$relative;
        if ($relative === '' || str_contains($relative, '..') || ! is_file($source)) {
            $this->report->warn('executed copies (copy)', $upload->id, "Not found in the uploads copy: {$relative}");

            return;
        }
        if ($this->report->dryRun) {
            $this->report->saved('executed copies (copy)', 'created');

            return;
        }
        $extension = Str::lower(pathinfo($relative, PATHINFO_EXTENSION));
        $path = "deals/{$deal->ulid}/executed/stack-{$upload->id}".($extension !== '' ? ".{$extension}" : '');
        Storage::disk('local')->put($path, (string) file_get_contents($source));
        $file->forceFill(['path' => $path, 'size' => filesize($source) ?: null, 'mime' => mime_content_type($source) ?: null])->save();
        $this->report->saved('executed copies (copy)', 'created');
    }
}
