<?php

namespace App\Http\Controllers\Approvals;

use App\Actions\Approvals\CastVote;
use App\Actions\Approvals\ReviseRejected;
use App\Actions\Approvals\SubmitForApproval;
use App\Enums\ApprovalStatus;
use App\Enums\VoteDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\VoteRequest;
use App\Models\ApprovalRequest;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    /** Open requests still waiting for the signed-in approver's vote. */
    public function index(Request $request): Response
    {
        $this->authorize('approvals.vote');
        $user = $request->user();

        $requests = ApprovalRequest::query()
            ->where('status', ApprovalStatus::Open)
            ->where('requested_by', '!=', $user->id)
            ->whereDoesntHave('votes', fn (Builder $q) => $q->where('user_id', $user->id))
            ->with(['transaction.company:id,name', 'transaction.issueDetail:id,transaction_id,total_issue_size', 'requester:id,name'])
            ->withCount(['votes as approvals_count' => fn (Builder $q) => $q->where('decision', VoteDecision::Approve)])
            ->oldest()
            ->get()
            ->map(fn (ApprovalRequest $r) => [
                'id' => $r->ulid,
                'transaction_id' => $r->transaction->ulid,
                'company' => $r->transaction->company->name,
                'issue_size' => $r->transaction->issueDetail?->total_issue_size,
                'requested_by' => $r->requester->name,
                'requested_at' => $r->created_at?->toIso8601String(),
                'approvals_count' => $r->approvals_count,
            ]);

        return Inertia::render('Approvals/Index', ['requests' => $requests]);
    }

    public function submit(Request $request, Transaction $transaction, SubmitForApproval $submit): RedirectResponse
    {
        $this->authorize('submit', $transaction);

        $submit->handle($transaction, $request->user());

        return redirect()->route('transactions.show', $transaction)->with('success', 'Sent for approval. Approvers have been emailed.');
    }

    public function vote(VoteRequest $request, ApprovalRequest $approvalRequest, CastVote $castVote): RedirectResponse
    {
        $result = $castVote->handle(
            $approvalRequest,
            $request->user(),
            VoteDecision::from($request->validated('decision')),
            $request->validated('comment'),
            'app',
        );

        return back()->with('success', match ($result->status) {
            ApprovalStatus::Approved => 'Vote recorded. The transaction is now approved.',
            ApprovalStatus::Rejected => 'Vote recorded. The transaction has been rejected and sent back.',
            ApprovalStatus::Open => 'Vote recorded.',
        });
    }

    public function revise(Request $request, Transaction $transaction, ReviseRejected $revise): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $revise->handle($transaction, $request->user());

        return redirect()->route('transactions.edit', $transaction)->with('success', 'Back in draft. Make the changes and send it again.');
    }
}
