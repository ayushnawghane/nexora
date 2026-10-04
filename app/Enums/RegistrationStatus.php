<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A registration is in force until it's satisfied (for a pledge: released). */
enum RegistrationStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Satisfied = 'satisfied';

    public function label(?RegistrationKind $kind = null): string
    {
        return match ($this) {
            self::Active => $kind === RegistrationKind::Pledge ? 'Pledged' : 'Registered',
            self::Satisfied => $kind === RegistrationKind::Pledge ? 'Released' : 'Satisfied',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Satisfied => 'neutral',
        };
    }
}
