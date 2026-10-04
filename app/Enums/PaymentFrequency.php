<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How often interest or principal falls due (Stack codes 1–5). */
enum PaymentFrequency: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Annual = 'annual';
    case Bullet = 'bullet';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::HalfYearly => 'Half-yearly',
            self::Annual => 'Annually',
            self::Bullet => 'Bullet (at maturity)',
        };
    }

    /** Months between due dates; null for a bullet (one payment at maturity). */
    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::HalfYearly => 6,
            self::Annual => 12,
            self::Bullet => null,
        };
    }

    public static function fromStack(int $code): ?self
    {
        return match ($code) {
            1 => self::Monthly,
            2 => self::Quarterly,
            3 => self::HalfYearly,
            4 => self::Annual,
            5 => self::Bullet,
            default => null,
        };
    }
}
