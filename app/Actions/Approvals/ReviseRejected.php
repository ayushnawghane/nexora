<?php

namespace App\Actions\Approvals;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviseRejected
{
    /** Sends a rejected transaction back to draft so it can be corrected and submitted again. */
    public function handle(Transaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($transaction, $actor) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== TransactionStatus::Rejected) {
                throw ValidationException::withMessages(['transaction' => 'Only a rejected transaction can be revised.']);
            }
            $locked->transitionTo(TransactionStatus::Draft);
            $locked->updated_by = $actor->id;
            $locked->save();
        });
    }
}
