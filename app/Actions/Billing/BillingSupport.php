<?php

namespace App\Actions\Billing;

use App\Models\CompanyContact;
use App\Models\DealExpense;
use App\Models\FeeSchedulePeriod;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Services\Billing\InvoiceRenderer;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What the billing actions share: who a deal is billed to, giving fee periods and expenses back
 * when an invoice stops billing them, PDFs written alongside the database work, and mailing.
 */
trait BillingSupport
{
    /** @var list<string> PDFs written during the current call (removed again if it fails) */
    private array $written = [];

    /**
     * Who the deal is billed to, as the invoice records it.
     *
     * @return array<string, mixed>
     */
    private function billedParty(Transaction $deal): array
    {
        $billing = $deal->billing()->with(['address.state', 'gstin'])->first();
        if ($billing === null) {
            throw ValidationException::withMessages(['billing' => 'Set who the deal is billed to (Billing tab) first.']);
        }
        $address = $billing->address;
        $company = $deal->company()->value('name');

        return [
            'billed_name' => $address->billing_name ?: $company,
            'billed_address' => collect([$address->line1, $address->line2, $address->city, $address->state?->name, $address->pincode])->filter()->implode(', '),
            'billed_gstin' => $billing->gstin?->gstin,
            'place_of_supply_state_id' => $billing->place_of_supply_state_id,
            'sac' => (string) config('billing.sac'),
        ];
    }

    /** The invoice no longer bills its fee periods and expenses; they can be billed again. */
    private function release(Invoice $invoice): void
    {
        FeeSchedulePeriod::query()->where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
        DealExpense::query()->where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
    }

    /**
     * Runs $work in a DB transaction; PDFs written through pdf() inside it are deleted if it fails.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function withPdfs(Closure $work): mixed
    {
        $this->written = [];

        try {
            return DB::transaction($work);
        } catch (Throwable $e) {
            $this->discardWritten();
            throw $e;
        } finally {
            $this->written = [];
        }
    }

    /** Deletes the PDFs written by the failed call. */
    private function discardWritten(): void
    {
        foreach ($this->written as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function pdf(Invoice $invoice): void
    {
        $invoice->unsetRelation('lines');
        $path = app(InvoiceRenderer::class)->store($invoice);
        $this->written[] = $path;
        $invoice->forceFill(['pdf_path' => $path])->save();
    }

    /**
     * Emails the invoice to the deal's billing contacts (queued after commit) and logs it.
     *
     * @return int how many people it went to
     */
    private function mail(Invoice $invoice, ?User $sender): int
    {
        $recipients = Transaction::query()->whereKey($invoice->transaction_id)->firstOrFail()
            ->billingContacts()->get()
            ->map(fn (CompanyContact $c) => $c->email)->filter()->unique()->values()->all();
        if ($recipients === []) {
            return 0;
        }

        $invoice->mails()->create(['recipients' => implode(', ', $recipients), 'sent_by' => $sender?->id]);
        Notification::route('mail', $recipients)->notify(new InvoiceIssued($invoice));

        return count($recipients);
    }
}
