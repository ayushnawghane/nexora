<?php

namespace App\Actions\Execution;

use App\Enums\ExecutionStatus;
use App\Models\DealExecution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckExecution
{
    /**
     * The checker verifies an executed copy (final) or sends it back with a reason. The checker can
     * never be the person who uploaded it. (Stack let the uploader verify their own upload.)
     */
    public function handle(DealExecution $execution, User $checker, ExecutionStatus $decision, ?string $comment): DealExecution
    {
        if (! in_array($decision, [ExecutionStatus::Verified, ExecutionStatus::Returned], true)) {
            throw ValidationException::withMessages(['decision' => 'Verify the executed copy or send it back.']);
        }

        return DB::transaction(function () use ($execution, $checker, $decision, $comment) {
            $deal = Transaction::query()->whereKey($execution->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealExecution::query()->whereKey($execution->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['decision' => 'The documents of a closed deal can\'t be changed.']);
            }
            if ($locked->status !== ExecutionStatus::Executed) {
                throw ValidationException::withMessages(['decision' => 'This execution isn\'t waiting for a check.']);
            }
            if ($locked->uploaded_by === $checker->id) {
                throw ValidationException::withMessages(['decision' => 'You uploaded the executed copy, so someone else has to check it.']);
            }
            if ($decision === ExecutionStatus::Returned && trim((string) $comment) === '') {
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
