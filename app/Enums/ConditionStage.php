<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** Conditions precedent are met before the issue; conditions subsequent after it. */
enum ConditionStage: string
{
    use HasOptions;

    case Precedent = 'precedent';
    case Subsequent = 'subsequent';

    public function label(): string
    {
        return match ($this) {
            self::Precedent => 'Condition precedent (CP)',
            self::Subsequent => 'Condition subsequent (CS)',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Precedent => 'CP',
            self::Subsequent => 'CS',
        };
    }
}
