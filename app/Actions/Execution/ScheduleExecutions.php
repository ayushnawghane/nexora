<?php

namespace App\Actions\Execution;

use App\Enums\ExecutionStatus;
use App\Enums\SignatoryType;
use App\Models\DealExecution;
use App\Models\PoaHolder;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ExecutionScheduled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleExecutions
{
    /**
     * Sets where, when and by whom documents are executed, and emails the signatory the list (after
     * commit). The signatory is one of Beacon's active authorised signatories, or a POA holder whose
     * power of attorney covers the execution date. Rescheduling is allowed until the executed copy
     * is uploaded.
     *
     * @param  list<int>  $executionIds
     * @return Collection<int, DealExecution>
     */
    public function handle(Transaction $deal, array $executionIds, string $place, CarbonImmutable $at, SignatoryType $type, ?User $signatory, ?PoaHolder $poa, User $actor): Collection
    {
        $executions = DB::transaction(function () use ($deal, $executionIds, $place, $at, $type, $signatory, $poa) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['execution_ids' => 'The documents of a closed deal can\'t be changed.']);
            }

            $executions = $locked->executions()->whereKey($executionIds)->with('document')->lockForUpdate()->get();
            if ($executions->count() !== count(array_unique($executionIds))) {
                throw ValidationException::withMessages(['execution_ids' => 'Some of the chosen documents aren\'t in this deal\'s execution.']);
            }
            $done = $executions->reject(fn (DealExecution $e) => $e->status->canSchedule());
            if ($done->isNotEmpty()) {
                throw ValidationException::withMessages(['execution_ids' => 'Already executed: '.$done->map(fn (DealExecution $e) => $e->document->name)->implode('; ').'.']);
            }

            $signatoryUserId = null;
            $poaHolderId = null;
            if ($type === SignatoryType::Internal) {
                if (! $signatory || ! $signatory->is_active || ! $signatory->is_authorised_signatory) {
                    throw ValidationException::withMessages(['signatory_user_id' => 'Choose an active authorised signatory.']);
                }
                $signatoryUserId = $signatory->id;
            } else {
                if (! $poa || PoaHolder::query()->validOn($at)->whereKey($poa->id)->doesntExist()) {
                    throw ValidationException::withMessages(['poa_holder_id' => 'Choose a POA holder whose power of attorney covers the execution date.']);
                }
                $poaHolderId = $poa->id;
            }

            foreach ($executions as $execution) {
                $execution->update([
                    'status' => ExecutionStatus::Scheduled,
                    'place' => $place,
                    'scheduled_at' => $at,
                    'signatory_type' => $type,
                    'signatory_user_id' => $signatoryUserId,
                    'poa_holder_id' => $poaHolderId,
                ]);
            }

            return $executions;
        });

        $recipient = $type === SignatoryType::Internal ? $signatory : $poa;
        if ($recipient?->email) {
            $recipient->notify(new ExecutionScheduled($deal, $executions->modelKeys(), $actor));
        }

        return $executions;
    }
}
