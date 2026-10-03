<?php

use App\Enums\DealStatus;
use App\Enums\StatusApprovalTeam;
use App\Models\DealJobSheetEntry;
use App\Models\JobSheetActivity;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

test('the dashboard shows deal and transaction counts to people who can see them', function () {
    $this->travelTo('2025-10-01 10:00');
    signIn(permissions: ['deals.view', 'transactions.view']);
    Transaction::factory()->deal(DealStatus::Live)->create();
    Transaction::factory()->deal()->create();
    Transaction::factory()->deal(DealStatus::Redeemed)->create();
    Transaction::factory()->create(); // draft

    $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Dashboard')
        ->where('kpis.0.key', 'open')->where('kpis.0.value', 2)
        ->where('kpis.1.key', 'live')->where('kpis.1.value', 1)
        ->where('kpis.2.value', 3)
        ->where('kpis.3.value', '4000000000.00')
        ->where('kpis.4.key', 'drafts')->where('kpis.4.value', 1));
});

test('a user without those permissions sees no numbers and an empty queue', function () {
    signIn();

    $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('kpis', null)
        ->where('queue', []));
});

test('the queue lists, oldest first, status changes for the user\'s team and entries waiting for their check', function () {
    Notification::fake();
    $this->travelTo('2025-10-01 10:00');
    $deal = Transaction::factory()->deal()->create();

    dealUser(['deals.status.request']);
    $this->post("/deals/{$deal->ulid}/status", ['to_status' => 'live', 'effective_on' => '2025-09-30', 'reason' => 'DTD executed']);

    $this->travel(1)->hours();
    $maker = User::factory()->create();
    DealJobSheetEntry::query()->forceCreate([
        'transaction_id' => $deal->id,
        'job_sheet_activity_id' => JobSheetActivity::query()->create(['name' => 'DTD received'])->id,
        'status' => 'submitted', 'received_on' => '2025-09-29', 'maker_id' => $maker->id, 'made_at' => now(),
    ]);

    $accounts = statusApprover(StatusApprovalTeam::Accounts);
    $accounts->givePermissionTo('deals.jobsheet.check');
    actAs($accounts);

    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('queue', 2)
        ->where('queue.0.kind', 'Status change')
        ->where('queue.0.detail', 'Preliminary → Live')
        ->where('queue.1.kind', 'Job sheet check'));
});
