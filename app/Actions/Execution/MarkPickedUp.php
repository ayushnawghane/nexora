<?php

namespace App\Actions\Execution;

use App\Enums\ExecutionStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkPickedUp
{
    /**
     * Custody records that it picked up a deal's executed documents. Only once every document in
     * execution is verified; the verified ones not picked up yet are marked.
     */
    public function handle(Transaction $deal, User $actor): int
    {
        return DB::transaction(function () use ($deal, $actor) {
            /** @var Transaction $locked */
            $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();

            $executions = $locked->executions()->lockForUpdate()->get();
            if ($executions->contains(fn ($e) => $e->status !== ExecutionStatus::Verified)) {
                throw ValidationException::withMessages(['pickup' => 'Some documents are still being executed or checked.']);
            }
            $ready = $executions->whereNull('picked_up_at');
            if ($ready->isEmpty()) {
                throw ValidationException::withMessages(['pickup' => 'There\'s nothing waiting to be picked up.']);
            }

            $locked->executions()->whereKey($ready->modelKeys())->update(['picked_up_at' => now(), 'picked_up_by' => $actor->id]);

            return $ready->count();
        });
    }
}
