<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Which team brought the deal in (legacy transaction.originated_by). */
enum Origin: string
{
    use HasOptions;

    case BusinessDevelopment = 'bd';
    case Operations = 'ops';

    public function label(): string
    {
        return match ($this) {
            self::BusinessDevelopment => 'Business development',
            self::Operations => 'Operations',
        };
    }
}
