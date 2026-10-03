<?php

namespace App\Actions\Deals;

use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\VoteDecision;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\DealStatusDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoteOnDealStatus
{
    public function __construct(private readonly ApplyDealStatus $apply) {}

    /**
     * Records one team's decision on a status request. Any rejection rejects it; once every required
     * team has approved, the deal moves to the new status. The request row is locked, so two people
     * of the same team voting at once can't both count.
     */
    public function handle(DealStatusRequest $request, User $voter, StatusApprovalTeam $team, VoteDecision $decision, ?string $comment): DealStatusRequest
    {
        $closed = DB::transaction(function () use ($request, $voter, $team, $decision, $comment) {
            $locked = DealStatusRequest::query()->whereKey($request->id)->with('votes')->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['vote' => 'This request has already been decided or withdrawn.']);
            }
            if (! $voter->is_active || ! $voter->can($team->permission())) {
                throw ValidationException::withMessages(['vote' => "You can't approve for {$team->label()}."]);
            }
            if ($locked->requested_by === $voter->id) {
                throw ValidationException::withMessages(['vote' => 'You can\'t vote on a request you raised.']);
            }
            if (! in_array($team, $locked->pendingTeams(), true)) {
                throw ValidationException::withMessages(['vote' => "{$team->label()} has already voted on this request."]);
            }
            if ($locked->votes->contains('user_id', $voter->id)) {
                throw ValidationException::withMessages(['vote' => 'You have already voted for the other team. Someone else must vote for this one.']);
            }
            if ($decision === VoteDecision::Reject && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Say why you are rejecting it.']);
            }

            $locked->votes()->create([
                'user_id' => $voter->id,
                'team' => $team,
                'decision' => $decision,
                'comment' => trim((string) $comment) ?: null,
            ]);

            $outcome = $locked->outcome();
            if ($outcome === StatusRequestState::Open) {
                return null;
            }

            $locked->update(['status' => $outcome, 'closed_at' => now()]);

            if ($outcome === StatusRequestState::Approved) {
                /** @var Transaction $deal */
                $deal = Transaction::query()->whereKey($locked->transaction_id)->lockForUpdate()->firstOrFail();
                $this->apply->handle($deal, $locked, $voter->id);
            }

            return $locked;
        });

        if ($closed !== null) {
            $closed->requester->notify(new DealStatusDecided($closed));
        }

        return $request->refresh();
    }
}
