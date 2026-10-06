<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\InvoiceNumbers;
use App\Services\Billing\InvoiceTotals;
use App\Services\EInvoice\EInvoiceFailed;
use App\Services\EInvoice\EInvoiceGateway;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The checker's side of billing: issuing a draft (someone other than its maker), sending it back,
 * converting a proforma into the tax invoice, cancelling, and emailing an invoice again.
 * Numbering, the GST worked out on the day, the IRN, the PDF and the database all succeed together
 * or not at all; the email goes out after commit.
 */
class IssueInvoice
{
    use BillingSupport;

    public function __construct(
        private readonly InvoiceTotals $totals,
        private readonly InvoiceNumbers $numbers,
        private readonly EInvoiceGateway $einvoice,
    ) {}

    /**
     * @return array{invoice: Invoice, emailed: int}
     */
    public function issue(Invoice $invoice, User $actor): array
    {
        $issued = $this->withPdfs(function () use ($invoice, $actor) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => 'This has already been issued.']);
            }
            if ($locked->created_by === $actor->id) {
                throw ValidationException::withMessages(['invoice' => 'Someone other than its maker has to issue it.']);
            }
            $today = CarbonImmutable::today();

            if ($locked->kind === InvoiceKind::CreditNote) {
                $tax = Invoice::query()->whereKey($locked->parent_id)->lockForUpdate()->firstOrFail();
                if ($tax->status !== InvoiceStatus::Issued) {
                    throw ValidationException::withMessages(['invoice' => 'The tax invoice it reduces is no longer issued.']);
                }
                $locked->fill($this->totals->at($tax, $locked->lines()->get()));
            } else {
                $deal = Transaction::query()->whereKey($locked->transaction_id)->lockForUpdate()->firstOrFail();
                // Who is billed may have changed since the draft: the invoice records it as it is now.
                $locked->fill($this->billedParty($deal));
                $locked->fill($this->totals->on($locked, $locked->lines()->get(), $today));
            }

            $this->number($locked, $today, $actor);
            $this->register($locked);
            $locked->save();
            $locked->recalculateBalance();
            if ($locked->kind === InvoiceKind::CreditNote) {
                $this->rebalanceProformaOf($locked);
            }
            $this->pdf($locked);

            return $locked;
        });

        return ['invoice' => $issued, 'emailed' => $this->mail($issued, null)];
    }

    /** Back to its maker with the reason; it stays a draft (and keeps what it bills). */
    public function sendBack(Invoice $invoice, string $reason, User $actor): Invoice
    {
        /** @var Invoice $locked */
        $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => 'Only drafts can be sent back.']);
        }
        if ($locked->created_by === $actor->id) {
            throw ValidationException::withMessages(['invoice' => 'You drafted this: change it or discard it instead.']);
        }
        $locked->update(['returned_reason' => $reason, 'returned_by' => $actor->id, 'returned_at' => now()]);

        return $locked;
    }

    /**
     * Issues the tax invoice for a proforma (usually once the client has paid): same lines and
     * party, GST at today's rate, its own number and IRN. The proforma is then converted.
     *
     * @return array{invoice: Invoice, emailed: int}
     */
    public function convert(Invoice $proforma, User $actor): array
    {
        $tax = $this->withPdfs(function () use ($proforma, $actor) {
            /** @var Invoice $source */
            $source = Invoice::query()->whereKey($proforma->id)->lockForUpdate()->firstOrFail();
            if ($source->kind !== InvoiceKind::Proforma || $source->status !== InvoiceStatus::Issued) {
                throw ValidationException::withMessages(['invoice' => $source->status === InvoiceStatus::Converted
                    ? 'This proforma has already been converted.'
                    : 'Only an issued proforma can be converted to a tax invoice.']);
            }
            $today = CarbonImmutable::today();

            $tax = Invoice::query()->create([
                ...$source->only(['transaction_id', 'billed_name', 'billed_address', 'billed_gstin', 'place_of_supply_state_id', 'sac', 'gst_applies', 'period_from', 'period_to', 'notes']),
                'kind' => InvoiceKind::Tax,
                'status' => InvoiceStatus::Draft,
                'parent_id' => $source->id,
                'created_by' => $actor->id,
            ]);
            foreach ($source->lines()->get() as $line) {
                $tax->lines()->create($line->only(['position', 'kind', 'description', 'period_from', 'period_to', 'taxable', 'amount', 'fee_schedule_period_id', 'deal_expense_id']));
            }
            $tax->fill($this->totals->on($tax, $tax->lines()->get(), $today));

            $this->number($tax, $today, $actor);
            $this->register($tax);
            $tax->save();
            $source->update(['status' => InvoiceStatus::Converted]);
            $this->pdf($tax);

            return $tax;
        });

        return ['invoice' => $tax, 'emailed' => $this->mail($tax, null)];
    }

    /**
     * Cancels an issued document. A proforma or reimbursement bill gives back what it billed. A tax
     * invoice or credit note can only be cancelled within the IRN window (GST rules); after that a
     * credit note is the way. Nothing with money received (or credited) against it can be cancelled.
     */
    public function cancel(Invoice $invoice, string $reason, User $actor): Invoice
    {
        $cancelled = $this->withPdfs(function () use ($invoice, $reason, $actor) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== InvoiceStatus::Issued) {
                throw ValidationException::withMessages(['invoice' => match ($locked->status) {
                    InvoiceStatus::Draft => 'Drafts are discarded, not cancelled.',
                    InvoiceStatus::Converted => 'Cancel the tax invoice made from this proforma first.',
                    default => 'This is already cancelled.',
                }]);
            }
            if ($locked->receipts()->whereNull('reversed_at')->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Money has been recorded against it. Reverse the receipts first.']);
            }
            if ($locked->children()->where('status', '!=', InvoiceStatus::Cancelled)->exists()) {
                throw ValidationException::withMessages(['invoice' => $locked->kind === InvoiceKind::Tax
                    ? 'It has credit notes. Cancel or discard them first.'
                    : 'Cancel the tax invoice made from it first.']);
            }

            if ($locked->kind->needsIrn()) {
                $registeredAt = $locked->ack_at ?? $locked->issued_at;
                $hours = (int) config('billing.irn_cancel_hours');
                if ($registeredAt === null || $registeredAt->copy()->addHours($hours)->isPast()) {
                    throw ValidationException::withMessages(['invoice' => "A {$locked->kind->shortLabel()} can only be cancelled within {$hours} hours of issue. Raise a credit note instead."]);
                }
                if ($locked->irn) {
                    try {
                        $this->einvoice->cancel($locked, $reason);
                    } catch (EInvoiceFailed $e) {
                        throw ValidationException::withMessages(['invoice' => "The e-invoice portal didn't cancel the IRN: {$e->getMessage()}"]);
                    }
                    $locked->irn_cancelled_at = now();
                }
            }

            $locked->fill(['status' => InvoiceStatus::Cancelled, 'cancel_reason' => $reason, 'cancelled_by' => $actor->id, 'cancelled_at' => now()])->save();
            match ($locked->kind) {
                InvoiceKind::Proforma, InvoiceKind::Reimbursement => $this->release($locked),
                // The proforma is live again: it can be converted again, or cancelled.
                InvoiceKind::Tax => Invoice::query()->whereKey($locked->parent_id)->where('status', InvoiceStatus::Converted)->update(['status' => InvoiceStatus::Issued]),
                InvoiceKind::CreditNote => $this->rebalanceProformaOf($locked),
            };
            $locked->recalculateBalance();
            $this->pdf($locked);

            return $locked;
        });

        $this->mail($cancelled, null);

        return $cancelled;
    }

    /** Emails an issued (or cancelled) invoice again. */
    public function resend(Invoice $invoice, User $actor): int
    {
        if ($invoice->status === InvoiceStatus::Draft || $invoice->number === null) {
            throw ValidationException::withMessages(['invoice' => 'Drafts aren\'t emailed.']);
        }
        $sent = $this->mail($invoice, $actor);
        if ($sent === 0) {
            throw ValidationException::withMessages(['invoice' => 'The deal has no billing contact with an email (Billing tab).']);
        }

        return $sent;
    }

    /** A credit note reduces what the proforma behind its tax invoice is owed. */
    private function rebalanceProformaOf(Invoice $creditNote): void
    {
        $proformaId = Invoice::query()->whereKey($creditNote->parent_id)->value('parent_id');
        if ($proformaId !== null) {
            Invoice::query()->whereKey($proformaId)->firstOrFail()->recalculateBalance();
        }
    }

    private function number(Invoice $invoice, CarbonImmutable $date, User $actor): void
    {
        if (BigDecimal::of($invoice->total)->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages(['invoice' => 'The total must be more than zero.']);
        }
        $invoice->fill([
            ...$this->numbers->next($invoice->kind, $date),
            'status' => InvoiceStatus::Issued,
            'invoice_date' => Carbon::parse($date->toDateString()),
            'issued_by' => $actor->id,
            'issued_at' => now(),
            'returned_reason' => null,
            'returned_by' => null,
            'returned_at' => null,
        ]);
    }

    /** Tax invoices and credit notes billed to a GSTIN get their IRN before anything is saved. */
    private function register(Invoice $invoice): void
    {
        if (! $invoice->kind->needsIrn() || $invoice->billed_gstin === null || ! $invoice->gst_applies) {
            return;
        }
        $invoice->save();
        $invoice->load('lines');

        try {
            $irn = $this->einvoice->register($invoice);
        } catch (EInvoiceFailed $e) {
            throw ValidationException::withMessages(['invoice' => "The e-invoice portal didn't accept it, so nothing was issued: {$e->getMessage()}"]);
        }
        if ($irn) {
            $invoice->fill(['irn' => $irn->irn, 'ack_no' => $irn->ackNo, 'ack_at' => $irn->ackAt, 'signed_qr' => $irn->signedQr]);
        }
    }
}
