<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CompanyCategory: string
{
    use HasOptions;

    case LimitedByShares = 'limited_by_shares';
    case LimitedByGuarantee = 'limited_by_guarantee';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::LimitedByShares => 'Company limited by shares',
            self::LimitedByGuarantee => 'Company limited by guarantee',
            self::Unlimited => 'Unlimited company',
        };
    }
}
