<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class VerifySchedule
{
    /**
     * Records that a person checked the generated schedule. Any later change to the issue or fees
     * clears this (RegenerateSchedule), so a submitted transaction always has a checked schedule.
     */
    public function handle(Transaction $transaction, User $actor): void
    {
        if ($transaction->schedulePeriods()->doesntExist()) {
            throw ValidationException::withMessages(['schedule' => 'There is no schedule to verify yet. Save the issue details and fees first.']);
        }

        $transaction->forceFill([
            'schedule_verified_at' => now(),
            'schedule_verified_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();
    }
}
