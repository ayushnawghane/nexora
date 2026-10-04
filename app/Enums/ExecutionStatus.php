<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A document in execution: waiting to be scheduled, scheduled with a signatory, executed (copy
 * uploaded, waiting for a check), sent back, or verified (final).
 */
enum ExecutionStatus: string
{
    use HasOptions;

    case ToSchedule = 'to_schedule';
    case Scheduled = 'scheduled';
    case Executed = 'executed';
    case Returned = 'returned';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::ToSchedule => 'To schedule',
            self::Scheduled => 'Scheduled',
            self::Executed => 'Awaiting check',
            self::Returned => 'Sent back',
            self::Verified => 'Verified',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ToSchedule => 'neutral',
            self::Scheduled => 'warning',
            self::Executed => 'warning',
            self::Returned => 'danger',
            self::Verified => 'success',
        };
    }

    /** Whether the schedule (place, time, signatory) can still change. */
    public function canSchedule(): bool
    {
        return in_array($this, [self::ToSchedule, self::Scheduled], true);
    }

    /** Whether the executed copy can be uploaded now. */
    public function canRecord(): bool
    {
        return in_array($this, [self::Scheduled, self::Returned], true);
    }
}
