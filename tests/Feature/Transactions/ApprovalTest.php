<?php

use App\Enums\ApprovalStatus;
use App\Enums\TransactionStatus;
use App\Models\ApprovalRequest;
use App\Models\Transaction;
use App\Notifications\ApprovalDecided;
use App\Notifications\ApprovalRequested;
use App\Services\Auth\TwoFactor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(fn () => Notification::fake());

/** Submits a ready draft as a user with submit rights; returns [transaction, request, submitter]. */
function submitted(): array
{
    $submitter = wizardUser(['transactions.submit']);
    $transaction = draftReadyForApproval();
    test()->post("/transactions/{$transaction->ulid}/submit")->assertRedirect("/transactions/{$transaction->ulid}");

    return [$transaction->fresh(), ApprovalRequest::query()->sole(), $submitter];
}

function voteAs($user, ApprovalRequest $request, string $decision, ?string $comment = null)
{
    return test()->actingAs($user)
        ->withSession([TwoFactor::SESSION_PASSED_AT => now()->getTimestamp()])
        ->post("/approvals/{$request->ulid}/vote", ['decision' => $decision, 'comment' => $comment]);
}

test('submitting needs the submit permission and a complete draft with a verified schedule', function () {
    wizardUser();
    $transaction = draftReadyForApproval();
    $this->post("/transactions/{$transaction->ulid}/submit")->assertForbidden();

    wizardUser(['transactions.submit']);
    $incomplete = draftReadyForFees();
    $this->post("/transactions/{$incomplete->ulid}/submit")->assertSessionHasErrors('transaction');
    expect($incomplete->fresh()->status)->toBe(TransactionStatus::Draft);
});

test('submitting locks the draft, opens a request and emails the approvers (not the submitter)', function () {
    $headApprover = approver(head: true);
    $otherApprover = approver();
    [$transaction, $request, $submitter] = submitted();
    $submitter->givePermissionTo('approvals.vote');

    expect($transaction->status)->toBe(TransactionStatus::PendingApproval)
        ->and($transaction->submitted_at)->not->toBeNull()
        ->and($request->status)->toBe(ApprovalStatus::Open);
    Notification::assertSentTo([$headApprover, $otherApprover], ApprovalRequested::class);
    Notification::assertNotSentTo($submitter, ApprovalRequested::class);

    $this->put("/transactions/{$transaction->ulid}/issue", issuePayload())->assertForbidden();
    $this->post("/transactions/{$transaction->ulid}/submit")->assertForbidden(); // can't submit twice
});

test('approval needs a head approver plus one more, and the submitter can\'t vote', function () {
    $head = approver(head: true);
    $second = approver();
    [$transaction, $request, $submitter] = submitted();
    $submitter->givePermissionTo('approvals.vote');

    voteAs($submitter, $request, 'approve')->assertSessionHasErrors('vote');

    voteAs($second, $request, 'approve')->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(ApprovalStatus::Open); // no head approver yet

    voteAs($second, $request, 'approve')->assertSessionHasErrors('vote'); // one vote each

    voteAs($head, $request, 'approve')->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Approved)
        ->and($transaction->fresh()->approved_at)->not->toBeNull();
    Notification::assertSentTo($submitter, ApprovalDecided::class);

    voteAs(approver(), $request, 'approve')->assertSessionHasErrors('vote'); // closed
});

test('two approvals without a head approver are not enough', function () {
    [, $request] = submitted();

    voteAs(approver(), $request, 'approve');
    voteAs(approver(), $request, 'approve');

    expect($request->fresh()->status)->toBe(ApprovalStatus::Open);
});

test('any rejection rejects; it needs a reason; the draft can then be revised and resubmitted', function () {
    $head = approver(head: true);
    [$transaction, $request, $submitter] = submitted();

    voteAs($head, $request, 'reject')->assertSessionHasErrors('comment');
    voteAs($head, $request, 'reject', 'Service fee is below the floor for this issue size.')->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Rejected);

    actAs($submitter)->post("/transactions/{$transaction->ulid}/revise")->assertRedirect("/transactions/{$transaction->ulid}/edit");
    expect($transaction->fresh()->status)->toBe(TransactionStatus::Draft);

    $this->post("/transactions/{$transaction->ulid}/submit")->assertSessionHasNoErrors();
    expect(ApprovalRequest::query()->count())->toBe(2) // each round is its own record
        ->and($transaction->fresh()->latestApprovalRequest->status)->toBe(ApprovalStatus::Open);
});

test('users without the approver permission cannot vote', function () {
    [, $request] = submitted();
    $user = wizardUser();

    voteAs($user, $request, 'approve')->assertSessionHasErrors('vote');
});

test('the approvals inbox lists requests waiting for my vote', function () {
    $head = approver(head: true);
    [$transaction, $request] = submitted();

    actAs($head)->get('/approvals')->assertInertia(fn ($page) => $page
        ->component('Approvals/Index')
        ->where('requests.0.transaction_id', $transaction->ulid));

    voteAs($head, $request, 'approve');
    actAs($head)->get('/approvals')->assertInertia(fn ($page) => $page->has('requests', 0));
});

test('email links are signed per approver and expire; GET only shows, POST votes', function () {
    $head = approver(head: true);
    $second = approver();
    [$transaction, $request] = submitted();
    auth()->logout();

    $link = URL::temporarySignedRoute('approvals.email', now()->addDays(7), ['approvalRequest' => $request->ulid, 'user' => $head->ulid]);
    $this->get($link)->assertOk()->assertInertia(fn ($page) => $page->component('Approvals/EmailVote')->where('canVote', true));
    expect($request->votes()->count())->toBe(0);

    // Tampering with the user or using an unsigned/expired link fails.
    $this->get(str_replace($head->ulid, $second->ulid, $link))->assertForbidden();
    $this->get(route('approvals.email', ['approvalRequest' => $request->ulid, 'user' => $head->ulid]))->assertForbidden();
    $this->travel(8)->days();
    $this->get($link)->assertForbidden();
    $this->travelBack();

    $voteUrl = URL::temporarySignedRoute('approvals.email.vote', now()->addMinutes(30), ['approvalRequest' => $request->ulid, 'user' => $head->ulid]);
    $this->post($voteUrl, ['decision' => 'approve'])->assertRedirect();
    $this->post(URL::temporarySignedRoute('approvals.email.vote', now()->addMinutes(30), ['approvalRequest' => $request->ulid, 'user' => $second->ulid]), ['decision' => 'approve']);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->votes()->pluck('via')->unique()->all())->toBe(['email'])
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Approved);
});

test('the transaction page shows the approval history and who can vote', function () {
    $head = approver(head: true);
    [$transaction, $request] = submitted();
    voteAs(approver(), $request, 'approve');

    actAs($head)->get("/transactions/{$transaction->ulid}")->assertInertia(fn ($page) => $page
        ->where('approvals.0.votes.0.decision', 'approve')
        ->where('can.vote', true)
        ->where('openRequestId', $request->ulid));
});
