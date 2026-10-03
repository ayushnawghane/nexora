<?php

use App\Models\Arranger;
use App\Models\Bank;
use App\Models\ContactType;
use App\Models\LeadSource;
use App\Models\Pincode;
use App\Models\State;
use Database\Seeders\StateSeeder;

beforeEach(function () {
    $this->seed(StateSeeder::class);
    legacySchema();
    legacyRows('master_lead', [['id' => 1, 'lead_name' => ' Organic  Search '], ['id' => 2, 'lead_name' => 'Referral', 'is_active' => 0]]);
    legacyRows('master_contact_type', [['id' => 1, 'contact_type' => 'Accounts']]);
    legacyRows('master_arranger', [
        ['id' => 1, 'arranger_name' => '<script>alert(2)</script>', 'arranger_cin' => null, 'is_active' => 0, 'is_deleted' => 1],
        ['id' => 2, 'arranger_name' => 'Vivriti Capital Limited', 'arranger_cin' => 'u65929tn2017plc117196'],
        ['id' => 3, 'arranger_name' => 'Northern Arc Capital Limited', 'arranger_cin' => 'not-a-cin'],
    ]);
    legacyRows('master_bank', [['id' => 1, 'bank_name' => 'State Bank of India', 'cin' => null], ['id' => 2, 'bank_name' => 'state bank of india', 'cin' => null]]);
    legacyRows('master_pincode', [
        ['id' => 1, 'pincode' => '400001', 'city' => 'Mumbai', 'state' => 'Maharashtra'],
        ['id' => 2, 'pincode' => '396210', 'city' => 'Daman', 'state' => 'The Dadra And Nagar Haveli And Daman And Diu'],
        ['id' => 3, 'pincode' => '000000', 'city' => 'Nowhere', 'state' => 'Maharashtra'],
        ['id' => 4, 'pincode' => '110001', 'city' => 'Delhi', 'state' => 'Na'],
        ['id' => 5, 'pincode' => '400001', 'city' => 'mumbai', 'state' => 'Maharashtra'],
    ]);
    ContactType::query()->create(['name' => 'Accounts']); // already added in Nexora
});

test('masters come over cleaned, with junk and duplicates rejected', function () {
    $this->artisan('legacy:import', ['area' => 'masters'])
        ->expectsOutputToContain('Name "<script>alert(2)</script>" contains markup.')
        ->expectsOutputToContain('Unknown state "Na".')
        ->assertSuccessful();

    expect(LeadSource::query()->where('legacy_id', 1)->value('name'))->toBe('Organic Search')
        ->and(LeadSource::query()->where('legacy_id', 2)->value('is_active'))->toBeFalse()
        ->and(ContactType::query()->sole()->legacy_id)->toBe(1)
        ->and(Arranger::query()->pluck('name')->sort()->values()->all())->toBe(['Northern Arc Capital Limited', 'Vivriti Capital Limited'])
        ->and(Arranger::query()->where('legacy_id', 2)->value('cin'))->toBe('U65929TN2017PLC117196')
        ->and(Arranger::query()->where('legacy_id', 3)->value('cin'))->toBeNull()
        ->and(Bank::query()->count())->toBe(1);

    expect(Pincode::query()->count())->toBe(2)
        ->and(Pincode::query()->where('legacy_id', 2)->value('state_id'))->toBe(State::query()->where('gst_code', '26')->value('id'));

    $this->artisan('legacy:import', ['area' => 'masters'])->assertSuccessful();
    expect(Pincode::query()->count())->toBe(2)->and(LeadSource::query()->count())->toBe(2);
});
