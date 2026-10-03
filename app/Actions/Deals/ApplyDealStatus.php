<?php

namespace App\Actions\Deals;

use App\Enums\TransactionStatus;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use LogicException;

/**
 * Moves a locked deal to the status an approved request asks for and records the change. A final
 * status (redeemed, cancelled, closed …) closes the transaction too. Call inside a DB transaction.
 */
class ApplyDealStatus
{
    public function handle(Transaction $locked, DealStatusRequest $request, int $actorId): void
    {
        if ($locked->deal_status !== $request->from_status || ! $request->from_status->canTransitionTo($request->to_status)) {
            throw new LogicException("Deal {$locked->id} is no longer {$request->from_status->label()}.");
        }

        $locked->deal_status = $request->to_status;
        $locked->deal_status_since = $request->effective_on;
        $locked->updated_by = $actorId;
        if ($request->to_status->isFinal()) {
            $locked->transitionTo(TransactionStatus::Closed);
            $locked->closed_at = now();
        }
        $locked->save();

        $locked->statusChanges()->create([
            'from_status' => $request->from_status,
            'to_status' => $request->to_status,
            'effective_on' => $request->effective_on,
            'deal_status_request_id' => $request->id,
            'changed_by' => $actorId,
        ]);
    }
}
