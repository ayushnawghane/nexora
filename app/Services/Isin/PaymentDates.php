<?php

namespace App\Services\Isin;

use App\Enums\HolidayConvention;
use App\Enums\PaymentFrequency;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Works out an ISIN's due dates: from the first due date, every N months up to maturity, with the
 * maturity date itself always the last one. Months are added without overflow, so 31 Jan + 1 month
 * is 28/29 Feb and the next date goes back to the 31st. A date on a weekend moves to the previous or
 * next working day when the ISIN says so (no holiday calendar: weekends only).
 */
class PaymentDates
{
    /** Longest schedule generated: 50 years of monthly dates. */
    private const MAX_DATES = 600;

    /**
     * @return list<CarbonImmutable>
     */
    public function generate(CarbonImmutable $first, CarbonImmutable $maturity, PaymentFrequency $frequency, HolidayConvention $holidays): array
    {
        if ($first->isAfter($maturity)) {
            throw new InvalidArgumentException('The first due date is after maturity.');
        }

        $months = $frequency->months();
        $dates = [];
        if ($months === null) {
            $dates[] = $maturity;
        } else {
            for ($i = 0; ; $i++) {
                $date = $first->addMonthsNoOverflow($i * $months);
                if ($date->isAfter($maturity) || count($dates) >= self::MAX_DATES) {
                    break;
                }
                $dates[] = $date;
            }
            $last = end($dates);
            if ($last === false || $last->notEqualTo($maturity)) {
                $dates[] = $maturity;
            }
        }

        $adjusted = array_map(fn (CarbonImmutable $d) => $this->adjust($d, $holidays), $dates);

        return array_values(array_unique($adjusted, SORT_REGULAR));
    }

    public function adjust(CarbonImmutable $date, HolidayConvention $holidays): CarbonImmutable
    {
        return match ($holidays) {
            HolidayConvention::None => $date,
            HolidayConvention::Preceding => $date->isWeekend() ? $date->previousWeekday() : $date,
            HolidayConvention::Following => $date->isWeekend() ? $date->nextWeekday() : $date,
        };
    }
}
