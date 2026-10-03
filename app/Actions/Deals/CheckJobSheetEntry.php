<?php

namespace App\Actions\Deals;

use App\Enums\JobSheetStatus;
use App\Models\DealJobSheetEntry;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckJobSheetEntry
{
    /**
     * The checker verifies a submitted entry or sends it back with a reason. The checker can never
     * be the person who made the entry.
     */
    public function handle(DealJobSheetEntry $entry, User $checker, JobSheetStatus $decision, ?string $comment): DealJobSheetEntry
    {
        if ($decision === JobSheetStatus::Submitted) {
            throw ValidationException::withMessages(['decision' => 'Verify the entry or send it back.']);
        }

        return DB::transaction(function () use ($entry, $checker, $decision, $comment) {
            $locked = DealJobSheetEntry::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $deal = Transaction::query()->findOrFail($locked->transaction_id);

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['decision' => 'The job sheet of a closed deal can\'t be changed.']);
            }
            if ($locked->status !== JobSheetStatus::Submitted) {
                throw ValidationException::withMessages(['decision' => 'This entry isn\'t waiting for a check.']);
            }
            if ($locked->maker_id === $checker->id) {
                throw ValidationException::withMessages(['decision' => 'You made this entry, so someone else has to check it.']);
            }
            if ($decision === JobSheetStatus::Returned && trim((string) $comment) === '') {
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
