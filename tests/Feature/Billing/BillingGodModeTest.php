<?php

use App\Actions\Billing\DraftInvoice;
use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\ManageExpenses;
use App\Actions\Billing\RecordReceipt;
use App\Models\FeeSchedulePeriod;
use App\Models\GodModeChange;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->travelTo('2025-10-01 10:00');
    $this->deal = billableDeal();
    $this->seed(PermissionSeeder::class);
    $maker = billingUser(['billing.view', 'billing.raise']);
    $checker = billingUser();
    $periods = FeeSchedulePeriod::query()->orderBy('id')->get();
    $this->proforma = app(IssueInvoice::class)->issue(app(DraftInvoice::class)->proforma($this->deal, [$periods[0]->id, $periods[1]->id], [], [], null, $maker), $checker)['invoice'];
    $this->tax = app(IssueInvoice::class)->convert($this->proforma, $checker)['invoice'];
    $this->receipt = app(RecordReceipt::class)->record($this->proforma, ['received_on' => '2025-10-01', 'amount' => '100000'], $checker);
    $this->expense = app(ManageExpenses::class)->add($this->deal, ['description' => 'Courier', 'amount' => '500'], [], $maker);
});

function godFix(string $editor, string $id, array $changes)
{
    $form = godForm($editor, $id);

    return test()->post("/god-mode/correct/{$editor}/{$id}", [
        'values' => array_replace($form['values'], $changes),
        'fingerprint' => $form['fingerprint'],
        'reason' => 'Corrected against the signed copy',
    ]);
}

test('God Mode lists a deal\'s invoices, lines, receipts and expenses', function () {
    godUser();
    $this->get("/god-mode/transactions/{$this->deal->ulid}")->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sections', fn ($sections) => collect($sections)->firstWhere('title', 'Invoices') !== null
            && collect(collect($sections)->firstWhere('title', 'Invoices')['items'])->pluck('editor')->countBy()->all() === ['invoice' => 2, 'invoice-line' => 4, 'invoice-receipt' => 1, 'deal-expense' => 1]));
});

test('correcting a line re-totals the invoice at its own rates, updates what is due, and can be undone', function () {
    godUser();
    $line = $this->proforma->lines()->orderBy('position')->get()[1]; // service fee, 2,00,000

    godFix('invoice-line', (string) $line->id, ['amount' => '0'])->assertSessionHasErrors('amount');
    godFix('invoice-line', (string) $line->id, ['amount' => '150000'])->assertSessionHasNoErrors();

    $proforma = $this->proforma->fresh();
    expect($proforma->taxable_amount)->toBe('250000.00')
        ->and($proforma->cgst)->toBe('22500.00')
        ->and($proforma->total)->toBe('295000.00')
        ->and($proforma->balance_due)->toBe('195000.00');
    Storage::disk('local')->assertExists($proforma->pdf_path);

    $this->post('/god-mode/changes/'.GodModeChange::query()->latest('id')->value('ulid').'/rollback', ['reason' => 'Wrong line'])->assertSessionHasNoErrors();
    expect($this->proforma->fresh()->total)->toBe('354000.00')->and($this->proforma->fresh()->balance_due)->toBe('254000.00');
});

test('invoice heading, receipts and expenses are corrected within their limits', function () {
    godUser();
    godFix('invoice', (string) $this->tax->id, ['billed_gstin' => '27AAAAA0000A1Z0'])->assertSessionHasErrors('billed_gstin');
    godFix('invoice', (string) $this->tax->id, ['number' => 'BTL/2526/TAX099', 'billed_name' => 'Issuer Finance Limited'])->assertSessionHasNoErrors();
    expect($this->tax->fresh()->number)->toBe('BTL/2526/TAX099');

    godFix('invoice-receipt', (string) $this->receipt->id, ['amount' => '354000.01'])->assertSessionHasErrors('amount');
    godFix('invoice-receipt', (string) $this->receipt->id, ['amount' => '300000', 'tds_amount' => '54000'])->assertSessionHasNoErrors();
    expect($this->proforma->fresh()->balance_due)->toBe('0.00');

    godFix('deal-expense', (string) $this->expense->id, ['description' => 'Courier to Delhi'])->assertSessionHasNoErrors();
    $bill = app(DraftInvoice::class)->reimbursement($this->deal, [$this->expense->ulid], null, billingUser(['billing.raise']));
    godFix('deal-expense', (string) $this->expense->id, ['amount' => '600'])->assertSessionHasErrors(['amount' => 'This expense is on an invoice: correct the invoice line\'s amount instead.']);
    expect($bill->exists)->toBeTrue();
});
