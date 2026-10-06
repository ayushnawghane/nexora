<?php

namespace App\GodMode\Editors;

use App\GodMode\Editor;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Corrects a receipt, including a reversed one: date, amounts, UTR and remark. The invoice's
 * outstanding amount is worked out again; a correction can't settle more than the invoice is for.
 *
 * @extends Editor<InvoiceReceipt>
 */
class InvoiceReceiptEditor extends Editor
{
    public function key(): string
    {
        return 'invoice-receipt';
    }

    public function label(): string
    {
        return 'Receipt';
    }

    public function modelClass(): string
    {
        return InvoiceReceipt::class;
    }

    public function values(Model $record): array
    {
        return [
            'received_on' => $record->received_on->toDateString(),
            'amount' => $record->amount,
            'tds_amount' => $record->tds_amount,
            'utr' => $record->utr,
            'remark' => $record->remark,
        ];
    }

    public function fields(Model $record): array
    {
        return [
            ['name' => 'received_on', 'label' => 'Date received', 'type' => 'date', 'required' => true],
            ['name' => 'amount', 'label' => 'Amount received (₹)', 'type' => 'number', 'required' => true],
            ['name' => 'tds_amount', 'label' => 'TDS (₹)', 'type' => 'number', 'required' => true],
            ['name' => 'utr', 'label' => 'UTR / reference', 'type' => 'text'],
            ['name' => 'remark', 'label' => 'Remark', 'type' => 'textarea'],
        ];
    }

    public function validate(Model $record, array $input, User $actor): array
    {
        $input = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $input);
        $money = ['required', 'numeric', 'min:0', 'max:9999999999999999', 'decimal:0,2'];
        $validated = array_merge(['utr' => null, 'remark' => null], Validator::make($input, [
            'received_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'amount' => $money,
            'tds_amount' => $money,
            'utr' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ])->validate());

        if ($record->reversed_at === null) {
            $invoice = $record->invoice()->firstOrFail();
            $others = $invoice->receipts()->whereNull('reversed_at')->whereKeyNot($record->id)->get();
            $room = BigDecimal::of($invoice->total)->minus($invoice->creditedTotal())
                ->minus($others->reduce(fn (BigDecimal $s, InvoiceReceipt $r) => $s->plus($r->amount)->plus($r->tds_amount), BigDecimal::zero()));
            if (BigDecimal::of((string) $validated['amount'])->plus((string) $validated['tds_amount'])->isGreaterThan($room)) {
                throw ValidationException::withMessages(['amount' => "With the other receipts, at most ₹{$room} can be settled on this invoice."]);
            }
        }

        return $validated;
    }

    public function apply(Model $record, array $validated, User $actor): void
    {
        $record->update($validated);
        Invoice::query()->whereKey($record->invoice_id)->firstOrFail()->recalculateBalance();
    }

    public function transactionId(Model $record): int
    {
        return (int) $record->invoice()->value('transaction_id');
    }
}
