<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Services\Billing\InvoiceRenderer;
use App\Services\Billing\InvoiceTotals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/**
 * Corrects one line of an invoice (its description, period and amount). The invoice's totals are
 * worked out again at the GST rates it already carries, what's outstanding is updated, and an
 * issued invoice's PDF is rendered again. The line's fee period or expense stays linked.
 *
 * @extends Editor<InvoiceLine>
 */
class InvoiceLineEditor extends Editor
{
    public function key(): string
    {
        return 'invoice-line';
    }

    public function label(): string
    {
        return 'Invoice line';
    }

    public function modelClass(): string
    {
        return InvoiceLine::class;
    }

    public function values(Model $record): array
    {
        return [
            'description' => $record->description,
            'period_from' => $record->period_from?->toDateString(),
            'period_to' => $record->period_to?->toDateString(),
            'amount' => $record->amount,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'period_from', 'label' => 'Period from', 'type' => 'date'],
            ['name' => 'period_to', 'label' => 'Period to', 'type' => 'date'],
            ['name' => 'amount', 'label' => 'Amount (₹)', 'type' => 'number', 'required' => true],
        ];
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);

        return array_merge(['period_from' => null, 'period_to' => null], Validator::make($input, [
            'description' => ['required', 'string', 'max:255'],
            'period_from' => ['nullable', 'required_with:period_to', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'period_to' => ['nullable', 'required_with:period_from', 'date_format:Y-m-d', 'after_or_equal:period_from', 'before_or_equal:2100-12-31'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999', 'decimal:0,2'],
        ])->validate());
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->whereKey($record->invoice_id)->lockForUpdate()->firstOrFail();
        $invoice->update(app(InvoiceTotals::class)->at($invoice, $invoice->lines()->get()));
        // What's owed sits on the proforma: a credit note's line changes its tax invoice's proforma.
        $invoice->recalculateBalance();
        $parent = $invoice->parent()->first();
        $parent?->recalculateBalance();
        $parent?->parent()->first()?->recalculateBalance();
        if ($invoice->pdf_path !== null) {
            $invoice->forceFill(['pdf_path' => app(InvoiceRenderer::class)->store($invoice->refresh())])->save();
        }
    }

    public function transactionId(Model $record): int
    {
        return (int) $record->invoice()->value('transaction_id');
    }
}
