<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AddressType: string
{
    use HasOptions;

    case Registered = 'registered';
    case Billing = 'billing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered office',
            self::Billing => 'Billing',
            self::Other => 'Other',
        };
    }
}
