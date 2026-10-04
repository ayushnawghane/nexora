<?php

namespace App\Notifications;

use App\Models\IsinPayment;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Lists a deal's interest and principal payments falling due soon, and any overdue. */
class IsinPaymentsDue extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $paymentIds
     */
    public function __construct(public Transaction $deal, public array $paymentIds)
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
        $deal = $this->deal->loadMissing('company');
        $payments = IsinPayment::query()->whereKey($this->paymentIds)->with('isin:id,isin')->orderBy('due_on')->get();
        $overdue = $payments->filter(fn (IsinPayment $p) => $p->isOverdue())->count();

        $mail = (new MailMessage)
            ->subject(($overdue ? 'Overdue and upcoming' : 'Upcoming')." debenture payments: {$deal->company->name}")
            ->greeting('Hello,')
            ->line("Payments on {$deal->company->name} ({$deal->el_number}):");

        foreach ($payments as $payment) {
            $mail->line(sprintf('• %s · %s due %s%s', $payment->isin->isin, $payment->kind->label(), $payment->due_on->format('d M Y'), $payment->isOverdue() ? ' (overdue)' : ''));
        }

        return $mail
            ->line('Please confirm each payment with the issuer and record it in Nexora.')
            ->action('Open the deal', route('deals.show', ['transaction' => $deal->ulid, 'tab' => 'isin']));
    }
}
