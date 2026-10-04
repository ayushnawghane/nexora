<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** The first allotment under an ISIN, or a further tranche (re-issue). */
enum AllotmentKind: string
{
    use HasOptions;

    case Initial = 'initial';
    case Additional = 'additional';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial allotment',
            self::Additional => 'Additional allotment',
        };
    }
}
