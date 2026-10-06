<?php

namespace App\Actions\Transactions;

use App\Enums\FeeAmountType;
use App\Models\FeeLine;
use App\Models\Transaction;
use App\Services\Fees\FeeScheduleService;
use App\Services\Fees\SchedulePeriod;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class RegenerateSchedule
{
    public function __construct(private readonly FeeScheduleService $schedules) {}

    /**
     * Re-resolves each fee's annual amount from the current issue size and rebuilds its periods.
     * The schedule must be verified again afterwards. Call inside the caller's DB transaction.
     */
    public function handle(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $issue = $transaction->issueDetail()->first();
            $transaction->markScheduleUnverified();
            $transaction->save();

            if ($issue === null) {
                return;
            }

            foreach ($transaction->feeLines()->get() as $line) {
                /** @var FeeLine $line */
                $line->annual_amount = $line->amount_type === FeeAmountType::Percent
                    ? (string) BigDecimal::of($issue->total_issue_size)->multipliedBy((string) $line->percent)->dividedBy(100, 2, RoundingMode::HalfUp)
                    : (string) $line->amount;
                $line->save();

                $periods = $this->schedules->generate($line->toTerms($issue));

                // Billed periods stay exactly as billed; only the periods after the last billed one
                // are rebuilt.
                $billed = $line->periods()->whereNotNull('invoice_id')->get();
                $lastBilled = $billed->max('to_date');
                if ($lastBilled !== null) {
                    $periods = array_values(array_filter($periods, fn (SchedulePeriod $p) => $p->from->isAfter($lastBilled)));
                }
                $offset = (int) $billed->max('sequence');

                $line->periods()->whereNull('invoice_id')->delete();
                $line->periods()->createMany(array_map(
                    fn (SchedulePeriod $p, int $i) => [...$p->toArray(), 'sequence' => $offset + $i + 1],
                    $periods,
                    array_keys($periods),
                ));
            }
        });
    }
}
