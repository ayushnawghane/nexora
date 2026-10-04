<?php

namespace App\Actions\Documents;

use App\Enums\ConditionStatus;
use App\Models\DealCondition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckCondition
{
    /**
     * The checker verifies a submitted CP/CS item (final) or sends it back with a reason. The checker
     * can never be the person who uploaded its files.
     */
    public function handle(DealCondition $condition, User $checker, ConditionStatus $decision, ?string $comment): DealCondition
    {
        if (! in_array($decision, [ConditionStatus::Verified, ConditionStatus::Returned], true)) {
            throw ValidationException::withMessages(['decision' => 'Verify the item or send it back.']);
        }

        return DB::transaction(function () use ($condition, $checker, $decision, $comment) {
            $deal = Transaction::query()->whereKey($condition->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealCondition::query()->whereKey($condition->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['decision' => 'The documents of a closed deal can\'t be changed.']);
            }
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
}
