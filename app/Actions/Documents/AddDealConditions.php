<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStage;
use App\Enums\ConditionStatus;
use App\Models\ConditionDocument;
use App\Models\DealCondition;
use App\Models\IssuingAuthority;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddDealConditions
{
    /**
     * Adds CP or CS items to a deal from the master list. Each master document can be on a deal once.
     *
     * @param  list<int>  $documentIds
     * @return Collection<int, DealCondition>
     */
    public function fromMaster(Transaction $deal, ConditionStage $stage, array $documentIds, ?CarbonImmutable $dueOn, User $actor): Collection
    {
        return DB::transaction(function () use ($deal, $stage, $documentIds, $dueOn, $actor) {
            $locked = $this->lockOpenDeal($deal);

            $documents = ConditionDocument::query()->active()->where('stage', $stage)->whereKey($documentIds)->get();
            if ($documents->count() !== count(array_unique($documentIds))) {
                throw ValidationException::withMessages(['document_ids' => "Some of the chosen documents aren't active {$stage->short()} documents."]);
            }
            $already = $locked->conditions()->whereIn('condition_document_id', $documentIds)->pluck('name');
            if ($already->isNotEmpty()) {
                throw ValidationException::withMessages(['document_ids' => 'Already on this deal: '.$already->implode('; ').'.']);
            }

            return $documents->map(fn (ConditionDocument $document) => $locked->conditions()->create([
                'stage' => $stage,
                'condition_document_id' => $document->id,
                'name' => $document->name,
                'issuing_authority_id' => $document->issuing_authority_id,
                'due_on' => $dueOn,
                'status' => ConditionStatus::Pending,
                'created_by' => $actor->id,
            ]))->values();
        });
    }

    /**
     * Adds a CP or CS item written for this deal only (not in the master list).
     */
    public function custom(Transaction $deal, ConditionStage $stage, string $name, ?IssuingAuthority $authority, ?CarbonImmutable $dueOn, User $actor): DealCondition
    {
        return DB::transaction(function () use ($deal, $stage, $name, $authority, $dueOn, $actor) {
            $locked = $this->lockOpenDeal($deal);

            if ($authority && ! $authority->is_active) {
                throw ValidationException::withMessages(['issuing_authority_id' => 'This issuing authority is inactive.']);
            }
            if ($locked->conditions()->where('stage', $stage)->where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => "This deal already has a {$stage->short()} item with this name."]);
            }

            return $locked->conditions()->create([
                'stage' => $stage,
                'name' => $name,
                'issuing_authority_id' => $authority?->id,
                'due_on' => $dueOn,
                'status' => ConditionStatus::Pending,
                'created_by' => $actor->id,
            ]);
        });
    }

    private function lockOpenDeal(Transaction $deal): Transaction
    {
        /** @var Transaction $locked */
        $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
        if (! $locked->isOpenDeal()) {
            throw ValidationException::withMessages(['document_ids' => 'The documents of a closed deal can\'t be changed.']);
        }

        return $locked;
    }
}
