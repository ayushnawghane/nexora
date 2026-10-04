<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How the debentures were issued. */
enum Placement: string
{
    use HasOptions;

    case Private = 'private';
    case Public = 'public';
    case Rights = 'rights';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private placement',
            self::Public => 'Public issue',
            self::Rights => 'Rights issue',
        };
    }
}
