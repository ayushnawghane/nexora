<?php

use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\ManageExpenses;
use App\Actions\Billing\RecordReceipt;
use App\Actions\Transactions\RegenerateSchedule;
use App\Actions\Transactions\SaveFees;
use App\Enums\FeeKind;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\NumberSequence;
use App\Models\State;
use App\Models\TaxRate;
use App\Notifications\InvoiceIssued;
use App\Services\EInvoice\EInvoiceFailed;
use App\Services\EInvoice\EInvoiceGateway;
use App\Services\EInvoice\Irn;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2025-10-01 10:00');
    $this->deal = billableDeal();
    $this->maker = billingUser(['billing.view', 'billing.raise']);
    $this->checker = billingUser(['billing.view', 'billing.approve', 'billing.receipts']);
    $this->periods = FeeSchedulePeriod::query()->orderBy('id')->get();
});

/** Drafts a proforma for the acceptance fee and the first service year. */
function draftProforma(array $others = [], array $expenseIds = []): Invoice
{
    return app(DraftInvoice::class)->proforma(test()->deal, test()->periods->take(2)->pluck('id')->all(), $expenseIds, $others, null, test()->maker);
}

test('a draft proforma takes its fee periods and works out GST; only someone else can issue it', function () {
    $draft = draftProforma([['description' => 'Documentation charges', 'amount' => '5000']]);

    expect($draft->status)->toBe(InvoiceStatus::Draft)
        ->and($draft->number)->toBeNull()
        ->and($draft->billed_name)->toBe('Issuer Finance Ltd')
        ->and($draft->taxable_amount)->toBe('305000.00')
        ->and($draft->cgst)->toBe('27450.00')
        ->and($draft->sgst)->toBe('27450.00')
        ->and($draft->igst)->toBe('0.00')
        ->and($draft->total)->toBe('359900.00')
        ->and($draft->period_from->toDateString())->toBe('2025-04-01')
        ->and($draft->period_to->toDateString())->toBe('2026-03-31')
        ->and($this->periods->take(2)->every(fn ($p) => $p->fresh()->invoice_id === $draft->id))->toBeTrue();

    // The same period can't be billed twice, even by another draft.
    expect(fn () => app(DraftInvoice::class)->proforma($this->deal, [$this->periods[0]->id], [], [], null, $this->maker))
        ->toThrow(ValidationException::class, 'already been billed');

    expect(fn () => app(IssueInvoice::class)->issue($draft, $this->maker))->toThrow(ValidationException::class, 'other than its maker');

    ['invoice' => $issued, 'emailed' => $emailed] = app(IssueInvoice::class)->issue($draft, $this->checker);
    expect($issued->number)->toBe('BTL/2526/INV001')
        ->and($issued->status)->toBe(InvoiceStatus::Issued)
        ->and($issued->invoice_date->toDateString())->toBe('2025-10-01')
        ->and($issued->irn)->toBeNull() // proformas aren't e-invoiced
        ->and($emailed)->toBe(1);
    Storage::disk('local')->assertExists($issued->pdf_path);
    Notification::assertSentTo(new AnonymousNotifiable, InvoiceIssued::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === ['accounts@issuer.test']);
    expect($issued->mails()->count())->toBe(1);
});

test('issuing re-reads who is billed and the GST in force; an out-of-state client pays IGST', function () {
    $draft = draftProforma();
    $karnataka = State::query()->where('gst_code', '29')->value('id');
    $this->deal->billing->update(['place_of_supply_state_id' => $karnataka]);

    $issued = app(IssueInvoice::class)->issue($draft, $this->checker)['invoice'];
    expect($issued->inter_state)->toBeTrue()
        ->and($issued->igst)->toBe('54000.00')
        ->and($issued->cgst)->toBe('0.00')
        ->and($issued->total)->toBe('354000.00');
});

test('a sent-back draft returns to its maker, who revises it; discarding gives the periods back', function () {
    $draft = draftProforma();
    expect(fn () => app(IssueInvoice::class)->sendBack($draft, 'Wrong period', $this->maker))->toThrow(ValidationException::class);
    app(IssueInvoice::class)->sendBack($draft, 'Bill only the acceptance fee', $this->checker);
    expect($draft->fresh()->returned_reason)->toBe('Bill only the acceptance fee');

    expect(fn () => app(DraftInvoice::class)->revise($draft, [$this->periods[0]->id], [], [], null, $this->checker))
        ->toThrow(ValidationException::class, 'Only the person who drafted it');
    $revised = app(DraftInvoice::class)->revise($draft, [$this->periods[0]->id], [], [], 'Acceptance only', $this->maker);
    expect($revised->returned_reason)->toBeNull()
        ->and($revised->total)->toBe('118000.00')
        ->and($this->periods[1]->fresh()->invoice_id)->toBeNull();

    app(DraftInvoice::class)->discard($revised, $this->maker);
    expect(Invoice::query()->count())->toBe(0)
        ->and(FeeSchedulePeriod::query()->whereNotNull('invoice_id')->count())->toBe(0);
});

test('receipts and TDS settle the proforma; its tax invoice follows with an IRN', function () {
    $proforma = app(IssueInvoice::class)->issue(draftProforma(), $this->checker)['invoice'];
    $this->travelTo('2025-10-20 11:00');

    $tax = app(IssueInvoice::class)->convert($proforma, $this->checker)['invoice'];
    expect($tax->kind)->toBe(InvoiceKind::Tax)
        ->and($tax->number)->toBe('BTL/2526/TAX001')
        ->and($tax->invoice_date->toDateString())->toBe('2025-10-20')
        ->and($tax->parent_id)->toBe($proforma->id)
        ->and($tax->total)->toBe('354000.00')
        ->and($tax->irn)->toHaveLength(64)
        ->and($tax->signed_qr)->not->toBeNull()
        ->and($tax->lines()->count())->toBe(2)
        ->and($proforma->fresh()->status)->toBe(InvoiceStatus::Converted);
    expect(fn () => app(IssueInvoice::class)->convert($proforma->fresh(), $this->checker))->toThrow(ValidationException::class, 'already been converted');

    // Money is recorded against the proforma (as in Stack), before or after its tax invoice.
    $receipts = app(RecordReceipt::class);
    expect(fn () => $receipts->record($proforma, ['received_on' => '2025-09-30', 'amount' => '1000'], $this->checker))->toThrow(ValidationException::class, 'before the invoice date');
    expect(fn () => $receipts->record($proforma, ['received_on' => '2025-10-20', 'amount' => '354000.01'], $this->checker))->toThrow(ValidationException::class, 'outstanding');
    expect(fn () => $receipts->record($tax, ['received_on' => '2025-10-20', 'amount' => '1'], $this->checker))->toThrow(ValidationException::class, 'proformas and reimbursement bills');

    $part = $receipts->record($proforma, ['received_on' => '2025-10-20', 'amount' => '318600', 'tds_amount' => '30000', 'utr' => 'UTR123'], $this->checker);
    expect($proforma->fresh()->balance_due)->toBe('5400.00')->and($tax->fresh()->balance_due)->toBe('0.00');

    $receipts->reverse($part, 'Recorded on the wrong invoice', $this->checker);
    expect($proforma->fresh()->balance_due)->toBe('354000.00');
    expect(fn () => $receipts->reverse($part, 'again', $this->checker))->toThrow(ValidationException::class);

    $receipts->record($proforma, ['received_on' => '2025-10-21', 'amount' => '354000'], $this->checker);
    expect($proforma->fresh()->balance_due)->toBe('0.00');
});

test('the IRP refusing a tax invoice leaves nothing behind and uses no number', function () {
    $proforma = app(IssueInvoice::class)->issue(draftProforma(), $this->checker)['invoice'];
    app()->instance(EInvoiceGateway::class, new class implements EInvoiceGateway
    {
        public function register($invoice): ?Irn
        {
            throw new EInvoiceFailed('GSTIN not active');
        }

        public function cancel($invoice, string $reason): void {}
    });

    expect(fn () => app(IssueInvoice::class)->convert($proforma, $this->checker))->toThrow(ValidationException::class, 'GSTIN not active');
    expect(Invoice::query()->where('kind', InvoiceKind::Tax)->exists())->toBeFalse()
        ->and($proforma->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and(NumberSequence::query()->where('key', 'invoice:tax:2526')->value('last_value'))->toBeNull()
        ->and(Storage::disk('local')->allFiles("invoices/{$this->deal->ulid}"))->toHaveCount(1); // only the proforma's
});

test('a client without a GSTIN gets no IRN', function () {
    $this->deal = billableDeal(withGstin: false);
    $this->periods = FeeSchedulePeriod::query()->whereHas('feeLine', fn ($q) => $q->where('transaction_id', $this->deal->id))->orderBy('id')->get();
    $proforma = app(IssueInvoice::class)->issue(draftProforma(), $this->checker)['invoice'];
    $tax = app(IssueInvoice::class)->convert($proforma, $this->checker)['invoice'];

    expect($tax->billed_gstin)->toBeNull()->and($tax->irn)->toBeNull();
});

test('credit notes reduce a tax invoice line by line, never beyond it, at its own GST rates', function () {
    $proforma = app(IssueInvoice::class)->issue(draftProforma(), $this->checker)['invoice'];
    $tax = app(IssueInvoice::class)->convert($proforma, $this->checker)['invoice'];
    [$acceptanceLine, $serviceLine] = $tax->lines()->get()->all();

    expect(fn () => app(DraftInvoice::class)->creditNote($tax, [$serviceLine->id => '200000.01'], 'Fee waived', $this->maker))
        ->toThrow(ValidationException::class, 'At most ₹200000.00');
    $draft = app(DraftInvoice::class)->creditNote($tax, [$serviceLine->id => '50000'], 'Service fee reduced by agreement', $this->maker);
    // A second draft can't credit what the first already takes.
    expect(fn () => app(DraftInvoice::class)->creditNote($tax, [$serviceLine->id => '150000.01'], 'x', $this->maker))->toThrow(ValidationException::class);

    // GST rates changed since: the credit note still reverses at the invoice's 9% + 9%.
    TaxRate::query()->create(['effective_from' => '2025-10-01', 'cgst' => '6', 'sgst' => '6', 'igst' => '12']);
    $note = app(IssueInvoice::class)->issue($draft, $this->checker)['invoice'];
    expect($note->number)->toBe('BTL/2526/CN001')
        ->and($note->total)->toBe('59000.00')
        ->and($note->cgst)->toBe('4500.00')
        ->and($note->irn)->not->toBeNull()
        ->and($proforma->fresh()->balance_due)->toBe('295000.00'); // what the client owes drops

    // The tax invoice can't be cancelled while it has credit notes.
    expect(fn () => app(IssueInvoice::class)->cancel($tax->fresh(), 'Mistake', $this->checker))->toThrow(ValidationException::class, 'credit notes');
});

test('cancelling: proformas give their periods back; tax invoices only within 24 hours of the IRN', function () {
    $proforma = app(IssueInvoice::class)->issue(draftProforma(), $this->checker)['invoice'];
    $tax = app(IssueInvoice::class)->convert($proforma, $this->checker)['invoice'];
    expect(fn () => app(IssueInvoice::class)->cancel($proforma->fresh(), 'x', $this->checker))->toThrow(ValidationException::class, 'Cancel the tax invoice');

    $this->travelTo('2025-10-02 09:00');
    $cancelled = app(IssueInvoice::class)->cancel($tax, 'Billed to the wrong GSTIN', $this->checker);
    expect($cancelled->status)->toBe(InvoiceStatus::Cancelled)
        ->and($cancelled->irn_cancelled_at)->not->toBeNull()
        ->and($proforma->fresh()->status)->toBe(InvoiceStatus::Issued);

    $tax2 = app(IssueInvoice::class)->convert($proforma->fresh(), $this->checker)['invoice'];
    expect($tax2->number)->toBe('BTL/2526/TAX002'); // numbers are never reused
    $this->travelTo('2025-10-03 10:00');
    expect(fn () => app(IssueInvoice::class)->cancel($tax2, 'Too late', $this->checker))->toThrow(ValidationException::class, 'within 24 hours');

    // A proforma that was never converted can be cancelled, and its periods billed again.
    $other = app(IssueInvoice::class)->issue(app(DraftInvoice::class)->proforma($this->deal, [$this->periods[2]->id], [], [], null, $this->maker), $this->checker)['invoice'];
    app(IssueInvoice::class)->cancel($other, 'Raised too early', $this->checker);
    expect($this->periods[2]->fresh()->invoice_id)->toBeNull();
});

test('expenses are billed once, on a reimbursement bill without GST, and lock while billed', function () {
    $expenses = app(ManageExpenses::class);
    $courier = $expenses->add($this->deal, ['incurred_on' => '2025-09-10', 'description' => 'Courier of executed documents', 'amount' => '1200.50'], [UploadedFile::fake()->create('bill.pdf', 20, 'application/pdf')], $this->maker);
    $stamp = $expenses->add($this->deal, ['incurred_on' => '2025-09-12', 'description' => 'Stamp duty', 'amount' => '500'], [], $this->maker);
    expect($courier->files()->count())->toBe(1);

    $draft = app(DraftInvoice::class)->reimbursement($this->deal, [$courier->ulid, $stamp->ulid], null, $this->maker);
    expect($draft->total)->toBe('1700.50')->and($draft->cgst)->toBe('0.00');
    expect(fn () => $expenses->update($courier, ['description' => 'Changed', 'amount' => '1'], [], $this->maker))->toThrow(ValidationException::class, 'on an invoice');

    $bill = app(IssueInvoice::class)->issue($draft, $this->checker)['invoice'];
    expect($bill->number)->toBe('BTL/2526/DN001')->and($bill->irn)->toBeNull()->and($bill->balance_due)->toBe('1700.50');

    app(IssueInvoice::class)->cancel($bill, 'Client paid directly', $this->checker);
    $expenses->remove($stamp->fresh(), $this->maker);
    expect($stamp->fresh()->removed_at)->not->toBeNull();
    expect(fn () => app(DraftInvoice::class)->reimbursement($this->deal, [$stamp->ulid], null, $this->maker))->toThrow(ValidationException::class, 'removed');
});

test('expenses can also ride on a proforma, without GST on them', function () {
    $courier = app(ManageExpenses::class)->add($this->deal, ['incurred_on' => '2025-09-10', 'description' => 'Courier', 'amount' => '1000'], [], $this->maker);
    $draft = draftProforma(expenseIds: [$courier->ulid]);

    expect($draft->taxable_amount)->toBe('300000.00')
        ->and($draft->non_taxable_amount)->toBe('1000.00')
        ->and($draft->total)->toBe('355000.00');
});

test('rebuilding a schedule keeps billed periods; a billed fee can\'t be switched off', function () {
    $draft = draftProforma();
    app(RegenerateSchedule::class)->handle($this->deal);

    expect($this->periods[0]->fresh())->not->toBeNull()
        ->and($this->periods[1]->fresh()->invoice_id)->toBe($draft->id);
    $service = $this->deal->feeLines()->where('kind', FeeKind::Service)->sole();
    expect($service->periods()->min('from_date'))->toBe('2025-04-01')
        ->and($service->periods()->where('from_date', '<=', '2026-03-31')->count())->toBe(1);

    expect(fn () => app(SaveFees::class)->handle($this->deal, [
        'acceptance' => ['enabled' => false],
        'service' => feesPayload()['fees']['service'],
    ], $this->maker))->toThrow(ValidationException::class, 'has been billed');
});

test('two people issuing at once never get the same number', function () {
    $a = draftProforma();
    $b = app(DraftInvoice::class)->proforma($this->deal, [$this->periods[2]->id], [], [], null, $this->maker);
    $numbers = [app(IssueInvoice::class)->issue($a, $this->checker)['invoice']->number, app(IssueInvoice::class)->issue($b, $this->checker)['invoice']->number];

    expect($numbers)->toBe(['BTL/2526/INV001', 'BTL/2526/INV002']);
});
