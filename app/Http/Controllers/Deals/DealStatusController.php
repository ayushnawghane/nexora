<?php

namespace App\Http\Controllers\Deals;

use App\Actions\Deals\RequestDealStatusChange;
use App\Actions\Deals\VoteOnDealStatus;
use App\Actions\Deals\WithdrawDealStatusRequest;
use App\Enums\DealStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\VoteDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deals\DealStatusChangeRequest;
use App\Http\Requests\Deals\DealStatusVoteRequest;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealStatusController extends Controller
{
    public function store(DealStatusChangeRequest $request, Transaction $transaction, RequestDealStatusChange $change): RedirectResponse
    {
        $statusRequest = $change->handle(
            $transaction,
            $request->user(),
            DealStatus::from($request->validated('to_status')),
            CarbonImmutable::parse($request->validated('effective_on')),
            $request->validated('reason'),
            $request->file('noc'),
        );

        return redirect()->route('deals.show', ['transaction' => $transaction, 'tab' => 'status'])->with('success', $statusRequest->isOpen()
            ? 'Status change requested. '.implode(' and ', array_map(fn (StatusApprovalTeam $t) => $t->label(), $statusRequest->teams())).' have been emailed.'
            : "The deal is now {$statusRequest->to_status->label()}.");
    }

    public function vote(DealStatusVoteRequest $request, DealStatusRequest $statusRequest, VoteOnDealStatus $vote): RedirectResponse
    {
        $this->authorize('viewDeal', $statusRequest->transaction);

        $result = $vote->handle(
            $statusRequest,
            $request->user(),
            StatusApprovalTeam::from($request->validated('team')),
            VoteDecision::from($request->validated('decision')),
            $request->validated('comment'),
        );

        return back()->with('success', match ($result->status) {
            StatusRequestState::Approved => "Vote recorded. The deal is now {$result->to_status->label()}.",
            StatusRequestState::Rejected => 'Vote recorded. The status change has been rejected.',
            default => 'Vote recorded. Waiting for the other team.',
        });
    }

    public function withdraw(Request $request, DealStatusRequest $statusRequest, WithdrawDealStatusRequest $withdraw): RedirectResponse
    {
        $this->authorize('viewDeal', $statusRequest->transaction);

        $withdraw->handle($statusRequest, $request->user());

        return back()->with('success', 'Request withdrawn.');
    }

    /** The NOC uploaded with a request, for anyone who can see the deal. */
    public function noc(DealStatusRequest $statusRequest): StreamedResponse
    {
        $this->authorize('viewDeal', $statusRequest->transaction);
        abort_if($statusRequest->noc_path === null || ! Storage::disk('local')->exists($statusRequest->noc_path), 404);

        return Storage::disk('local')->download($statusRequest->noc_path, $statusRequest->noc_name ?? 'noc');
    }
}
