<?php

namespace App\Notifications;

use App\Enums\ApprovalStatus;
use App\Enums\VoteDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalVote;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the person who submitted a transaction that the approvers have decided. */
class ApprovalDecided extends Notification implements ShouldQueue
{
    use Queueable;

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
        $request = $this->approvalRequest->loadMissing(['transaction.company', 'votes.user']);
        $company = $request->transaction->company->name;
        $approved = $request->status === ApprovalStatus::Approved;

        $mail = (new MailMessage)
            ->subject(($approved ? 'Approved: ' : 'Rejected: ').$company)
            ->greeting("Hello {$notifiable->name},")
            ->line($approved
                ? "The transaction for {$company} has been approved. The engagement letter can now be issued."
                : "The transaction for {$company} was rejected. Revise it and send it again.");

        $request->votes
            ->filter(fn (ApprovalVote $v) => $v->decision === VoteDecision::Reject)
            ->each(fn (ApprovalVote $v) => $mail->line("{$v->user->name}: \"{$v->comment}\""));

        return $mail->action('Open the transaction', route('transactions.show', $request->transaction->ulid));
    }
}
