<?php

namespace App\Actions\Execution;

use App\Models\DealExecution;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawFromExecution
{
    /**
     * Takes a document back out of execution (sent by mistake, or its execution version must change
     * first). Only before an executed copy was ever uploaded; the activity log keeps the record.
     */
    public function handle(DealExecution $execution): void
    {
        DB::transaction(function () use ($execution) {
            $deal = Transaction::query()->whereKey($execution->transaction_id)->lockForUpdate()->firstOrFail();
            $locked = DealExecution::query()->whereKey($execution->id)->lockForUpdate()->firstOrFail();

            if (! $deal->isOpenDeal()) {
                throw ValidationException::withMessages(['execution' => 'The documents of a closed deal can\'t be changed.']);
            }
            if (! $locked->status->canSchedule() || $locked->files()->exists()) {
                throw ValidationException::withMessages(['execution' => 'An executed copy has been uploaded, so the document stays in execution.']);
            }

            $locked->delete();
        });
    }
}
