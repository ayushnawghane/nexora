<?php

use App\Enums\HolidayConvention;
use App\Enums\PaymentFrequency;
use App\Services\Isin\PaymentDates;
use App\Support\IndianIdentifiers;
use Carbon\CarbonImmutable;

function dueDates(string $first, string $maturity, PaymentFrequency $frequency, HolidayConvention $holidays = HolidayConvention::None): array
{
    return array_map(
        fn (CarbonImmutable $d) => $d->toDateString(),
        (new PaymentDates)->generate(CarbonImmutable::parse($first), CarbonImmutable::parse($maturity), $frequency, $holidays),
    );
}

test('dates run every period from the first date, with maturity always last', function () {
    expect(dueDates('2025-09-15', '2026-06-15', PaymentFrequency::Quarterly))
        ->toBe(['2025-09-15', '2025-12-15', '2026-03-15', '2026-06-15'])
        // A maturity off the cycle is added as a final, broken period.
        ->and(dueDates('2025-09-15', '2026-05-10', PaymentFrequency::Quarterly))
        ->toBe(['2025-09-15', '2025-12-15', '2026-03-15', '2026-05-10'])
        ->and(dueDates('2025-09-15', '2027-09-15', PaymentFrequency::Annual))
        ->toBe(['2025-09-15', '2026-09-15', '2027-09-15']);
});

test('a bullet is one payment at maturity', function () {
    expect(dueDates('2025-09-15', '2028-06-15', PaymentFrequency::Bullet))->toBe(['2028-06-15']);
});

test('month ends stay on the month end without drifting', function () {
    expect(dueDates('2025-01-31', '2025-05-31', PaymentFrequency::Monthly))
        ->toBe(['2025-01-31', '2025-02-28', '2025-03-31', '2025-04-30', '2025-05-31']);
});

test('weekend dates move to the previous or next working day when the ISIN says so', function () {
    // 15 Nov 2025 is a Saturday.
    expect(dueDates('2025-08-15', '2025-11-15', PaymentFrequency::Quarterly))->toBe(['2025-08-15', '2025-11-15'])
        ->and(dueDates('2025-08-15', '2025-11-15', PaymentFrequency::Quarterly, HolidayConvention::Preceding))->toBe(['2025-08-15', '2025-11-14'])
        ->and(dueDates('2025-08-15', '2025-11-15', PaymentFrequency::Quarterly, HolidayConvention::Following))->toBe(['2025-08-15', '2025-11-17']);
});

test('a first date after maturity is refused', function () {
    dueDates('2026-01-01', '2025-12-31', PaymentFrequency::Monthly);
})->throws(InvalidArgumentException::class);

test('ISINs pass only with a valid check digit', function (string $isin, bool $valid) {
    expect(IndianIdentifiers::isIsin($isin))->toBe($valid);
})->with([
    'equity' => ['INE002A01018', true],
    'debenture' => ['INE471X07014', true],
    'wrong check digit' => ['INE002A01019', false],
    'not Indian' => ['US0378331005', false],
    'lowercase' => ['ine002a01018', false],
    'too short' => ['INE002A0101', false],
]);
