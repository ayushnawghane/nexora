<?php

use App\Enums\EscalationType;
use App\Enums\FeeFrequency;
use App\Enums\FeeTiming;
use App\Services\Fees\FeeScheduleService;
use App\Services\Fees\FeeTerms;
use Carbon\CarbonImmutable;

function terms(array $overrides = []): FeeTerms
{
    $d = fn (string $date) => CarbonImmutable::parse($date);

    return new FeeTerms(
        frequency: $overrides['frequency'] ?? FeeFrequency::Annual,
        timing: $overrides['timing'] ?? FeeTiming::Advance,
        annualAmount: $overrides['amount'] ?? '65000',
        startDate: $d($overrides['start'] ?? '2025-09-16'),
        endDate: $d($overrides['end'] ?? '2028-09-15'),
        escalationType: $overrides['escalation'] ?? EscalationType::None,
        escalationValue: $overrides['escalationValue'] ?? null,
        escalationEveryYears: $overrides['every'] ?? null,
    );
}

function schedule(array $overrides = [], int $scale = 0): array
{
    return array_map(fn ($p) => $p->toArray(), (new FeeScheduleService($scale))->generate(terms($overrides)));
}

test('legacy DT deal BTL/DEB/EL/25-26/323: annual, in advance, first period pro-rated to 31 March', function () {
    $rows = schedule();

    expect($rows[0])->toMatchArray([
        'from_date' => '2025-09-16', 'to_date' => '2026-03-31', 'bill_date' => '2025-09-16',
        'days' => 197, 'days_in_year' => 365, 'amount' => '35082.00', 'financial_year' => 'FY 2025-26', 'prorated' => true,
    ])->and($rows[1])->toMatchArray([
        'from_date' => '2026-04-01', 'to_date' => '2027-03-31', 'amount' => '65000.00', 'prorated' => false,
    ])->and($rows[2])->toMatchArray([
        'from_date' => '2027-04-01', 'to_date' => '2028-03-31', 'days_in_year' => 366, 'amount' => '65000.00', 'prorated' => false,
    ])->and(end($rows))->toMatchArray([
        // Last period runs to maturity and is pro-rated on the leap-free FY 2028-29.
        'from_date' => '2028-04-01', 'to_date' => '2028-09-15', 'days' => 168, 'days_in_year' => 365, 'amount' => '29918.00',
    ])->and($rows)->toHaveCount(4);
});

test('partial first periods match legacy DT schedules to the rupee', function (string $start, string $amount, int $days, string $expected) {
    $first = schedule(['start' => $start, 'amount' => $amount, 'end' => '2030-03-31'])[0];

    expect($first['days'])->toBe($days)->and($first['amount'])->toBe($expected);
})->with([
    'EL/26-27/4' => ['2026-04-02', '200000', 364, '199452.00'],
    'EL/25-26/661' => ['2026-07-01', '25000', 274, '18767.00'],
    'EL/25-26/691' => ['2026-02-20', '180000', 40, '19726.00'],
    'EL/25-26/445' => ['2025-11-21', '375000', 131, '134589.00'],
    'EL/25-26/272' => ['2025-09-12', '420714', 201, '231681.00'],
    'EL/25-26/112' => ['2025-06-11', '120000', 294, '96658.00'],
]);

test('a financial year containing 29 February has 366 days', function () {
    $first = schedule(['start' => '2024-03-07', 'amount' => '20000', 'end' => '2026-03-31'])[0];

    expect($first)->toMatchArray(['days' => 25, 'days_in_year' => 366, 'amount' => '1366.00']);
});

test('half-yearly, quarterly and monthly periods align to the financial year', function () {
    $half = schedule(['frequency' => FeeFrequency::HalfYearly, 'start' => '2025-04-01', 'end' => '2026-03-31', 'amount' => '100000']);
    expect(array_column($half, 'to_date'))->toBe(['2025-09-30', '2026-03-31'])
        ->and(array_column($half, 'amount'))->toBe(['50000.00', '50000.00']);

    $quarter = schedule(['frequency' => FeeFrequency::Quarterly, 'start' => '2025-05-15', 'end' => '2026-01-10', 'amount' => '120000']);
    expect(array_column($quarter, 'from_date'))->toBe(['2025-05-15', '2025-07-01', '2025-10-01', '2026-01-01'])
        ->and($quarter[0]['amount'])->toBe('15452.00') // 120000 × 47 / 365
        ->and($quarter[1]['amount'])->toBe('30000.00')
        ->and($quarter[3]['amount'])->toBe('3288.00'); // 120000 × 10 / 365

    $monthly = schedule(['frequency' => FeeFrequency::Monthly, 'start' => '2026-01-01', 'end' => '2026-03-31', 'amount' => '120000']);
    expect(array_column($monthly, 'amount'))->toBe(['10000.00', '10000.00', '10000.00']);
});

test('in arrears bills on the last day of each period', function () {
    $rows = schedule(['timing' => FeeTiming::Arrears]);

    expect($rows[0]['bill_date'])->toBe('2026-03-31')->and($rows[1]['bill_date'])->toBe('2027-03-31');
});

test('percentage escalation compounds every N years and splits the period it starts in', function () {
    $rows = schedule([
        'start' => '2025-09-16', 'end' => '2028-09-15', 'amount' => '100000',
        'escalation' => EscalationType::Percent, 'escalationValue' => '10', 'every' => 1,
    ]);

    expect(array_map(fn ($r) => [$r['from_date'], $r['to_date'], $r['base_amount']], $rows))->toBe([
        ['2025-09-16', '2026-03-31', '100000.00'],
        ['2026-04-01', '2026-09-15', '100000.00'],
        ['2026-09-16', '2027-03-31', '110000.00'],
        ['2027-04-01', '2027-09-15', '110000.00'],
        ['2027-09-16', '2028-03-31', '121000.00'],
        ['2028-04-01', '2028-09-15', '121000.00'],
    ]);
    // 2026-09-16 → 2027-03-31 is 197 days at 110000 p.a.
    expect($rows[2]['amount'])->toBe('59370.00');
});

test('fixed-amount escalation adds the amount each step', function () {
    $rows = schedule([
        'start' => '2025-04-01', 'end' => '2029-03-31', 'amount' => '50000',
        'escalation' => EscalationType::Amount, 'escalationValue' => '5000', 'every' => 2,
    ]);

    expect(array_column($rows, 'amount'))->toBe(['50000.00', '50000.00', '55000.00', '55000.00']);
});

test('one-time fees are a single charge for the whole term', function () {
    $rows = schedule(['frequency' => FeeFrequency::OneTime, 'amount' => '65000']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['from_date' => '2025-09-16', 'to_date' => '2028-09-15', 'bill_date' => '2025-09-16', 'amount' => '65000.00', 'prorated' => false]);
});

test('rounding follows the configured scale', function () {
    expect(schedule(['start' => '2025-09-16'], scale: 2)[0]['amount'])->toBe('35082.19');
});

test('a fee that ends before it starts is rejected', function () {
    schedule(['start' => '2026-01-01', 'end' => '2025-12-31']);
})->throws(InvalidArgumentException::class);

test('the generated periods cover every day from start to end exactly once', function () {
    $rows = schedule([
        'frequency' => FeeFrequency::Quarterly, 'start' => '2024-02-10', 'end' => '2031-11-05', 'amount' => '99999',
        'escalation' => EscalationType::Percent, 'escalationValue' => '7.5', 'every' => 2,
    ]);

    expect($rows[0]['from_date'])->toBe('2024-02-10')->and(end($rows)['to_date'])->toBe('2031-11-05');
    for ($i = 1; $i < count($rows); $i++) {
        expect(CarbonImmutable::parse($rows[$i - 1]['to_date'])->addDay()->toDateString())->toBe($rows[$i]['from_date']);
    }
    expect(array_sum(array_column($rows, 'days')))->toBe((int) CarbonImmutable::parse('2024-02-10')->diffInDays(CarbonImmutable::parse('2031-11-05')) + 1);
});
