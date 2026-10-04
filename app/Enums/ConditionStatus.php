<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A CP/CS item: pending until files are uploaded, then waiting for a check. The checker verifies it
 * (final) or sends it back. An item that doesn't apply to the deal is marked not applicable, with a reason.
 */
enum ConditionStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Verified = 'verified';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Awaiting check',
            self::Returned => 'Sent back',
            self::Verified => 'Verified',
            self::Waived => 'Not applicable',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Submitted => 'warning',
            self::Returned => 'danger',
            self::Verified => 'success',
            self::Waived => 'neutral',
        };
    }

    /** Whether files can still be added or removed. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Submitted, self::Returned], true);
    }
}
