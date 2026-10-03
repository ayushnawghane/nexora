<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeKind: string
{
    use HasOptions;

    /** One-time fee for accepting the trusteeship. */
    case Acceptance = 'acceptance';
    /** Recurring annual trusteeship fee. */
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Acceptance => 'Acceptance fee',
            self::Service => 'Service fee',
        };
    }

    /** The frequencies this kind of fee may use. */
    public function allowedFrequencies(): array
    {
        return match ($this) {
            self::Acceptance => [FeeFrequency::OneTime],
            self::Service => [FeeFrequency::Annual, FeeFrequency::HalfYearly, FeeFrequency::Quarterly, FeeFrequency::Monthly],
        };
    }
}
