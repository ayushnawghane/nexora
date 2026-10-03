<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Indian-format rupee strings for emails and PDFs (the browser has its own in lib/format.js). */
final class Money
{
    /** "200000000.5" → "₹20,00,00,000.50" (lakh/crore grouping). */
    public static function format(?string $amount, string $symbol = '₹'): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $value = BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);
        $negative = $value->isNegative();
        [$whole, $paise] = explode('.', (string) $value->abs());

        $lastThree = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $grouped = $rest === '' ? $lastThree : preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$lastThree;

        return ($negative ? '-' : '').$symbol.$grouped.'.'.$paise;
    }

    /** "6500000.50" → "Rupees Sixty Five Lakh and Fifty Paise Only" (Indian numbering). */
    public static function inWords(string $amount): string
    {
        [$rupees, $paise] = explode('.', (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp)->abs());

        $text = 'Rupees '.(self::words((int) $rupees) ?: 'Zero');
        if ((int) $paise > 0) {
            $text .= ' and '.self::words((int) $paise).' Paise';
        }

        return $text.' Only';
    }

    private static function words(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        }

        foreach ([[10_000_000, 'Crore'], [100_000, 'Lakh'], [1_000, 'Thousand'], [100, 'Hundred']] as [$unit, $name]) {
            if ($n >= $unit) {
                return trim(self::words(intdiv($n, $unit)).' '.$name.' '.self::words($n % $unit));
            }
        }

        return '';
    }
}
