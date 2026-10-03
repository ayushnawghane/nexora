<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** A job sheet entry: the maker submits it, a different person verifies it or sends it back. */
enum JobSheetStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case Returned = 'returned';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Awaiting check',
            self::Returned => 'Sent back',
            self::Verified => 'Verified',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Returned => 'danger',
            self::Verified => 'success',
        };
    }
}
