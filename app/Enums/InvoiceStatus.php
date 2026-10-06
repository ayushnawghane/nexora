<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * A draft has no number yet and can still change. Issued documents never change: a proforma is
 * converted into a tax invoice, and a tax invoice is reduced by credit notes.
 */
enum InvoiceStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Issued = 'issued';
    case Converted = 'converted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Converted => 'Converted to tax invoice',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Issued => 'brand',
            self::Converted => 'success',
            self::Cancelled => 'neutral',
        };
    }

    /** Still bills what it carries (fee periods and expenses stay taken). */
    public function isLive(): bool
    {
        return $this !== self::Cancelled;
    }
}
