<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Debenture types an issue can be split into (legacy issue_details ncd/ocd/ccd/mld columns). */
enum Instrument: string
{
    use HasOptions;

    case Ncd = 'ncd';
    case Ocd = 'ocd';
    case Ccd = 'ccd';
    case Mld = 'mld';

    public function label(): string
    {
        return match ($this) {
            self::Ncd => 'Non-convertible debentures (NCD)',
            self::Ocd => 'Optionally convertible debentures (OCD)',
            self::Ccd => 'Compulsorily convertible debentures (CCD)',
            self::Mld => 'Market-linked debentures (MLD)',
        };
    }
}
