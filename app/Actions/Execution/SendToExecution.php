<?php

namespace App\Actions\Execution;

use App\Enums\ExecutionStatus;
use App\Models\DealDocument;
use App\Models\DealExecution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendToExecution
{
    /**
     * Sends deal documents for execution. Each needs its execution version uploaded, and goes in
     * once; it then waits to be scheduled.
     *
     * @param  list<int>  $documentIds
     * @return Collection<int, DealExecution>
     */
    public function handle(Transaction $deal, array $documentIds, User $actor): Collection
    {
        return DB::transaction(function () use ($deal, $documentIds, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isOpenDeal()) {
                throw ValidationException::withMessages(['document_ids' => 'The documents of a closed deal can\'t be changed.']);
            }

            $documents = $locked->dealDocuments()->whereKey($documentIds)->with(['currentFile', 'execution'])->lockForUpdate()->get();
            if ($documents->count() !== count(array_unique($documentIds))) {
                throw ValidationException::withMessages(['document_ids' => 'Some of the chosen documents aren\'t on this deal.']);
            }
            $problems = $documents->map(fn (DealDocument $d) => match (true) {
                $d->execution !== null => "{$d->name} is already in execution.",
                $d->currentFile === null => "{$d->name} has no execution version uploaded.",
                default => null,
            })->filter();
            if ($problems->isNotEmpty()) {
                throw ValidationException::withMessages(['document_ids' => $problems->implode(' ')]);
            }

            return $documents->map(fn (DealDocument $d) => $locked->executions()->create([
                'deal_document_id' => $d->id,
                'status' => ExecutionStatus::ToSchedule,
                'created_by' => $actor->id,
            ]))->values();
        });
    }
}
