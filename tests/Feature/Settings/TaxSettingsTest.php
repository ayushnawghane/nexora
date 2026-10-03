<?php

use App\Models\Setting;
use App\Models\State;
use App\Models\TaxRate;
use App\Services\Tax\GstCalculator;
use App\Services\Tax\TaxNotConfigured;
use Database\Seeders\StateSeeder;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed([StateSeeder::class, TaxRateSeeder::class]);
});

function gstState(string $code): int
{
    return State::query()->where('gst_code', $code)->value('id');
}

test('tax settings need permission', function () {
    signIn();
    $this->get('/admin/tax-settings')->assertForbidden();

    signIn(permissions: ['settings.view']);
    $this->get('/admin/tax-settings')->assertInertia(fn ($page) => $page
        ->component('Settings/Tax')
        ->where('rates.0.igst', '18.00')
        ->where('rates.0.status', 'current')
        ->where('can.manage', false));
    $this->put('/admin/tax-settings/gstin', ['gstin' => '27AAACR5055K1Z7'])->assertForbidden();
    $this->post('/admin/tax-settings/rates', [])->assertForbidden();
});

test('Beacon\'s GSTIN is validated and decides the home state', function () {
    signIn(permissions: ['settings.view', 'settings.manage']);

    $this->put('/admin/tax-settings/gstin', ['gstin' => '27AAACR5055K1Z8'])->assertSessionHasErrors('gstin');
    $this->put('/admin/tax-settings/gstin', ['gstin' => ' 27aaacr5055k1z7 '])->assertSessionHasNoErrors();

    expect(Setting::value(Setting::BEACON_GSTIN))->toBe('27AAACR5055K1Z7')
        ->and(app(GstCalculator::class)->homeStateId())->toBe(gstState('27'));
    $this->get('/admin/tax-settings')->assertInertia(fn ($page) => $page->where('beacon.state', 'Maharashtra'));
});

test('a new rate must be future-dated, after the latest rate, with SGST = CGST and IGST = their sum', function (array $payload, string $field) {
    signIn(permissions: ['settings.manage']);

    $this->post('/admin/tax-settings/rates', array_merge([
        'effective_from' => today()->addMonth()->toDateString(), 'cgst' => '6', 'sgst' => '6', 'igst' => '12',
    ], $payload))->assertSessionHasErrors($field);
})->with([
    'in the past' => [['effective_from' => today()->subDay()->toDateString()], 'effective_from'],
    'CGST ≠ SGST' => [['sgst' => '7', 'igst' => '13'], 'sgst'],
    'IGST ≠ sum' => [['igst' => '18'], 'igst'],
    'three decimals' => [['cgst' => '6.125'], 'cgst'],
    'negative' => [['cgst' => '-6', 'sgst' => '-6', 'igst' => '-12'], 'cgst'],
]);

test('rates are added in date order and only scheduled ones can be withdrawn', function () {
    signIn(permissions: ['settings.manage']);
    $later = today()->addMonths(2)->toDateString();

    $this->post('/admin/tax-settings/rates', ['effective_from' => $later, 'cgst' => '6', 'sgst' => '6', 'igst' => '12'])
        ->assertSessionHasNoErrors();
    $this->post('/admin/tax-settings/rates', ['effective_from' => today()->addMonth()->toDateString(), 'cgst' => '6', 'sgst' => '6', 'igst' => '12'])
        ->assertSessionHasErrors('effective_from'); // before the latest one

    $scheduled = TaxRate::query()->where('effective_from', $later)->sole();
    $inForce = TaxRate::query()->where('effective_from', '2017-07-01')->sole();

    $this->delete("/admin/tax-settings/rates/{$inForce->id}")->assertSessionHas('error');
    $this->delete("/admin/tax-settings/rates/{$scheduled->id}")->assertSessionHas('success');
    expect(TaxRate::query()->pluck('effective_from')->map->toDateString()->all())->toBe(['2017-07-01']);
});

test('GST is CGST + SGST within Beacon\'s state and IGST across states, rounded to paise', function () {
    Setting::put(Setting::BEACON_GSTIN, '27AAACR5055K1Z7');
    $calc = app(GstCalculator::class);

    $intra = $calc->calculate('1234.55', gstState('27'), today());
    expect($intra->interState)->toBeFalse()
        ->and($intra->cgst)->toBe('111.11') // 111.1095
        ->and($intra->sgst)->toBe('111.11')
        ->and($intra->igst)->toBe('0.00')
        ->and($intra->totalTax)->toBe('222.22')
        ->and($intra->total)->toBe('1456.77');

    $inter = $calc->calculate('1234.55', gstState('29'), today());
    expect($inter->interState)->toBeTrue()
        ->and($inter->igst)->toBe('222.22') // 222.219
        ->and($inter->cgst)->toBe('0.00')
        ->and($inter->total)->toBe('1456.77');

    expect($calc->calculate('100000000.00', gstState('29'), today())->igst)->toBe('18000000.00');
});

test('the rate in force on the invoice date is used', function () {
    Setting::put(Setting::BEACON_GSTIN, '27AAACR5055K1Z7');
    TaxRate::query()->create(['effective_from' => '2030-04-01', 'cgst' => '6', 'sgst' => '6', 'igst' => '12']);
    $calc = app(GstCalculator::class);

    expect($calc->calculate('1000', gstState('29'), Carbon::parse('2030-03-31'))->igst)->toBe('180.00')
        ->and($calc->calculate('1000', gstState('29'), Carbon::parse('2030-04-01'))->igst)->toBe('120.00');

    expect(fn () => $calc->calculate('1000', gstState('29'), Carbon::parse('2017-06-30')))
        ->toThrow(TaxNotConfigured::class);
});

test('GST can\'t be worked out without Beacon\'s GSTIN', function () {
    expect(fn () => app(GstCalculator::class)->calculate('1000', gstState('27'), today()))
        ->toThrow(TaxNotConfigured::class, 'Beacon\'s GSTIN is not set');
});
