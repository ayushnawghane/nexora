<?php

namespace App\Services\Fees;

use App\Enums\EscalationType;
use App\Enums\FeeFrequency;
use App\Enums\FeeTiming;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Builds a fee's billing schedule. Pure: same terms in, same periods out; no database.
 *
 * Rules (kept in line with the legacy DT schedules, see docs/PLAN.md M4):
 *  - Recurring fees are quoted per annum. Periods align to the Indian financial year (1 April):
 *    annual = Apr–Mar, half-yearly = Apr–Sep / Oct–Mar, quarterly = calendar quarters from April,
 *    monthly = calendar months.
 *  - A full period costs its share of the year (half-yearly = ½). A partial period (the first,
 *    the last, or one cut by an escalation) costs rate × days ÷ days in that financial year,
 *    counting both the first and last day; a financial year containing 29 February has 366 days.
 *  - Escalation raises the rate every N years from the start date; a period is split on the date
 *    the new rate takes effect. Percentage escalation compounds.
 *  - Billed on the period's first day when payable in advance, its last day when in arrears.
 *  - One-time fees are a single charge covering start to end.
 *  - Amounts are rounded half up to config('fees.rounding_scale') places.
 */
class FeeScheduleService
{
    public function __construct(private readonly int $scale) {}

    public static function fromConfig(): self
    {
        return new self((int) config('fees.rounding_scale', 0));
    }

    /**
     * @return list<SchedulePeriod>
     */
    public function generate(FeeTerms $terms): array
    {
        if ($terms->endDate->lt($terms->startDate)) {
            throw new InvalidArgumentException('The fee end date is before its start date.');
        }

        if ($terms->frequency === FeeFrequency::OneTime) {
            return [$this->period($terms, $terms->startDate, $terms->endDate, $this->round(BigDecimal::of($terms->annualAmount)), false)];
        }

        $months = (int) $terms->frequency->months();
        $periods = [];
        $cursor = $terms->startDate;

        while ($cursor->lte($terms->endDate)) {
            [$alignedStart, $alignedEnd] = $this->alignedPeriod($cursor, $months);
            $to = $alignedEnd->min($terms->endDate);

            $nextEscalation = $this->nextEscalationAfter($terms, $cursor);
            if ($nextEscalation !== null && $nextEscalation->lte($to)) {
                $to = $nextEscalation->subDay();
            }

            $rate = $this->rateOn($terms, $cursor);
            $full = $cursor->equalTo($alignedStart) && $to->equalTo($alignedEnd);
            $amount = $full
                ? $rate->multipliedBy($months)->dividedBy(12, $this->scale, RoundingMode::HalfUp)
                : $rate->multipliedBy($this->days($cursor, $to))->dividedBy($this->daysInFinancialYear($cursor), $this->scale, RoundingMode::HalfUp);

            $periods[] = $this->period($terms, $cursor, $to, $amount, ! $full, $rate);
            $cursor = $to->addDay();
        }

        return $periods;
    }

    /**
     * The billing period of the given length (in months) that contains the date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function alignedPeriod(CarbonImmutable $date, int $months): array
    {
        $fyStart = $this->financialYearStart($date);
        $monthsIntoYear = ($date->month - 4 + 12) % 12;
        $start = $fyStart->addMonthsNoOverflow(intdiv($monthsIntoYear, $months) * $months);

        return [$start, $start->addMonthsNoOverflow($months)->subDay()];
    }

    public function daysInFinancialYear(CarbonImmutable $date): int
    {
        $start = $this->financialYearStart($date);

        return $this->days($start, $start->addYear()->subDay());
    }

    public function financialYearLabel(CarbonImmutable $date): string
    {
        $year = $this->financialYearStart($date)->year;

        return sprintf('FY %d-%02d', $year, ($year + 1) % 100);
    }

    private function financialYearStart(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::create($date->month >= 4 ? $date->year : $date->year - 1, 4, 1);
    }

    /** Inclusive day count. */
    private function days(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) round($from->startOfDay()->diffInDays($to->startOfDay())) + 1;
    }

    private function escalates(FeeTerms $terms): bool
    {
        return $terms->escalationType !== EscalationType::None
            && $terms->escalationEveryYears !== null && $terms->escalationEveryYears > 0
            && $terms->escalationValue !== null;
    }

    /** First date after $date on which a new escalated rate takes effect. */
    private function nextEscalationAfter(FeeTerms $terms, CarbonImmutable $date): ?CarbonImmutable
    {
        if (! $this->escalates($terms)) {
            return null;
        }

        $step = 1;
        do {
            $candidate = $terms->startDate->addYearsNoOverflow($step * $terms->escalationEveryYears);
            $step++;
        } while ($candidate->lte($date));

        return $candidate;
    }

    private function rateOn(FeeTerms $terms, CarbonImmutable $date): BigDecimal
    {
        $rate = BigDecimal::of($terms->annualAmount);
        if (! $this->escalates($terms)) {
            return $rate;
        }

        $steps = 0;
        while ($terms->startDate->addYearsNoOverflow(($steps + 1) * $terms->escalationEveryYears)->lte($date)) {
            $steps++;
        }

        for ($i = 0; $i < $steps; $i++) {
            $rate = $terms->escalationType === EscalationType::Percent
                ? $rate->multipliedBy(BigDecimal::of((string) $terms->escalationValue)->dividedBy(100, 6, RoundingMode::HalfUp)->plus(1))->toScale(2, RoundingMode::HalfUp)
                : $rate->plus((string) $terms->escalationValue);
        }

        return $rate->toScale(2, RoundingMode::HalfUp);
    }

    private function period(FeeTerms $terms, CarbonImmutable $from, CarbonImmutable $to, BigDecimal $amount, bool $prorated, ?BigDecimal $rate = null): SchedulePeriod
    {
        return new SchedulePeriod(
            from: $from,
            to: $to,
            billDate: $terms->timing === FeeTiming::Advance ? $from : $to,
            days: $this->days($from, $to),
            daysInYear: $this->daysInFinancialYear($from),
            baseAmount: (string) ($rate ?? BigDecimal::of($terms->annualAmount))->toScale(2, RoundingMode::HalfUp),
            amount: (string) $this->round($amount)->toScale(2),
            financialYear: $this->financialYearLabel($from),
            prorated: $prorated,
        );
    }

    private function round(BigDecimal $amount): BigDecimal
    {
        return $amount->toScale($this->scale, RoundingMode::HalfUp);
    }
}
