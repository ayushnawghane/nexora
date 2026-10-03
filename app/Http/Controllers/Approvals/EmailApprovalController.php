<?php

namespace App\Http\Controllers\Approvals;

use App\Actions\Approvals\CastVote;
use App\Enums\VoteDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\VoteRequest;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Voting from the email link without signing in. Both the page and the vote need a valid,
 * unexpired signature bound to this approver and request; GET only shows, POST records.
 */
class EmailApprovalController extends Controller
{
    /** How long the vote form stays usable once the page is opened from the email. */
    private const FORM_MINUTES = 30;

    public function show(ApprovalRequest $approvalRequest, User $user): Response
    {
        $approvalRequest->load(['transaction.company', 'transaction.issueDetail', 'transaction.feeLines', 'requester', 'votes.user:id,name']);
        $transaction = $approvalRequest->transaction;
        $vote = $approvalRequest->votes->firstWhere('user_id', $user->id);

        return Inertia::render('Approvals/EmailVote', [
            'approver' => $user->name,
            'request' => [
                'status' => $approvalRequest->status->value,
                'status_label' => $approvalRequest->status->label(),
                'requested_by' => $approvalRequest->requester->name,
                'requested_at' => $approvalRequest->created_at?->toIso8601String(),
            ],
            'transaction' => [
                'company' => $transaction->company->name,
                'cin' => $transaction->company->cin,
                'issue_size' => Money::format((string) $transaction->issueDetail?->total_issue_size),
                'tenure_months' => $transaction->issueDetail?->tenure_months,
                'brief' => $transaction->brief,
                'fees' => $transaction->feeLines->map(fn ($f) => [
                    'label' => $f->kind->label(),
                    'amount' => Money::format($f->annual_amount),
                    'frequency' => $f->frequency->label(),
                ]),
            ],
            'myVote' => $vote ? ['decision' => $vote->decision->label(), 'comment' => $vote->comment] : null,
            'canVote' => $approvalRequest->isOpen() && $vote === null && $user->is_active
                && $user->can('approvals.vote') && $approvalRequest->requested_by !== $user->id,
            'voteUrl' => URL::temporarySignedRoute('approvals.email.vote', now()->addMinutes(self::FORM_MINUTES), [
                'approvalRequest' => $approvalRequest->ulid,
                'user' => $user->ulid,
            ]),
        ]);
    }

    public function vote(VoteRequest $request, ApprovalRequest $approvalRequest, User $user, CastVote $castVote): RedirectResponse
    {
        $castVote->handle(
            $approvalRequest,
            $user,
            VoteDecision::from($request->validated('decision')),
            $request->validated('comment'),
            'email',
        );

        return redirect(URL::temporarySignedRoute('approvals.email', now()->addMinutes(self::FORM_MINUTES), [
            'approvalRequest' => $approvalRequest->ulid,
            'user' => $user->ulid,
        ]))->with('success', 'Thank you, your vote has been recorded.');
    }
}
