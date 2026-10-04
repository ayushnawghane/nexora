<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** One step in a registration's history. */
enum RegistrationAction: string
{
    use HasOptions;

    case Create = 'create';
    case Modify = 'modify';
    case Satisfy = 'satisfy';

    public function label(?RegistrationKind $kind = null): string
    {
        return match ($this) {
            self::Create => $kind === RegistrationKind::Pledge ? 'Pledged' : 'Registered',
            self::Modify => 'Modified',
            self::Satisfy => $kind === RegistrationKind::Pledge ? 'Released' : 'Satisfied',
        };
    }
}
