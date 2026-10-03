<?php

use App\Enums\DealStatus;
use App\Enums\StatusApprovalTeam;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Models\DealStatusRequest;
use App\Models\Transaction;
use App\Notifications\DealStatusDecided;
use App\Notifications\DealStatusRequested;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->travelTo('2025-10-01 10:00');
});

function requestStatus(Transaction $deal, array $overrides = [])
{
    return test()->post("/deals/{$deal->ulid}/status", array_merge([
        'to_status' => 'live', 'effective_on' => '2025-09-30', 'reason' => 'Debenture trust deed executed',
    ], $overrides));
}

function voteStatus(DealStatusRequest $request, string $team, string $decision = 'approve', ?string $comment = null)
{
    return test()->post("/deal-status-requests/{$request->ulid}/vote", ['team' => $team, 'decision' => $decision, 'comment' => $comment]);
}

test('moving a deal on needs Management and Accounts; it changes only when both approve', function () {
    $management = statusApprover(StatusApprovalTeam::Management);
    $accounts = statusApprover(StatusApprovalTeam::Accounts);
    $requester = dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();

    requestStatus($deal)->assertRedirect("/deals/{$deal->ulid}?tab=status")->assertSessionHas('success');

    $request = DealStatusRequest::query()->sole();
    expect($request->status)->toBe(StatusRequestState::Open)
        ->and($request->teams())->toBe([StatusApprovalTeam::Management, StatusApprovalTeam::Accounts])
        ->and($deal->fresh()->deal_status)->toBe(DealStatus::Preliminary);
    Notification::assertSentTo([$management, $accounts], DealStatusRequested::class);
    Notification::assertNotSentTo($requester, DealStatusRequested::class);

    actAs($management);
    voteStatus($request, 'management')->assertSessionHasNoErrors();
    expect($deal->fresh()->deal_status)->toBe(DealStatus::Preliminary);

    actAs($accounts);
    voteStatus($request, 'accounts')->assertSessionHasNoErrors();

    $deal->refresh();
    expect($request->fresh()->status)->toBe(StatusRequestState::Approved)
        ->and($deal->deal_status)->toBe(DealStatus::Live)
        ->and($deal->deal_status_since->toDateString())->toBe('2025-09-30')
        ->and($deal->status)->toBe(TransactionStatus::Active)
        ->and($deal->statusChanges()->latest('id')->first()->from_status)->toBe(DealStatus::Preliminary);
    Notification::assertSentTo($requester, DealStatusDecided::class);
});

test('any rejection rejects the request and leaves the deal where it was', function () {
    $management = statusApprover(StatusApprovalTeam::Management);
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();
    requestStatus($deal);
    $request = DealStatusRequest::query()->sole();

    actAs($management);
    voteStatus($request, 'management', 'reject')->assertSessionHasErrors('comment');
    voteStatus($request, 'management', 'reject', 'Trust deed not yet stamped')->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(StatusRequestState::Rejected)
        ->and($deal->fresh()->deal_status)->toBe(DealStatus::Preliminary);
});

test('putting a deal on hold applies at once, and it can only resume where it was', function () {
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal(DealStatus::Documentation)->create();

    requestStatus($deal, ['to_status' => 'hold'])->assertSessionHasNoErrors();
    $deal->refresh();
    expect($deal->deal_status)->toBe(DealStatus::Hold)
        ->and(DealStatusRequest::query()->sole()->status)->toBe(StatusRequestState::Approved);
    Notification::assertNothingSent();

    requestStatus($deal, ['to_status' => 'live'])->assertSessionHasErrors(['to_status' => 'A deal on hold resumes at the status it was put on hold from (Documentation).']);
    requestStatus($deal, ['to_status' => 'documentation'])->assertSessionHasNoErrors();
    expect(DealStatusRequest::query()->latest('id')->first()->teams())->toHaveCount(2);
});

test('cancelling a preliminary deal needs only Management', function () {
    $management = statusApprover(StatusApprovalTeam::Management);
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();

    requestStatus($deal, ['to_status' => 'cancelled', 'reason' => 'Issuer dropped the issue'])->assertSessionHasNoErrors();
    $request = DealStatusRequest::query()->sole();
    expect($request->teams())->toBe([StatusApprovalTeam::Management]);

    actAs($management);
    voteStatus($request, 'management');

    $deal->refresh();
    expect($deal->deal_status)->toBe(DealStatus::Cancelled)
        ->and($deal->status)->toBe(TransactionStatus::Closed)
        ->and($deal->closed_at)->not->toBeNull();
});

test('leaving Live needs the NOC, which is stored and downloadable', function () {
    $management = statusApprover(StatusApprovalTeam::Management);
    $accounts = statusApprover(StatusApprovalTeam::Accounts);
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal(DealStatus::Live)->create();

    requestStatus($deal, ['to_status' => 'redeemed'])->assertSessionHasErrors('noc');
    requestStatus($deal, ['to_status' => 'redeemed', 'noc' => UploadedFile::fake()->create('noc.exe', 10)])->assertSessionHasErrors('noc');
    expect(DealStatusRequest::query()->count())->toBe(0);

    requestStatus($deal, ['to_status' => 'redeemed', 'noc' => UploadedFile::fake()->create('noc.pdf', 100, 'application/pdf')])
        ->assertSessionHasNoErrors();
    $request = DealStatusRequest::query()->sole();
    Storage::disk('local')->assertExists($request->noc_path);
    $this->get("/deal-status-requests/{$request->ulid}/noc")->assertOk()->assertDownload('noc.pdf');

    actAs($management);
    voteStatus($request, 'management');
    actAs($accounts);
    voteStatus($request, 'accounts');

    expect($deal->fresh()->deal_status)->toBe(DealStatus::Redeemed)
        ->and($deal->fresh()->status)->toBe(TransactionStatus::Closed);

    // A final deal can't change again, and the request form is refused.
    dealUser(['deals.status.request']);
    requestStatus($deal, ['to_status' => 'live'])->assertForbidden();
});

test('only one request can be open, and only its requester can withdraw it', function () {
    $requester = dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();
    requestStatus($deal)->assertSessionHasNoErrors();
    requestStatus($deal, ['to_status' => 'documentation'])->assertSessionHasErrors('to_status');
    $request = DealStatusRequest::query()->sole();

    dealUser(['deals.status.request']);
    $this->post("/deal-status-requests/{$request->ulid}/withdraw")->assertSessionHasErrors('request');

    actAs($requester);
    $this->post("/deal-status-requests/{$request->ulid}/withdraw")->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(StatusRequestState::Withdrawn);
    requestStatus($deal, ['to_status' => 'documentation'])->assertSessionHasNoErrors();
});

test('voting rules: no self-approval, right team only, one team per person, one vote per team', function () {
    $both = statusApprover(StatusApprovalTeam::Management, StatusApprovalTeam::Accounts);
    $management = statusApprover(StatusApprovalTeam::Management);
    $otherManagement = statusApprover(StatusApprovalTeam::Management);
    $requester = dealUser(['deals.status.request', 'deals.status.approve_management']);
    $deal = Transaction::factory()->deal()->create();
    requestStatus($deal);
    $request = DealStatusRequest::query()->sole();

    voteStatus($request, 'management')->assertSessionHasErrors(['vote' => 'You can\'t vote on a request you raised.']);

    actAs($management);
    voteStatus($request, 'accounts')->assertSessionHasErrors(['vote' => 'You can\'t approve for Accounts.']);
    voteStatus($request, 'management')->assertSessionHasNoErrors();

    actAs($otherManagement);
    voteStatus($request, 'management')->assertSessionHasErrors(['vote' => 'Management has already voted on this request.']);

    actAs($both);
    voteStatus($request, 'management')->assertSessionHasErrors('vote');
    voteStatus($request, 'accounts')->assertSessionHasNoErrors();
    expect($deal->fresh()->deal_status)->toBe(DealStatus::Live);
});

test('a person who voted for one team can\'t also vote for the other', function () {
    $both = statusApprover(StatusApprovalTeam::Management, StatusApprovalTeam::Accounts);
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();
    requestStatus($deal);
    $request = DealStatusRequest::query()->sole();

    actAs($both);
    voteStatus($request, 'management')->assertSessionHasNoErrors();
    voteStatus($request, 'accounts')->assertSessionHasErrors('vote');
    expect($deal->fresh()->deal_status)->toBe(DealStatus::Preliminary);
});

test('status moves the state machine does not allow, and bad dates, are refused', function () {
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();

    requestStatus($deal, ['to_status' => 'redeemed'])->assertSessionHasErrors(['to_status' => 'A Preliminary deal can\'t move to Redeemed.']);
    requestStatus($deal, ['effective_on' => '2025-10-02'])->assertSessionHasErrors('effective_on');
    requestStatus($deal, ['effective_on' => '2025-03-31'])->assertSessionHasErrors('effective_on');
    requestStatus($deal, ['reason' => ' '])->assertSessionHasErrors('reason');
    expect(DealStatusRequest::query()->count())->toBe(0);
});

test('requesting a status change needs the permission, and drafts have no deal page', function () {
    dealUser();
    $deal = Transaction::factory()->deal()->create();
    requestStatus($deal)->assertForbidden();

    $draft = Transaction::factory()->create();
    $this->get("/deals/{$draft->ulid}")->assertForbidden();
});

test('the state machine only lets final statuses close the deal', function () {
    foreach (DealStatus::cases() as $status) {
        expect($status->isFinal())->toBe($status->allowedNext() === []);
        foreach ($status->allowedNext() as $next) {
            expect($status->approvalTeamsFor($next))->toBe(match (true) {
                $next === DealStatus::Hold => [],
                $status === DealStatus::Preliminary && $next === DealStatus::Cancelled => [StatusApprovalTeam::Management],
                default => [StatusApprovalTeam::Management, StatusApprovalTeam::Accounts],
            });
        }
    }
    expect(DealStatus::Live->needsNocToLeave())->toBeTrue()
        ->and(DealStatus::Documentation->needsNocToLeave())->toBeFalse();
});
