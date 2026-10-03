<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The event the fee runs from; it's printed in the letter. The schedule uses the fee's start date. */
enum FeeStartReference: string
{
    use HasOptions;

    case ElDate = 'el_date';
    case DtaExecution = 'dta_execution';
    case AllotmentDate = 'allotment_date';
    case TrustDeedExecution = 'trust_deed_execution';
    case CustomDate = 'custom_date';

    public function label(): string
    {
        return match ($this) {
            self::ElDate => 'Engagement letter date',
            self::DtaExecution => 'DTA execution date',
            self::AllotmentDate => 'Deemed date of allotment',
            self::TrustDeedExecution => 'Trust deed execution date',
            self::CustomDate => 'Custom date',
        };
    }
}
