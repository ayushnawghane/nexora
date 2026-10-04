<?php

namespace App\Actions\Isin;

use App\Enums\DealStatus;
use App\Enums\IsinPaymentStatus;
use App\Models\IsinPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\IsinPaymentsDue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Emails a deal's team about interest and principal falling due soon (config isin.reminder_days)
 * or recently overdue (config isin.reminder_overdue_days): one email per deal, to the deal's vertical team and its relationship manager. Each
 * payment is reminded about at most once a day, whether by the daily job or by hand.
 */
class SendPaymentReminders
{
    /**
     * The daily run over every open deal.
     *
     * @return int emails sent
     */
    public function daily(): int
    {
        $sent = 0;
        $this->duePayments()
            ->get()
            ->groupBy(fn (IsinPayment $p) => $p->isin->transaction_id)
            ->each(function (Collection $payments) use (&$sent) {
                $sent += $this->send($payments, null) ? 1 : 0;
            });

        return $sent;
    }

    /**
     * Someone on the deal sends the reminder now, for this deal's due payments.
     */
    public function now(Transaction $deal, User $actor): int
    {
        $payments = $this->duePayments()->whereHas('isin', fn (Builder $q) => $q->where('transaction_id', $deal->id))->get();
        if ($payments->isEmpty()) {
            throw ValidationException::withMessages(['reminder' => 'Nothing is due in the next '.config('isin.reminder_days').' days or overdue in the last '.config('isin.reminder_overdue_days').' days, or the team was already reminded today.']);
        }
        if (! $this->send($payments, $actor)) {
            throw ValidationException::withMessages(['reminder' => 'The deal has no team email or relationship manager email to send to.']);
        }

        return $payments->count();
    }

    /**
     * Payments still due within the reminder window (or overdue) on open deals, not reminded today.
     *
     * @return Builder<IsinPayment>
     */
    private function duePayments(): Builder
    {
        $closed = array_values(array_map(fn (DealStatus $s) => $s->value, array_filter(DealStatus::cases(), fn (DealStatus $s) => $s->isFinal())));

        return IsinPayment::query()
            ->where('status', IsinPaymentStatus::Due)
            ->whereDate('due_on', '<=', today()->addDays((int) config('isin.reminder_days')))
            ->whereDate('due_on', '>=', today()->subDays((int) config('isin.reminder_overdue_days')))
            ->whereDoesntHave('reminders', fn (Builder $q) => $q->whereDate('sent_on', today()))
            ->whereHas('isin.transaction', fn (Builder $q) => $q->whereNotNull('deal_status')->whereNotIn('deal_status', $closed))
            ->with(['isin.transaction' => fn ($q) => $q->with(['company:id,name', 'verticalTeam:id,name,email', 'relationshipManager:id,name,email'])])
            ->orderBy('due_on');
    }

    /**
     * @param  Collection<int, IsinPayment>  $payments  of one deal
     */
    private function send(Collection $payments, ?User $actor): bool
    {
        /** @var Transaction $deal */
        $deal = $payments->first()->isin->transaction;
        $recipients = collect([$deal->verticalTeam?->email, $deal->relationshipManager?->email])->filter()->unique()->values()->all();
        if ($recipients === []) {
            return false;
        }

        DB::transaction(function () use ($payments, $recipients, $actor) {
            foreach ($payments as $payment) {
                $payment->reminders()->create(['sent_on' => today(), 'recipients' => implode(', ', $recipients), 'sent_by' => $actor?->id]);
            }
        });
        Notification::route('mail', $recipients)->notify(new IsinPaymentsDue($deal, $payments->map(fn (IsinPayment $p) => $p->id)->values()->all()));

        return true;
    }
}
