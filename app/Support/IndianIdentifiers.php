<?php

namespace App\Support;

use App\Enums\CompanyClass;

/**
 * Format and checksum rules for Indian business identifiers (CIN, LLPIN, PAN, GSTIN).
 * Mirrored on the client in resources/js/lib/identifiers.js; keep the two in step.
 */
final class IndianIdentifiers
{
    public const CIN_PATTERN = '/^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/';

    public const LLPIN_PATTERN = '/^[A-Z]{3}-[0-9]{4}$/';

    public const PAN_PATTERN = '/^[A-Z]{3}[ABCEFGHJLPT][A-Z][0-9]{4}[A-Z]$/';

    /** Regular GSTIN: state code, PAN, entity number, the fixed "Z", check character. */
    public const GSTIN_PATTERN = '/^[0-9]{2}[A-Z]{3}[ABCEFGHJLPT][A-Z][0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    private const GSTIN_CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function normalise(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }

    public static function isCin(string $value): bool
    {
        return preg_match(self::CIN_PATTERN, $value) === 1;
    }

    public static function isLlpin(string $value): bool
    {
        return preg_match(self::LLPIN_PATTERN, $value) === 1;
    }

    public static function isPan(string $value): bool
    {
        return preg_match(self::PAN_PATTERN, $value) === 1;
    }

    /** The 4th PAN character is the holder type: C = company, F = firm/LLP, T = trust, P = person… */
    public static function panHolderType(string $pan): string
    {
        return $pan[3];
    }

    /** Year of incorporation embedded in a CIN (characters 9–12). */
    public static function cinYear(string $cin): int
    {
        return (int) substr($cin, 8, 4);
    }

    /** Company class from the CIN's ownership code (characters 13–15), or null when unrecognised. */
    public static function cinClass(string $cin): ?CompanyClass
    {
        return CompanyClass::fromCinCode(substr($cin, 12, 3));
    }

    public static function isListedCin(string $cin): bool
    {
        return $cin[0] === 'L';
    }

    public static function isGstin(string $value): bool
    {
        return preg_match(self::GSTIN_PATTERN, $value) === 1
            && self::gstinCheckCharacter(substr($value, 0, 14)) === $value[14];
    }

    /**
     * GSTN check character: each of the first 14 characters is valued 0–35, weighted 1,2,1,2…,
     * and the base-36 digits of each product are summed; the check value makes the sum a multiple of 36.
     */
    public static function gstinCheckCharacter(string $first14): string
    {
        $sum = 0;
        foreach (str_split($first14) as $i => $char) {
            $product = strpos(self::GSTIN_CHARSET, $char) * ($i % 2 === 0 ? 1 : 2);
            $sum += intdiv($product, 36) + $product % 36;
        }

        return self::GSTIN_CHARSET[(36 - $sum % 36) % 36];
    }

    public static function gstinStateCode(string $gstin): string
    {
        return substr($gstin, 0, 2);
    }

    public static function gstinPan(string $gstin): string
    {
        return substr($gstin, 2, 10);
    }
}
