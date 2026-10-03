<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Asks an approver to vote. The links are signed for this approver and request and expire, so
 * they can't be forged or reused by someone else (the legacy links could be).
 */
class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public const LINK_DAYS = 7;

    public function __construct(public ApprovalRequest $approvalRequest)
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
        $request = $this->approvalRequest->loadMissing(['transaction.company', 'transaction.issueDetail', 'requester']);
        $transaction = $request->transaction;
        $link = URL::temporarySignedRoute('approvals.email', now()->addDays(self::LINK_DAYS), [
            'approvalRequest' => $request->ulid,
            'user' => $notifiable->ulid,
        ]);

        return (new MailMessage)
            ->subject("Approval needed: {$transaction->company->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$request->requester->name} has sent a debenture trustee transaction for {$transaction->company->name} for approval.")
            ->line('Issue size: '.Money::format((string) $transaction->issueDetail?->total_issue_size))
            ->action('Review and vote', $link)
            ->line('This link is for you only and expires in '.self::LINK_DAYS.' days. You can also vote in Nexora under Approvals.');
    }
}
