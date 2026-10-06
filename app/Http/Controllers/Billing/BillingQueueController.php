<?php

namespace App\Http\Controllers\Billing;

use App\Enums\DealStatus;
use App\Http\Controllers\Controller;
use App\Models\FeeSchedulePeriod;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fee periods ready to bill: not on any invoice, billed within the next queue_days days (or
 * already past their bill date), on deals that are still open. One row per deal.
 */
class BillingQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('billing.view');
        $term = trim((string) $request->input('search', ''));
        $due = fn (Builder $q) => $q->whereNull('fee_schedule_periods.invoice_id')
            ->whereDate('fee_schedule_periods.bill_date', '<=', today()->addDays((int) config('billing.queue_days')));

        // One page of deals, the longest-waiting first; then just their periods.
        $deals = Transaction::query()
            ->whereNotNull('deal_status')->whereNotIn('deal_status', self::closedStatuses())
            ->whereHas('schedulePeriods', $due)
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $t) => $t
                ->where('el_number', 'like', "%{$term}%")
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'like', "%{$term}%"))))
            ->withMin(['schedulePeriods as earliest_bill_date' => $due], 'fee_schedule_periods.bill_date')
            ->with(['company:id,name', 'billing:id,transaction_id'])
            ->orderBy('earliest_bill_date')->orderBy('id')
            ->paginate(20)->withQueryString();

        $periods = self::due()
            ->whereHas('feeLine', fn (Builder $q) => $q->whereIn('transaction_id', $deals->getCollection()->modelKeys()))
            ->with('feeLine:id,transaction_id,kind')
            ->orderBy('bill_date')->get()
            ->groupBy(fn (FeeSchedulePeriod $p) => $p->feeLine->transaction_id);

        return Inertia::render('Billing/Queue', [
            'deals' => $deals->through(function (Transaction $deal) use ($periods) {
                /** @var Collection<int, FeeSchedulePeriod> $rows */
                $rows = $periods->get($deal->id, collect());

                return [
                    'id' => $deal->ulid,
                    'el_number' => $deal->el_number,
                    'company' => $deal->company->name,
                    'deal_status_label' => $deal->deal_status?->label(),
                    'has_billing' => $deal->billing !== null,
                    'overdue' => $rows->contains(fn (FeeSchedulePeriod $p) => $p->bill_date->isBefore(today())),
                    'total' => (string) $rows->reduce(fn (BigDecimal $sum, FeeSchedulePeriod $p) => $sum->plus($p->amount), BigDecimal::zero()->toScale(2)),
                    'periods' => $rows->map(fn (FeeSchedulePeriod $p) => [
                        'id' => $p->id,
                        'fee' => $p->feeLine->kind->label(),
                        'from' => $p->from_date->toDateString(),
                        'to' => $p->to_date->toDateString(),
                        'bill_date' => $p->bill_date->toDateString(),
                        'amount' => $p->amount,
                    ])->values(),
                ];
            }),
            'queueDays' => (int) config('billing.queue_days'),
            'search' => $term,
            'canRaise' => $request->user()->can('billing.raise'),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function closedStatuses(): array
    {
        return array_values(array_map(fn (DealStatus $s) => $s->value, array_filter(DealStatus::cases(), fn (DealStatus $s) => $s->isFinal())));
    }

    /**
     * Unbilled periods whose bill date has come or is within the queue window, on open deals.
     *
     * @return Builder<FeeSchedulePeriod>
     */
    public static function due(): Builder
    {
        $closed = self::closedStatuses();

        return FeeSchedulePeriod::query()
            ->whereNull('invoice_id')
            ->whereDate('bill_date', '<=', today()->addDays((int) config('billing.queue_days')))
            ->whereHas('feeLine.transaction', fn (Builder $q) => $q->whereNotNull('deal_status')->whereNotIn('deal_status', $closed));
    }
}
