<?php

namespace App\Actions\Deals;

use App\Enums\JobSheetStatus;
use App\Models\DealJobSheetEntry;
use App\Models\JobSheetActivity;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitJobSheetEntry
{
    /**
     * The maker records a job sheet activity as done and sends it for checking. An entry that was
     * sent back can be submitted again; one waiting for a check or already verified can't.
     */
    public function handle(Transaction $deal, JobSheetActivity $activity, User $maker, CarbonImmutable $receivedOn, ?string $comment): DealJobSheetEntry
    {
        return DB::transaction(function () use ($deal, $activity, $maker, $receivedOn, $comment) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->with('issueDetail')->lockForUpdate()->firstOrFail();

            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['activity' => 'The job sheet of a closed deal can\'t be changed.']);
            }
            if (JobSheetActivity::query()->appliesTo($locked->issueDetail?->listing)->whereKey($activity->id)->doesntExist()) {
                throw ValidationException::withMessages(['activity' => 'This activity isn\'t on this deal\'s job sheet.']);
            }
            if ($receivedOn->isAfter(today())) {
                throw ValidationException::withMessages(['received_on' => 'The received date can\'t be in the future.']);
            }

            $entry = $locked->jobSheetEntries()->where('job_sheet_activity_id', $activity->id)->first();
            if ($entry?->status === JobSheetStatus::Submitted) {
                throw ValidationException::withMessages(['activity' => 'This activity is already waiting for a check.']);
            }
            if ($entry?->status === JobSheetStatus::Verified) {
                throw ValidationException::withMessages(['activity' => 'This activity has already been verified.']);
            }

            $values = [
                'status' => JobSheetStatus::Submitted,
                'received_on' => $receivedOn,
                'maker_id' => $maker->id,
                'maker_comment' => trim((string) $comment) ?: null,
                'made_at' => now(),
                'checker_id' => null,
                'checker_comment' => null,
                'checked_at' => null,
            ];

            if ($entry) {
                $entry->update($values);

                return $entry;
            }

            return $locked->jobSheetEntries()->create(['job_sheet_activity_id' => $activity->id, ...$values]);
        });
    }
}
