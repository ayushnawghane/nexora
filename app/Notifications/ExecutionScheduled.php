<?php

namespace App\Notifications;

use App\Models\DealExecution;
use App\Models\PoaHolder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the signatory which documents of a deal they sign, where and when. A Beacon signatory gets
 * a link to the deal; a POA holder outside Beacon can't sign in, so their email has no link.
 */
class ExecutionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $executionIds
     */
    public function __construct(public Transaction $deal, public array $executionIds, public User $scheduledBy)
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

    public function toMail(User|PoaHolder $notifiable): MailMessage
    {
        $deal = $this->deal->loadMissing('company');
        $executions = DealExecution::query()->whereKey($this->executionIds)->with('document')->orderBy('id')->get();
        /** @var DealExecution|null $first */
        $first = $executions->first();

        $mail = (new MailMessage)
            ->subject("Documents to sign: {$deal->company->name} ({$deal->el_number})")
            ->greeting("Hello {$notifiable->name},")
            ->line("You're the signatory for these documents of {$deal->company->name} ({$deal->el_number}):");

        foreach ($executions as $execution) {
            $mail->line("• {$execution->document->name}");
        }

        if ($first?->scheduled_at) {
            $mail->line("Execution: {$first->scheduled_at->format('d M Y, H:i')}, {$first->place}.");
        }
        $mail->line("Scheduled by {$this->scheduledBy->name}. Please contact them with any questions.");

        return $notifiable instanceof User
            ? $mail->action('Open the deal', route('deals.show', ['transaction' => $deal->ulid, 'tab' => 'execution']))
            : $mail;
    }
}
