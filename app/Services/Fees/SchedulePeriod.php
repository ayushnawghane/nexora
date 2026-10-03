<?php

namespace App\Services\Fees;

use Carbon\CarbonImmutable;

/** One billable period. Amounts are decimal strings; `baseAmount` is the per-annum rate in force. */
final readonly class SchedulePeriod
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public CarbonImmutable $billDate,
        public int $days,
        public int $daysInYear,
        public string $baseAmount,
        public string $amount,
        public string $financialYear,
        public bool $prorated,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from_date' => $this->from->toDateString(),
            'to_date' => $this->to->toDateString(),
            'bill_date' => $this->billDate->toDateString(),
            'days' => $this->days,
            'days_in_year' => $this->daysInYear,
            'base_amount' => $this->baseAmount,
            'amount' => $this->amount,
            'financial_year' => $this->financialYear,
            'prorated' => $this->prorated,
        ];
    }
}
