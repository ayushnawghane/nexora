<?php

use App\Enums\AddressType;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\ContactType;
use App\Models\Pincode;
use App\Models\State;
use Database\Factories\CompanyGstinFactory;
use Database\Seeders\StateSeeder;

beforeEach(function () {
    $this->seed(StateSeeder::class);
});

function validCompanyPayload(array $overrides = []): array
{
    return array_merge([
        'entity_type' => 'company',
        'cin' => 'l17110mh1973plc019786 ',
        'name' => '  Reliance Industries Limited ',
        'pan' => 'aaacr5055k',
        'category' => 'limited_by_shares',
        'incorporated_on' => '1973-05-08',
        // Ignored for companies: class and listing come from the CIN.
        'company_class' => 'private',
        'is_listed' => false,
    ], $overrides);
}

function stateId(string $gstCode): int
{
    return State::query()->where('gst_code', $gstCode)->value('id');
}

test('companies need permission to view and manage', function () {
    signIn();
    $company = Company::factory()->create();

    $this->get('/companies')->assertForbidden();
    $this->get("/companies/{$company->ulid}")->assertForbidden();

    signIn(permissions: ['companies.view']);
    $this->get('/companies')->assertOk();
    $this->get("/companies/{$company->ulid}")->assertOk();
    $this->get('/companies/create')->assertForbidden();
    $this->post('/companies', validCompanyPayload())->assertForbidden();
    $this->post("/companies/{$company->ulid}/contacts", ['name' => 'X', 'email' => 'x@y.test'])->assertForbidden();
});

test('the company list searches name, CIN, PAN and GSTIN', function () {
    signIn(permissions: ['companies.view']);
    $acme = Company::factory()->create(['name' => 'Acme Finance Private Limited']);
    Company::factory()->create(['name' => 'Zeta Housing Limited']);
    $gstin = CompanyGstin::factory()->for($acme)->create();

    foreach (['acme', $acme->cin, strtolower($acme->pan), $gstin->gstin] as $term) {
        $this->get('/companies?filter[search]='.urlencode($term))->assertInertia(fn ($page) => $page
            ->component('Companies/Index')
            ->where('companies.total', 1)
            ->where('companies.data.0.name', 'Acme Finance Private Limited')
            ->where('companies.data.0.gstins_count', 1));
    }

    // A comma is part of the search, not a list separator.
    Company::factory()->create(['name' => 'Sachdev, Gala and Bhatia Private Limited']);
    $this->get('/companies?filter[search]='.urlencode('Sachdev, Gala'))->assertInertia(fn ($page) => $page
        ->where('companies.total', 1));

    $this->get('/companies?sort=-name')->assertOk();
    $this->get('/companies?sort=legacy_id')->assertStatus(400);
});

test('creating a company normalises input and reads class and listing from the CIN', function () {
    signIn(permissions: ['companies.view', 'companies.manage']);

    $this->post('/companies', validCompanyPayload())->assertRedirect();

    $company = Company::query()->sole();
    expect($company->cin)->toBe('L17110MH1973PLC019786')
        ->and($company->pan)->toBe('AAACR5055K')
        ->and($company->name)->toBe('Reliance Industries Limited')
        ->and($company->company_class)->toBe(CompanyClass::Public)
        ->and($company->is_listed)->toBeTrue()
        ->and($company->entity_type)->toBe(EntityType::Company);
});

test('other entities have no CIN and may set class and listing by hand', function () {
    signIn(permissions: ['companies.manage']);

    $this->post('/companies', [
        'entity_type' => 'other',
        'name' => 'Sunrise Charitable Trust',
        'pan' => 'AAATS1234A',
        'is_listed' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Company::query()->sole()->cin)->toBeNull();

    $this->post('/companies', ['entity_type' => 'other', 'name' => 'X', 'cin' => 'U65990MH2010PTC123456'])
        ->assertSessionHasErrors('cin');
});

test('company validation rejects bad input', function (array $payload, string $field) {
    signIn(permissions: ['companies.manage']);
    Company::factory()->create(['cin' => 'U65990MH2010PTC123456', 'pan' => 'AAACT1234Z']);

    $this->post('/companies', validCompanyPayload($payload))->assertSessionHasErrors($field);
})->with([
    'missing CIN' => [['cin' => ''], 'cin'],
    'bad CIN' => [['cin' => 'U65990MH2010PTC12345'], 'cin'],
    'duplicate CIN' => [['cin' => 'u65990mh2010ptc123456', 'incorporated_on' => null], 'cin'],
    'LLPIN for a company' => [['cin' => 'AAB-1234'], 'cin'],
    'missing PAN' => [['pan' => ''], 'pan'],
    'bad PAN' => [['pan' => 'AAAC1234Z'], 'pan'],
    'duplicate PAN' => [['pan' => 'AAACT1234Z'], 'pan'],
    'person PAN on a company' => [['pan' => 'AAAPR5055K'], 'pan'],
    'year differs from CIN' => [['incorporated_on' => '1975-01-01'], 'incorporated_on'],
    'future incorporation' => [['incorporated_on' => now()->addDay()->toDateString()], 'incorporated_on'],
    'former name same as name' => [['formerly_known_as' => 'Reliance Industries Limited'], 'formerly_known_as'],
    'unknown entity type' => [['entity_type' => 'society'], 'entity_type'],
]);

test('an LLP needs an LLPIN and a firm PAN', function () {
    signIn(permissions: ['companies.manage']);

    $this->post('/companies', ['entity_type' => 'llp', 'name' => 'Rao & Co LLP', 'cin' => 'aab-1234', 'pan' => 'AAFFR1234Q'])
        ->assertSessionHasNoErrors();
    $this->post('/companies', ['entity_type' => 'llp', 'name' => 'Other LLP', 'cin' => 'AAB-9999', 'pan' => 'AAACR1234Q'])
        ->assertSessionHasErrors('pan');
});

test('a company can be updated, but not to a PAN its GSTINs do not carry', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create(['cin' => 'L17110MH1973PLC019786', 'pan' => 'AAACR5055K', 'incorporated_on' => '1973-05-08']);

    $this->put("/companies/{$company->ulid}", validCompanyPayload(['name' => 'Reliance Industries Ltd']))
        ->assertRedirect("/companies/{$company->ulid}");
    expect($company->fresh()->name)->toBe('Reliance Industries Ltd');

    CompanyGstin::factory()->for($company)->create(['gstin' => '27AAACR5055K1Z7']);

    $this->put("/companies/{$company->ulid}", validCompanyPayload(['pan' => 'AAACX5055K']))->assertSessionHasErrors('pan');
});

test('a company can be deactivated and reactivated', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();

    $this->post("/companies/{$company->ulid}/toggle-active")->assertRedirect();
    expect($company->fresh()->is_active)->toBeFalse();

    $this->post("/companies/{$company->ulid}/toggle-active");
    expect($company->fresh()->is_active)->toBeTrue();
});

test('adding a GSTIN checks the check digit and PAN, and takes the state from the code', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create(['pan' => 'AAACR5055K']);

    $this->post("/companies/{$company->ulid}/gstins", ['gstin' => ' 27aaacr5055k1z7 ', 'legal_name' => 'Reliance'])
        ->assertSessionHasNoErrors();

    $gstin = CompanyGstin::query()->sole();
    expect($gstin->gstin)->toBe('27AAACR5055K1Z7')
        ->and($gstin->state_id)->toBe(stateId('27'));

    $other = Company::factory()->create(['pan' => 'AAPFU0939F']);
    $this->post("/companies/{$company->ulid}/gstins", ['gstin' => '27AAPFU0939F1ZV'])->assertSessionHasErrors('gstin'); // another PAN
    $this->post("/companies/{$company->ulid}/gstins", ['gstin' => '27AAACR5055K1Z8'])->assertSessionHasErrors('gstin'); // check digit
    $this->post("/companies/{$company->ulid}/gstins", ['gstin' => '27AAACR5055K1Z7'])->assertSessionHasErrors('gstin'); // duplicate
    $this->post("/companies/{$company->ulid}/gstins", ['gstin' => CompanyGstinFactory::gstinFor('64', 'AAACR5055K')])
        ->assertSessionHasErrors('gstin'); // no such state code
    $this->post("/companies/{$other->ulid}/gstins", ['gstin' => '27AAPFU0939F1ZV'])->assertSessionHasNoErrors();
});

test('a GSTIN needs the company PAN first, and cannot be changed once saved', function () {
    signIn(permissions: ['companies.manage']);
    $trust = Company::factory()->other()->create();

    $this->post("/companies/{$trust->ulid}/gstins", ['gstin' => '27AAPFU0939F1ZV'])->assertSessionHasErrors('gstin');

    $company = Company::factory()->create(['pan' => 'AAACR5055K']);
    $gstin = CompanyGstin::factory()->for($company)->create(['gstin' => '27AAACR5055K1Z7']);

    $this->put("/companies/{$company->ulid}/gstins/{$gstin->id}", ['gstin' => CompanyGstinFactory::gstinFor('29', 'AAACR5055K'), 'trade_name' => 'RIL'])
        ->assertSessionHasErrors('gstin');
    $this->put("/companies/{$company->ulid}/gstins/{$gstin->id}", ['trade_name' => 'RIL'])->assertSessionHasNoErrors();
    expect($gstin->fresh()->trade_name)->toBe('RIL')->and($gstin->fresh()->gstin)->toBe('27AAACR5055K1Z7');
});

test('child records are only reachable through their own company', function () {
    signIn(permissions: ['companies.manage']);
    $mine = Company::factory()->create();
    $theirs = Company::factory()->create();
    $gstin = CompanyGstin::factory()->for($theirs)->create();
    $contact = CompanyContact::factory()->for($theirs)->create();

    $this->post("/companies/{$mine->ulid}/gstins/{$gstin->id}/toggle")->assertNotFound();
    $this->put("/companies/{$mine->ulid}/contacts/{$contact->id}", ['name' => 'X', 'email' => 'x@y.test'])->assertNotFound();
});

test('an address linked to a GSTIN must be in its state, and a known pincode in the chosen state', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $gstin = CompanyGstin::factory()->for($company)->create(['state_id' => stateId('27')]);
    Pincode::query()->create(['pincode' => '560001', 'city' => 'Bengaluru', 'state_id' => stateId('29')]);

    $address = fn (array $overrides = []) => array_merge([
        'type' => 'billing', 'line1' => '1 Marine Drive', 'city' => 'Mumbai', 'pincode' => '400 001',
        'state_id' => stateId('27'), 'company_gstin_id' => $gstin->id,
    ], $overrides);

    $this->post("/companies/{$company->ulid}/addresses", $address(['state_id' => stateId('29')]))->assertSessionHasErrors('state_id');
    $this->post("/companies/{$company->ulid}/addresses", $address(['pincode' => '560001']))->assertSessionHasErrors('pincode');
    $this->post("/companies/{$company->ulid}/addresses", $address(['pincode' => '012345']))->assertSessionHasErrors('pincode');
    $this->post("/companies/{$company->ulid}/addresses", $address())->assertSessionHasNoErrors();

    expect(CompanyAddress::query()->sole())
        ->pincode->toBe('400001')
        ->company_gstin_id->toBe($gstin->id);
});

test('an address cannot use another company\'s GSTIN or an inactive one', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $foreign = CompanyGstin::factory()->create();
    $inactive = CompanyGstin::factory()->for($company)->create(['is_active' => false]);

    foreach ([$foreign, $inactive] as $gstin) {
        $this->post("/companies/{$company->ulid}/addresses", [
            'type' => 'billing', 'line1' => 'X', 'city' => 'Mumbai', 'pincode' => '400001',
            'state_id' => stateId('27'), 'company_gstin_id' => $gstin->id,
        ])->assertSessionHasErrors('company_gstin_id');
    }
});

test('a company has at most one active registered office', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $first = CompanyAddress::factory()->for($company)->create(['type' => AddressType::Registered]);
    $payload = ['type' => 'registered', 'line1' => 'X', 'city' => 'Mumbai', 'pincode' => '400001', 'state_id' => stateId('27')];

    $this->post("/companies/{$company->ulid}/addresses", $payload)->assertSessionHasErrors('type');
    $this->put("/companies/{$company->ulid}/addresses/{$first->id}", $payload)->assertSessionHasNoErrors();

    $this->post("/companies/{$company->ulid}/addresses/{$first->id}/toggle");
    $this->post("/companies/{$company->ulid}/addresses", $payload)->assertSessionHasNoErrors();

    $this->post("/companies/{$company->ulid}/addresses/{$first->id}/toggle")->assertSessionHas('error');
    expect($first->fresh()->is_active)->toBeFalse();
});

test('a GSTIN used by active addresses cannot be deactivated', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $gstin = CompanyGstin::factory()->for($company)->create();
    $address = CompanyAddress::factory()->for($company)->create(['company_gstin_id' => $gstin->id]);

    $this->post("/companies/{$company->ulid}/gstins/{$gstin->id}/toggle")->assertSessionHas('error');
    expect($gstin->fresh()->is_active)->toBeTrue();

    $this->post("/companies/{$company->ulid}/addresses/{$address->id}/toggle");
    $this->post("/companies/{$company->ulid}/gstins/{$gstin->id}/toggle")->assertSessionHas('success');
    expect($gstin->fresh()->is_active)->toBeFalse();

    // The address can't come back while its GSTIN is inactive.
    $this->post("/companies/{$company->ulid}/addresses/{$address->id}/toggle")->assertSessionHas('error');
});

test('contacts need an email or mobile, and emails are unique within a company', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $type = ContactType::query()->create(['name' => 'Management']);
    $url = "/companies/{$company->ulid}/contacts";

    $this->post($url, ['name' => 'Asha Rao'])->assertSessionHasErrors(['email', 'mobile']);
    $this->post($url, ['name' => 'Asha Rao', 'email' => ' Asha@Client.test ', 'contact_type_id' => $type->id, 'salutation' => 'Ms'])
        ->assertSessionHasNoErrors();
    $this->post($url, ['name' => 'Someone', 'email' => 'asha@client.test'])->assertSessionHasErrors('email');
    $this->post($url, ['name' => 'Ravi', 'mobile' => '98765 43210'])->assertSessionHasNoErrors();
    $this->post($url, ['name' => 'Bad', 'mobile' => '12345'])->assertSessionHasErrors('mobile');
    $this->post($url, ['name' => 'Bad', 'mobile' => '9876543210', 'salutation' => 'Sir'])->assertSessionHasErrors('salutation');

    // The same email is fine at a different company.
    $this->post('/companies/'.Company::factory()->create()->ulid.'/contacts', ['name' => 'Asha', 'email' => 'asha@client.test'])
        ->assertSessionHasNoErrors();

    $asha = CompanyContact::query()->where('name', 'Asha Rao')->sole();
    expect($asha->email)->toBe('asha@client.test')
        ->and($asha->contactType->is($type))->toBeTrue()
        ->and(CompanyContact::query()->where('name', 'Ravi')->value('mobile'))->toBe('9876543210');
});

test('an inactive contact type cannot be newly assigned', function () {
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create();
    $type = ContactType::query()->create(['name' => 'Old', 'is_active' => false]);

    $this->post("/companies/{$company->ulid}/contacts", ['name' => 'X', 'email' => 'x@y.test', 'contact_type_id' => $type->id])
        ->assertSessionHasErrors('contact_type_id');
});

test('the company page shows its GSTINs, addresses and contacts', function () {
    signIn(permissions: ['companies.view']);
    $company = Company::factory()->create();
    $gstin = CompanyGstin::factory()->for($company)->create();
    CompanyAddress::factory()->for($company)->create(['company_gstin_id' => $gstin->id]);
    CompanyContact::factory()->for($company)->count(2)->create();

    $this->get("/companies/{$company->ulid}")->assertInertia(fn ($page) => $page
        ->component('Companies/Show')
        ->where('company.name', $company->name)
        ->has('gstins', 1)
        ->where('gstins.0.addresses_count', 1)
        ->has('addresses', 1)
        ->where('addresses.0.gstin', $gstin->gstin)
        ->has('contacts', 2)
        ->where('can.update', false));
});

test('a contact type in use by a company contact cannot be deleted', function () {
    signIn(permissions: ['masters.view', 'masters.manage']);
    $type = ContactType::query()->create(['name' => 'Finance']);
    CompanyContact::factory()->create(['contact_type_id' => $type->id]);

    $this->delete("/masters/contact-types/{$type->id}")->assertSessionHas('error');
    expect($type->fresh())->not->toBeNull();
});
