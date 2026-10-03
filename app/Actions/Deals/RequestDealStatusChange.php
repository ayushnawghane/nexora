<?php

namespace App\Actions\Deals;

use App\Enums\DealStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\DealStatusRequested;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequestDealStatusChange
{
    public function __construct(private readonly ApplyDealStatus $apply) {}

    /**
     * Opens a request to move a deal to another status. Putting a deal on hold needs no approval and
     * applies at once; anything else waits for the teams DealStatus::approvalTeamsFor() names, who
     * are emailed after commit. The deal row is locked, so a deal never has two open requests.
     */
    public function handle(Transaction $transaction, User $actor, DealStatus $to, CarbonImmutable $effectiveOn, string $reason, ?UploadedFile $noc): DealStatusRequest
    {
        $nocPath = null;

        try {
            $request = DB::transaction(function () use ($transaction, $actor, $to, $effectiveOn, $reason, $noc, &$nocPath) {
                /** @var Transaction $locked */
                $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
                $from = $this->guard($locked, $to, $effectiveOn, $noc);
                $teams = $from->approvalTeamsFor($to);

                if ($noc) {
                    $nocPath = $noc->store("deals/{$locked->ulid}/noc", 'local');
                }

                $request = $locked->statusRequests()->create([
                    'from_status' => $from,
                    'to_status' => $to,
                    'effective_on' => $effectiveOn,
                    'reason' => trim($reason),
                    'noc_path' => $nocPath ?: null,
                    'noc_name' => $noc?->getClientOriginalName(),
                    'needs_management' => in_array(StatusApprovalTeam::Management, $teams, true),
                    'needs_accounts' => in_array(StatusApprovalTeam::Accounts, $teams, true),
                    'status' => $teams === [] ? StatusRequestState::Approved : StatusRequestState::Open,
                    'requested_by' => $actor->id,
                    'closed_at' => $teams === [] ? now() : null,
                ]);

                if ($teams === []) {
                    $this->apply->handle($locked, $request, $actor->id);
                }

                return $request;
            });
        } catch (Throwable $e) {
            if ($nocPath) {
                Storage::disk('local')->delete($nocPath);
            }
            throw $e;
        }

        if ($request->isOpen()) {
            $approvers = User::query()->active()
                ->permission(array_map(fn (StatusApprovalTeam $t) => $t->permission(), $request->teams()))
                ->whereKeyNot($actor->id)
                ->get();
            Notification::send($approvers, new DealStatusRequested($request));
        }

        $transaction->refresh();

        return $request;
    }

    private function guard(Transaction $deal, DealStatus $to, CarbonImmutable $effectiveOn, ?UploadedFile $noc): DealStatus
    {
        $from = $deal->deal_status;

        if ($from === null || $from->isFinal()) {
            throw ValidationException::withMessages(['to_status' => 'This deal\'s status can no longer be changed.']);
        }
        if ($deal->statusRequests()->where('status', StatusRequestState::Open)->exists()) {
            throw ValidationException::withMessages(['to_status' => 'A status change is already waiting for approval. Wait for it, or withdraw it first.']);
        }
        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['to_status' => "A {$from->label()} deal can't move to {$to->label()}."]);
        }
        if ($from === DealStatus::Hold && $to !== DealStatus::Cancelled) {
            $heldFrom = $deal->statusChanges()->where('to_status', DealStatus::Hold)->latest('id')->first()?->from_status;
            if ($heldFrom !== null && $heldFrom !== $to) {
                throw ValidationException::withMessages(['to_status' => "A deal on hold resumes at the status it was put on hold from ({$heldFrom->label()})."]);
            }
        }
        if ($from->needsNocToLeave() && $noc === null) {
            throw ValidationException::withMessages(['noc' => 'Upload the NOC to move a deal out of Live.']);
        }
        if ($effectiveOn->isAfter(today())) {
            throw ValidationException::withMessages(['effective_on' => 'The effective date can\'t be in the future.']);
        }
        if ($deal->deal_status_since && $effectiveOn->lt($deal->deal_status_since)) {
            throw ValidationException::withMessages(['effective_on' => "The effective date can't be before the deal became {$from->label()} ({$deal->deal_status_since->format('d M Y')})."]);
        }

        return $from;
    }
}
