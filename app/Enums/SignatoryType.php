<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Who signs for Beacon: one of its authorised signatories, or a POA holder outside Beacon. */
enum SignatoryType: string
{
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Beacon authorised signatory',
            self::External => 'POA holder',
        };
    }
}
