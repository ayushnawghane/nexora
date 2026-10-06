<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceLineKind;
use App\Enums\InvoiceStatus;
use App\Models\DealExpense;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\InvoiceTotals;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The maker's side of billing: drafting a proforma (fee periods, expenses, other fees), a
 * reimbursement bill (expenses only, no GST) or a credit note (part of a tax invoice), changing a
 * draft, and discarding it. A draft holds the periods and expenses it bills, so nobody else can
 * bill them meanwhile; a checker then issues it (IssueInvoice).
 */
class DraftInvoice
{
    use BillingSupport;

    public function __construct(private readonly InvoiceTotals $totals) {}

    /**
     * @param  list<int>  $periodIds
     * @param  list<string>  $expenseIds  ulids
     * @param  list<array{description: string, amount: string}>  $others
     */
    public function proforma(Transaction $deal, array $periodIds, array $expenseIds, array $others, ?string $notes, User $actor): Invoice
    {
        return DB::transaction(function () use ($deal, $periodIds, $expenseIds, $others, $notes, $actor) {
            $locked = $this->lockDeal($deal);
            $invoice = $locked->invoices()->create([
                ...$this->billedParty($locked),
                'kind' => InvoiceKind::Proforma,
                'status' => InvoiceStatus::Draft,
                'gst_applies' => true,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
            $this->fill($invoice, $locked, $periodIds, $expenseIds, $others);

            return $invoice;
        });
    }

    /**
     * @param  list<string>  $expenseIds  ulids
     */
    public function reimbursement(Transaction $deal, array $expenseIds, ?string $notes, User $actor): Invoice
    {
        return DB::transaction(function () use ($deal, $expenseIds, $notes, $actor) {
            $locked = $this->lockDeal($deal);
            $invoice = $locked->invoices()->create([
                ...$this->billedParty($locked),
                'kind' => InvoiceKind::Reimbursement,
                'status' => InvoiceStatus::Draft,
                'gst_applies' => false,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);
            $this->fill($invoice, $locked, [], $expenseIds, []);

            return $invoice;
        });
    }

    /**
     * Changes a draft's contents. Only its maker can, and a sent-back draft goes back for checking.
     *
     * @param  list<int>  $periodIds
     * @param  list<string>  $expenseIds
     * @param  list<array{description: string, amount: string}>  $others
     */
    public function revise(Invoice $invoice, array $periodIds, array $expenseIds, array $others, ?string $notes, User $actor): Invoice
    {
        return DB::transaction(function () use ($invoice, $periodIds, $expenseIds, $others, $notes, $actor) {
            $locked = $this->lockDraft($invoice, $actor);
            if ($locked->kind === InvoiceKind::CreditNote) {
                throw ValidationException::withMessages(['invoice' => 'Discard the credit note and draft it again to change it.']);
            }
            if ($locked->kind === InvoiceKind::Reimbursement && ($periodIds !== [] || $others !== [])) {
                throw ValidationException::withMessages(['periods' => 'A reimbursement bill carries expenses only.']);
            }
            $deal = $this->lockDeal($locked->transaction);

            $this->release($locked);
            $locked->lines()->delete();
            $locked->update([
                ...$this->billedParty($deal),
                'notes' => $notes,
                'returned_reason' => null,
                'returned_by' => null,
                'returned_at' => null,
            ]);
            $this->fill($locked, $deal, $periodIds, $expenseIds, $others);

            return $locked;
        });
    }

    /**
     * A credit note against an issued tax invoice: how much of each line to reduce (no more than
     * is left of it after other credit notes), with the reason. GST at the invoice's own rates.
     *
     * @param  array<int, string>  $amounts  tax invoice line id => amount to credit
     */
    public function creditNote(Invoice $taxInvoice, array $amounts, string $reason, User $actor): Invoice
    {
        return DB::transaction(function () use ($taxInvoice, $amounts, $reason, $actor) {
            /** @var Invoice $tax */
            $tax = Invoice::query()->whereKey($taxInvoice->id)->lockForUpdate()->firstOrFail();
            if ($tax->kind !== InvoiceKind::Tax || $tax->status !== InvoiceStatus::Issued) {
                throw ValidationException::withMessages(['invoice' => 'Credit notes can only be raised against an issued tax invoice.']);
            }

            $credited = $this->creditedSoFar($tax);
            $lines = $tax->lines()->get()->keyBy('id');
            $picked = collect($amounts)->filter(fn ($amount) => BigDecimal::of((string) $amount)->isPositive());
            if ($picked->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Enter the amount to credit on at least one line.']);
            }

            $note = Invoice::query()->create([
                'transaction_id' => $tax->transaction_id,
                'kind' => InvoiceKind::CreditNote,
                'status' => InvoiceStatus::Draft,
                'parent_id' => $tax->id,
                'billed_name' => $tax->billed_name,
                'billed_address' => $tax->billed_address,
                'billed_gstin' => $tax->billed_gstin,
                'place_of_supply_state_id' => $tax->place_of_supply_state_id,
                'sac' => $tax->sac,
                'gst_applies' => $tax->gst_applies,
                'period_from' => $tax->period_from,
                'period_to' => $tax->period_to,
                'notes' => $reason,
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ($picked as $lineId => $amount) {
                /** @var InvoiceLine|null $line */
                $line = $lines->get((int) $lineId);
                if ($line === null) {
                    throw ValidationException::withMessages(['lines' => 'A line isn\'t on this invoice.']);
                }
                $left = BigDecimal::of($line->amount)->minus($credited->get($line->id, '0'));
                if (BigDecimal::of((string) $amount)->isGreaterThan($left)) {
                    throw ValidationException::withMessages(["lines.{$line->id}" => "At most ₹{$left} of “{$line->description}” can still be credited."]);
                }
                $note->lines()->create([
                    'position' => ++$position,
                    'kind' => $line->kind,
                    'description' => $line->description,
                    'period_from' => $line->period_from,
                    'period_to' => $line->period_to,
                    'taxable' => $line->taxable,
                    'amount' => (string) BigDecimal::of((string) $amount)->toScale(2),
                    'credited_line_id' => $line->id,
                ]);
            }
            $note->update($this->totals->at($tax, $note->lines()->get()));

            return $note;
        });
    }

    /** Deletes a draft; whatever it billed can be billed again. */
    public function discard(Invoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor) {
            $locked = $this->lockDraft($invoice, $actor);
            $this->release($locked);
            $locked->delete();
        });
    }

    /**
     * Credited so far per tax invoice line, counting drafts too (so two drafts can't over-credit).
     *
     * @return Collection<int, string>
     */
    public function creditedSoFar(Invoice $tax): Collection
    {
        return InvoiceLine::query()
            ->whereIn('credited_line_id', $tax->lines()->select('id'))
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', InvoiceStatus::Cancelled))
            ->get(['credited_line_id', 'amount'])
            ->groupBy('credited_line_id')
            ->map(fn (Collection $rows) => (string) $rows->reduce(fn (BigDecimal $sum, InvoiceLine $l) => $sum->plus($l->amount), BigDecimal::zero()));
    }

    /**
     * Adds the lines and takes the periods and expenses, then works out the amounts (provisionally:
     * the GST is worked out again when the invoice is issued).
     *
     * @param  list<int>  $periodIds
     * @param  list<string>  $expenseIds
     * @param  list<array{description: string, amount: string}>  $others
     */
    private function fill(Invoice $invoice, Transaction $deal, array $periodIds, array $expenseIds, array $others): void
    {
        $periods = FeeSchedulePeriod::query()
            ->whereKey(array_values(array_unique($periodIds)))
            ->whereHas('feeLine', fn ($q) => $q->where('transaction_id', $deal->id))
            ->with('feeLine:id,kind')
            ->orderBy('from_date')->lockForUpdate()->get();
        if ($periods->count() !== count(array_unique($periodIds))) {
            throw ValidationException::withMessages(['periods' => 'A fee period isn\'t on this deal.']);
        }
        if ($periods->contains(fn (FeeSchedulePeriod $p) => $p->invoice_id !== null)) {
            throw ValidationException::withMessages(['periods' => 'A fee period has already been billed. Refresh and pick again.']);
        }

        $expenses = DealExpense::query()
            ->whereIn('ulid', array_values(array_unique($expenseIds)))
            ->where('transaction_id', $deal->id)->whereNull('removed_at')
            ->orderBy('incurred_on')->orderBy('id')->lockForUpdate()->get();
        if ($expenses->count() !== count(array_unique($expenseIds))) {
            throw ValidationException::withMessages(['expenses' => 'An expense isn\'t on this deal or was removed.']);
        }
        if ($expenses->contains(fn (DealExpense $e) => $e->invoice_id !== null)) {
            throw ValidationException::withMessages(['expenses' => 'An expense has already been billed. Refresh and pick again.']);
        }

        if ($periods->isEmpty() && $expenses->isEmpty() && $others === []) {
            throw ValidationException::withMessages(['periods' => 'Pick at least one fee period, expense or other fee to bill.']);
        }

        $position = 0;
        foreach ($periods as $period) {
            $invoice->lines()->create([
                'position' => ++$position,
                'kind' => InvoiceLineKind::forFee($period->feeLine->kind),
                'description' => InvoiceLineKind::forFee($period->feeLine->kind)->label(),
                'period_from' => $period->from_date,
                'period_to' => $period->to_date,
                'taxable' => true,
                'amount' => $period->amount,
                'fee_schedule_period_id' => $period->id,
            ]);
            $period->forceFill(['invoice_id' => $invoice->id])->save();
        }
        foreach ($others as $other) {
            $invoice->lines()->create([
                'position' => ++$position,
                'kind' => InvoiceLineKind::Other,
                'description' => trim($other['description']),
                'taxable' => true,
                'amount' => (string) BigDecimal::of((string) $other['amount'])->toScale(2),
            ]);
        }
        foreach ($expenses as $expense) {
            $invoice->lines()->create([
                'position' => ++$position,
                'kind' => InvoiceLineKind::Expense,
                'description' => $expense->description,
                'period_from' => $expense->incurred_on,
                'period_to' => $expense->incurred_on,
                'taxable' => false,
                'amount' => $expense->amount,
                'deal_expense_id' => $expense->id,
            ]);
            $expense->forceFill(['invoice_id' => $invoice->id])->save();
        }

        $invoice->update([
            'period_from' => $periods->min('from_date'),
            'period_to' => $periods->max('to_date'),
            ...$this->totals->on($invoice, $invoice->lines()->get(), today()),
        ]);
    }

    private function lockDeal(Transaction $deal): Transaction
    {
        /** @var Transaction $locked */
        $locked = Transaction::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
        if (! $locked->isDeal()) {
            throw ValidationException::withMessages(['invoice' => 'Only deals can be billed.']);
        }

        return $locked;
    }

    private function lockDraft(Invoice $invoice, User $actor): Invoice
    {
        /** @var Invoice $locked */
        $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => 'This invoice has been issued and can no longer change.']);
        }
        if ($locked->created_by !== $actor->id) {
            throw ValidationException::withMessages(['invoice' => 'Only the person who drafted it can change or discard it.']);
        }

        return $locked;
    }
}
