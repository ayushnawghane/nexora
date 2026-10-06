<?php

use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Enums\InvoiceStatus;
use App\Models\DealExpense;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2025-10-01 10:00');
    $this->deal = billableDeal();
    $this->periods = FeeSchedulePeriod::query()->orderBy('id')->get();
});

function draftOverHttp(array $data)
{
    return test()->post('/deals/'.test()->deal->ulid.'/invoices', $data);
}

test('drafting needs billing: raise; the form is checked on the server', function () {
    signIn(permissions: ['deals.view', 'billing.view']);
    draftOverHttp(['kind' => 'proforma', 'periods' => [$this->periods[0]->id]])->assertForbidden();

    signIn(permissions: ['deals.view', 'billing.view', 'billing.raise']);
    draftOverHttp(['kind' => 'tax', 'periods' => [$this->periods[0]->id]])->assertSessionHasErrors('kind');
    draftOverHttp(['kind' => 'proforma', 'others' => [['description' => 'Fee', 'amount' => '10.555']]])->assertSessionHasErrors('others.0.amount');
    draftOverHttp(['kind' => 'proforma', 'others' => [['description' => ' ', 'amount' => '10']]])->assertSessionHasErrors(['others.0.description' => 'Describe the fee.']);
    draftOverHttp(['kind' => 'reimbursement', 'others' => [['description' => 'Fee', 'amount' => '10']]])->assertSessionHasErrors('others');
    draftOverHttp(['kind' => 'proforma'])->assertSessionHasErrors(['periods' => 'Pick at least one fee period, expense or other fee to bill.']);

    $other = billableDeal();
    $foreign = FeeSchedulePeriod::query()->whereHas('feeLine', fn ($q) => $q->where('transaction_id', $other->id))->first();
    draftOverHttp(['kind' => 'proforma', 'periods' => [$foreign->id]])->assertSessionHasErrors(['periods' => 'A fee period isn\'t on this deal.']);

    $response = draftOverHttp(['kind' => 'proforma', 'periods' => [$this->periods[0]->id], 'others' => [['description' => ' Out-of-hours meeting ', 'amount' => '2500']], 'notes' => 'PO 4471']);
    $invoice = Invoice::query()->sole();
    $response->assertRedirect("/invoices/{$invoice->ulid}");
    expect($invoice->lines()->pluck('description')->all())->toBe(['Acceptance fee', 'Out-of-hours meeting'])
        ->and($invoice->notes)->toBe('PO 4471');
});

test('the invoice page shows what each person may do; the maker can\'t issue', function () {
    $maker = signIn(permissions: ['deals.view', 'billing.view', 'billing.raise', 'billing.approve']);
    draftOverHttp(['kind' => 'proforma', 'periods' => [$this->periods[0]->id]]);
    $invoice = Invoice::query()->sole();

    $this->get("/invoices/{$invoice->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Invoices/Show')
        ->where('invoice.status', 'draft')
        ->where('invoice.total', '118000.00')
        ->where('can.issue', false)
        ->where('can.revise', true)
        ->where('can.discard', true)
        ->has('revise.periods', 4)
        ->where('revise.initial.periods', [$this->periods[0]->id]));
    $this->post("/invoices/{$invoice->ulid}/issue")->assertSessionHasErrors(['invoice' => 'Someone other than its maker has to issue it.']);

    signIn(permissions: ['deals.view', 'billing.view', 'billing.approve']);
    $this->get("/invoices/{$invoice->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can.issue', true)
        ->where('can.sendBack', true)
        ->where('can.revise', false)
        ->where('revise', null));
    $this->post("/invoices/{$invoice->ulid}/send-back", ['reason' => 'no'])->assertSessionHasErrors('reason');
    $this->post("/invoices/{$invoice->ulid}/issue")->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Issued);

    // The PDF of an issued invoice is the stored one.
    $this->get("/invoices/{$invoice->ulid}/pdf")->assertOk()->assertDownload('BTL-2526-INV001.pdf');
});

test('a draft\'s PDF is a preview; billing.view is needed to see invoices at all', function () {
    $maker = billingUser(['billing.view', 'billing.raise']);
    $this->actingAs($maker);
    $draft = app(DraftInvoice::class)->proforma($this->deal, [$this->periods[0]->id], [], [], null, $maker);

    signIn(permissions: ['deals.view']);
    $this->get("/invoices/{$draft->ulid}")->assertForbidden();
    $this->get('/invoices')->assertForbidden();
    $this->get('/billing')->assertForbidden();
    $this->get("/deals/{$this->deal->ulid}?tab=invoices")->assertInertia(fn (AssertableInertia $page) => $page->where('invoices', null)->where('can.viewBilling', false));

    signIn(permissions: ['deals.view', 'billing.view']);
    $response = $this->get("/invoices/{$draft->ulid}/pdf")->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('the hub lists invoices by tab, searches, and exports to Excel', function () {
    $checker = billingUser();
    $maker = billingUser(['billing.view', 'billing.raise']);
    $drafts = app(DraftInvoice::class);
    $issued = app(IssueInvoice::class)->issue($drafts->proforma($this->deal, [$this->periods[0]->id], [], [], null, $maker), $checker)['invoice'];
    $tax = app(IssueInvoice::class)->convert($issued, $checker)['invoice'];
    $drafts->proforma($this->deal, [$this->periods[1]->id], [], [], null, $maker);

    signIn(permissions: ['deals.view', 'billing.view']);
    $this->get('/invoices')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Invoices/Index')
        ->where('filters.tab', 'due')
        ->where('counts', ['drafts' => 1, 'due' => 1, 'overdue' => 0])
        ->has('invoices.data', 1)
        ->where('invoices.data.0.number', 'BTL/2526/INV001') // the proforma is what's owed, converted or not
        ->where('invoices.data.0.balance_due', '118000.00'));
    $this->get('/invoices?filter[tab]=drafts')->assertInertia(fn (AssertableInertia $page) => $page->has('invoices.data', 1)->where('invoices.data.0.number', null));
    $this->get('/invoices?filter[tab]=proforma')->assertInertia(fn (AssertableInertia $page) => $page->where('invoices.data.0.status', 'converted'));
    $this->get('/invoices?filter[tab]=tax&filter[search]=nothing-like-this')->assertInertia(fn (AssertableInertia $page) => $page->has('invoices.data', 0));

    // Overdue once past the payment terms.
    $this->travelTo('2025-11-05');
    $this->get('/invoices')->assertInertia(fn (AssertableInertia $page) => $page->where('counts.overdue', 1)->where('invoices.data.0.overdue', true));

    $this->get('/invoices/export?filter[tab]=tax')->assertOk()->assertDownload();
});

test('the deal tab shows periods with what billed them, expenses and the outstanding total', function () {
    $checker = billingUser();
    $user = signIn(permissions: ['deals.view', 'billing.view', 'billing.raise']);
    $this->post("/deals/{$this->deal->ulid}/expenses", ['description' => 'Courier', 'amount' => '0'])->assertSessionHasErrors('amount');
    $this->post("/deals/{$this->deal->ulid}/expenses", ['description' => 'Courier', 'amount' => '850', 'incurred_on' => '2025-10-02'])->assertSessionHasErrors('incurred_on');
    $this->post("/deals/{$this->deal->ulid}/expenses", ['description' => 'Courier', 'amount' => '850', 'incurred_on' => '2025-09-30', 'files' => [UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();
    $expense = DealExpense::query()->sole();

    draftOverHttp(['kind' => 'proforma', 'periods' => [$this->periods[0]->id], 'expenses' => [$expense->ulid]]);
    app(IssueInvoice::class)->issue(Invoice::query()->sole(), $checker);

    $this->get("/deals/{$this->deal->ulid}?tab=invoices")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can.raiseBilling', true)
        ->where('invoices.has_billing', true)
        ->where('invoices.balance_due', '118850.00') // proforma: acceptance fee with GST, plus the courier
        ->has('invoices.invoices', 1)
        ->where('invoices.periods.0.invoice.title', 'Proforma invoice BTL/2526/INV001')
        ->where('invoices.periods.1.billable', true)
        ->where('invoices.periods.1.due', true)
        ->where('invoices.periods.2.due', false)
        ->where('invoices.expenses.0.invoice.title', 'Proforma invoice BTL/2526/INV001')
        ->has('invoices.expenses.0.files', 1));

    // A billed expense is locked; its proof can't be removed on its own.
    $this->post("/deals/{$this->deal->ulid}/expenses/{$expense->ulid}", ['description' => 'Changed', 'amount' => '1'])->assertSessionHasErrors('description');
    $file = $expense->files()->sole();
    signIn(permissions: ['deals.view', 'deals.documents.manage']);
    $this->delete("/deals/{$this->deal->ulid}/files/{$file->ulid}")->assertForbidden();
});

test('the billing queue lists unbilled periods due within the window, by deal', function () {
    signIn(permissions: ['deals.view', 'billing.view']);
    $this->get('/billing')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Billing/Queue')
        ->has('deals.data', 1)
        ->where('deals.data.0.id', $this->deal->ulid)
        ->has('deals.data.0.periods', 2) // acceptance + FY 25-26; FY 26-27 bills on 1 April 2026
        ->where('deals.data.0.overdue', true)
        ->where('deals.data.0.total', '300000.00')
        ->where('canRaise', false));

    $this->travelTo('2026-03-10');
    signIn(permissions: ['deals.view', 'billing.view']); // the earlier session's 2FA has lapsed
    $this->get('/billing')->assertInertia(fn (AssertableInertia $page) => $page->has('deals.data.0.periods', 3));
    $this->get('/billing?search=no-such-company')->assertInertia(fn (AssertableInertia $page) => $page->has('deals.data', 0));
});

test('receipts and reversals over HTTP need billing.receipts', function () {
    $checker = billingUser();
    $maker = billingUser(['billing.view', 'billing.raise']);
    $proforma = app(IssueInvoice::class)->issue(app(DraftInvoice::class)->proforma($this->deal, [$this->periods[0]->id], [], [], null, $maker), $checker)['invoice'];

    signIn(permissions: ['deals.view', 'billing.view']);
    $this->post("/invoices/{$proforma->ulid}/receipts", ['received_on' => '2025-10-01', 'amount' => '100'])->assertForbidden();

    signIn(permissions: ['deals.view', 'billing.view', 'billing.receipts']);
    $this->get("/invoices/{$proforma->ulid}")->assertInertia(fn (AssertableInertia $page) => $page->where('can.receipt', true));
    $this->post("/invoices/{$proforma->ulid}/receipts", ['received_on' => '2025-10-02', 'amount' => '100'])->assertSessionHasErrors(['received_on' => 'The date received can\'t be in the future.']);
    $this->post("/invoices/{$proforma->ulid}/receipts", ['received_on' => '2025-10-01', 'amount' => '100', 'utr' => 'UTR 1'])->assertSessionHasErrors('utr');
    $this->post("/invoices/{$proforma->ulid}/receipts", ['received_on' => '2025-10-01', 'amount' => '106200', 'tds_amount' => '11800', 'utr' => 'HDFC0001/22'])->assertSessionHasNoErrors();
    expect($proforma->fresh()->balance_due)->toBe('0.00');

    $receipt = $proforma->receipts()->sole();
    $this->post("/invoice-receipts/{$receipt->ulid}/reverse", ['reason' => 'Bounced cheque'])->assertSessionHasNoErrors();
    expect($proforma->fresh()->balance_due)->toBe('118000.00');
});
