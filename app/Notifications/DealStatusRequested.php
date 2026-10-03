<?php

namespace App\Notifications;

use App\Models\DealStatusRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks Management / Accounts to approve a deal status change. The link opens the deal in Nexora
 * (sign-in and 2FA required); votes are only taken there.
 */
class DealStatusRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DealStatusRequest $statusRequest)
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

    public function toMail(User $notifiable): MailMessage
    {
        $request = $this->statusRequest->loadMissing(['transaction.company', 'requester']);
        $deal = $request->transaction;

        return (new MailMessage)
            ->subject("Status change approval: {$deal->company->name} ({$deal->el_number})")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$request->requester->name} has asked to move {$deal->company->name} ({$deal->el_number}) from {$request->from_status->label()} to {$request->to_status->label()}, effective {$request->effective_on->format('d M Y')}.")
            ->line("Reason: \"{$request->reason}\"")
            ->action('Review the request', route('deals.show', ['transaction' => $deal->ulid, 'tab' => 'status']));
    }
}
