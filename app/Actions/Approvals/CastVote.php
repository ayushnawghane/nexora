<?php

namespace App\Actions\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\TransactionStatus;
use App\Enums\VoteDecision;
use App\Models\ApprovalRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastVote
{
    /**
     * Records one approver's decision and closes the request when the rule is met (any rejection
     * rejects; a head approver plus one more approval approves). The request row is locked, so
     * simultaneous votes are applied one after the other and a closed request takes no more votes.
     */
    public function handle(ApprovalRequest $request, User $voter, VoteDecision $decision, ?string $comment, string $via): ApprovalRequest
    {
        $closed = DB::transaction(function () use ($request, $voter, $decision, $comment, $via) {
            $locked = ApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['vote' => 'Voting on this request has closed.']);
            }
            if (! $voter->is_active || ! $voter->can('approvals.vote')) {
                throw ValidationException::withMessages(['vote' => 'You are not an approver.']);
            }
            if ($locked->requested_by === $voter->id) {
                throw ValidationException::withMessages(['vote' => 'You can\'t vote on a request you submitted.']);
            }
            if ($locked->votes()->where('user_id', $voter->id)->exists()) {
                throw ValidationException::withMessages(['vote' => 'You have already voted on this request.']);
            }
            if ($decision === VoteDecision::Reject && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Say why you are rejecting it.']);
            }

            $locked->votes()->create([
                'user_id' => $voter->id,
                'decision' => $decision,
                'is_head' => $voter->can('approvals.head'),
                'comment' => trim((string) $comment) ?: null,
                'via' => $via,
            ]);

            $outcome = $locked->outcome();
            if ($outcome === ApprovalStatus::Open) {
                return null;
            }

            $locked->update(['status' => $outcome, 'closed_at' => now()]);

            $transaction = Transaction::query()->whereKey($locked->transaction_id)->lockForUpdate()->firstOrFail();
            if ($outcome === ApprovalStatus::Approved) {
                $transaction->transitionTo(TransactionStatus::Approved);
                $transaction->approved_at = now();
            } else {
                $transaction->transitionTo(TransactionStatus::Rejected);
            }
            $transaction->save();

            return $locked;
        });

        if ($closed !== null) {
            $closed->requester->notify(new ApprovalDecided($closed));
        }

        return $request->refresh();
    }
}
