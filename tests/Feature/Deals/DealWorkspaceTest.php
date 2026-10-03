<?php

use App\Enums\DealStatus;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia;

test('the deal list shows only deals, filtered by status and search', function () {
    dealUser();
    $live = Transaction::factory()->deal(DealStatus::Live)->create();
    Transaction::factory()->deal()->create();
    Transaction::factory()->create(); // a draft is not a deal

    $this->get('/deals')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Deals/Index')
        ->has('deals.data', 2));

    $this->get('/deals?filter[status]=live')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('deals.data', 1)
        ->where('deals.data.0.id', $live->ulid)
        ->where('deals.data.0.deal_status_label', 'Live'));

    $this->get('/deals?filter[search]='.urlencode($live->el_number))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('deals.data', 1));

    $live->company->update(['name' => 'Sachdev, Gala and Bhatia Private Limited']);
    $this->get('/deals?filter[search]='.urlencode('Sachdev, Gala'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('deals.data', 1));
});

test('the workspace shows the deal, its next statuses and its activity', function () {
    dealUser(['deals.status.request']);
    $deal = Transaction::factory()->deal()->create();

    $this->get("/deals/{$deal->ulid}?tab=status")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Deals/Show')
        ->where('tab', 'status')
        ->where('deal.el_number', $deal->el_number)
        ->where('deal.deal_status', 'preliminary')
        ->where('status.next.0.value', 'documentation')
        ->where('status.next.0.teams', ['Management', 'Accounts'])
        ->where('status.next.2.value', 'hold')
        ->where('status.next.2.teams', [])
        ->where('can.requestStatus', true)
        ->where('can.editBilling', false)
        ->has('status.history', 1)
        ->where('activity.0.area', fn ($area) => in_array($area, ['Status', 'Transaction'], true)));

    // An unknown tab falls back to the overview.
    $this->get("/deals/{$deal->ulid}?tab=nope")->assertInertia(fn (AssertableInertia $page) => $page->where('tab', 'overview'));
});

test('the deal list and page need the deals permission', function () {
    signIn(permissions: ['transactions.view']);
    $deal = Transaction::factory()->deal()->create();

    $this->get('/deals')->assertForbidden();
    $this->get("/deals/{$deal->ulid}")->assertForbidden();
});
