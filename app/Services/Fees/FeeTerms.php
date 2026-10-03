<?php

namespace App\Services\Fees;

use App\Enums\EscalationType;
use App\Enums\FeeFrequency;
use App\Enums\FeeTiming;
use Carbon\CarbonImmutable;

/**
 * Everything the schedule depends on, as plain values. `annualAmount` is the fee per annum (or the
 * one-time fee), already resolved from a fixed amount or a percentage of the issue size.
 * `endDate` is the last day covered (inclusive), normally the maturity date.
 */
final readonly class FeeTerms
{
    public function __construct(
        public FeeFrequency $frequency,
        public FeeTiming $timing,
        public string $annualAmount,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public EscalationType $escalationType = EscalationType::None,
        public ?string $escalationValue = null,
        public ?int $escalationEveryYears = null,
    ) {}
}
