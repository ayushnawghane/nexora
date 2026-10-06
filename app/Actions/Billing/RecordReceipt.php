<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money received against a proforma (before or after its tax invoice) or a reimbursement bill. The
 * amount plus the TDS deducted can't be more than is outstanding, and a receipt can't predate the
 * invoice.
 */
class RecordReceipt
{
    /**
     * @param  array{received_on: string, amount: string, tds_amount?: string|null, utr?: string|null, remark?: string|null}  $data
     */
    public function record(Invoice $invoice, array $data, User $actor): InvoiceReceipt
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isCollectable()) {
                throw ValidationException::withMessages(['amount' => 'Receipts are recorded against issued proformas and reimbursement bills.']);
            }
            if (CarbonImmutable::parse($data['received_on'])->isBefore($locked->invoice_date)) {
                throw ValidationException::withMessages(['received_on' => 'The date received can\'t be before the invoice date ('.$locked->invoice_date->format('d M Y').').']);
            }

            $amount = BigDecimal::of((string) $data['amount'])->toScale(2);
            $tds = BigDecimal::of((string) ($data['tds_amount'] ?? '0'))->toScale(2);
            if ($amount->plus($tds)->isZero()) {
                throw ValidationException::withMessages(['amount' => 'Enter the amount received or the TDS deducted.']);
            }
            $outstanding = BigDecimal::of($locked->outstanding());
            if ($amount->plus($tds)->isGreaterThan($outstanding)) {
                throw ValidationException::withMessages(['amount' => "Only ₹{$outstanding} is outstanding on this invoice."]);
            }

            $receipt = $locked->receipts()->create([
                'received_on' => $data['received_on'],
                'amount' => (string) $amount,
                'tds_amount' => (string) $tds,
                'utr' => $data['utr'] ?? null,
                'remark' => $data['remark'] ?? null,
                'created_by' => $actor->id,
            ]);
            $locked->recalculateBalance();

            return $receipt;
        });
    }

    /** Marks a wrong receipt reversed; the amount is outstanding again. */
    public function reverse(InvoiceReceipt $receipt, string $reason, User $actor): void
    {
        DB::transaction(function () use ($receipt, $reason, $actor) {
            // Invoice first, then receipt: the same order as record(), so the two never deadlock.
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($receipt->invoice_id)->lockForUpdate()->firstOrFail();
            /** @var InvoiceReceipt $locked */
            $locked = InvoiceReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($locked->reversed_at !== null) {
                throw ValidationException::withMessages(['reason' => 'This receipt has already been reversed.']);
            }
            $locked->update(['reversal_reason' => $reason, 'reversed_by' => $actor->id, 'reversed_at' => now()]);
            $invoice->recalculateBalance();
        });
    }
}
