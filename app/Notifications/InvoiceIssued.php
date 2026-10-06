<?php

namespace App\Notifications;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/** Sends an issued invoice (or the notice that it was cancelled) to the client, with the PDF. */
class InvoiceIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing('transaction.company');
        $deal = $invoice->transaction;
        $cancelled = $invoice->status === InvoiceStatus::Cancelled;

        $mail = (new MailMessage)
            ->subject(($cancelled ? 'Cancelled: ' : '')."{$invoice->kind->label()} {$invoice->number} · {$deal->company->name}")
            ->greeting('Dear Sir / Madam,');

        if ($cancelled) {
            $mail->line("{$invoice->kind->label()} {$invoice->number} dated {$invoice->invoice_date?->format('d M Y')} has been cancelled.")
                ->line("Reason: {$invoice->cancel_reason}");
        } else {
            $mail->line("Please find attached our {$invoice->kind->label()} {$invoice->number} dated {$invoice->invoice_date?->format('d M Y')} for {$deal->el_number}, for ".Money::format($invoice->total, 'INR ').'.');
            if ($invoice->kind === InvoiceKind::Proforma) {
                $mail->line('Please pay against this proforma; the tax invoice follows on receipt of payment.');
            }
        }

        $mail->salutation('Regards, '.config('billing.issuer.name'));

        if ($invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path)) {
            $mail->attachData(Storage::disk('local')->get($invoice->pdf_path), str_replace('/', '-', (string) $invoice->number).'.pdf', ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
