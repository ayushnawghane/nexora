<?php

namespace App\Notifications;

use App\Enums\StatusRequestState;
use App\Enums\VoteDecision;
use App\Models\DealStatusRequest;
use App\Models\DealStatusVote;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the person who asked for a deal status change how it was decided. */
class DealStatusDecided extends Notification implements ShouldQueue
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
        $request = $this->statusRequest->loadMissing(['transaction.company', 'votes.user']);
        $company = $request->transaction->company->name;
        $approved = $request->status === StatusRequestState::Approved;

        $mail = (new MailMessage)
            ->subject(($approved ? 'Status change approved: ' : 'Status change rejected: ').$company)
            ->greeting("Hello {$notifiable->name},")
            ->line($approved
                ? "{$company} is now {$request->to_status->label()}."
                : "The request to move {$company} to {$request->to_status->label()} was rejected.");

        $request->votes
            ->filter(fn (DealStatusVote $v) => $v->decision === VoteDecision::Reject)
            ->each(fn (DealStatusVote $v) => $mail->line("{$v->user->name} ({$v->team->label()}): \"{$v->comment}\""));

        return $mail->action('Open the deal', route('deals.show', ['transaction' => $request->transaction->ulid, 'tab' => 'status']));
    }
}
