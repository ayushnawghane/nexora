<?php

namespace App\Services\Tax;

use RuntimeException;

class TaxNotConfigured extends RuntimeException
{
    public static function noRateOn(string $date): self
    {
        return new self("No GST rate is in force on {$date}. Add one in Tax settings.");
    }

    public static function noHomeState(): self
    {
        return new self('Beacon\'s GSTIN is not set, so CGST+SGST vs IGST can\'t be decided. Set it in Tax settings.');
    }
}
