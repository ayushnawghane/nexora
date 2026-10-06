<?php

namespace App\Services\Billing;

use App\Enums\InvoiceKind;
use App\Models\NumberSequence;
use App\Support\FinancialYear;
use DateTimeInterface;

/**
 * Invoice numbers, one running sequence per kind and financial year (row-locked, so two people
 * issuing at once never get the same number; a rolled-back issue gives its number back).
 */
class InvoiceNumbers
{
    /**
     * @return array{number: string, financial_year: string, serial: int}
     */
    public function next(InvoiceKind $kind, DateTimeInterface $date): array
    {
        $fy = FinancialYear::compact($date);
        $serial = NumberSequence::next(self::key($kind, $fy));

        return ['number' => self::format($kind, $fy, $serial), 'financial_year' => $fy, 'serial' => $serial];
    }

    public static function key(InvoiceKind $kind, string $financialYear): string
    {
        return "invoice:{$kind->value}:{$financialYear}";
    }

    public static function format(InvoiceKind $kind, string $financialYear, int $serial): string
    {
        return strtr((string) config('billing.number_format'), [
            '{fy}' => $financialYear,
            '{code}' => $kind->code(),
            '{serial}' => str_pad((string) $serial, (int) config('billing.serial_digits'), '0', STR_PAD_LEFT),
        ]);
    }
}
