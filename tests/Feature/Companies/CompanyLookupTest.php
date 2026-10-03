<?php

use App\Enums\CompanyCategory;
use App\Models\Company;
use App\Models\CompanyGstin;
use App\Services\CompanyLookup\CachedCompanyLookup;
use App\Services\CompanyLookup\CodiumCompanyLookup;
use App\Services\CompanyLookup\CompanyLookup;
use App\Services\CompanyLookup\LookupFailed;
use App\Services\CompanyLookup\PanDetails;
use Database\Seeders\StateSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function codium(): CodiumCompanyLookup
{
    return new CodiumCompanyLookup([
        'login_url' => 'https://codium.test/login',
        'cin_url' => 'https://codium.test/cin',
        'gst_url' => 'https://codium.test/gst',
        'pan_url' => 'https://codium.test/pan',
        'company_code' => 'BTL',
        'password' => 'secret',
        'jwt_secret' => 'jwt-key',
        'surepass_key' => 'surepass-key',
        'stack_token' => 'stack-token',
        'email' => 'ops@beacon.test',
        'verify_ssl' => true,
        'timeout' => 5,
    ]);
}

beforeEach(fn () => Http::preventStrayRequests());

test('lookups need permission to create companies', function () {
    signIn(permissions: ['companies.view']);

    $this->getJson('/companies/lookup/cin?value=L17110MH1973PLC019786')->assertForbidden();
});

test('a CIN lookup returns registry details for pre-filling', function () {
    signIn(permissions: ['companies.manage']);

    $this->getJson('/companies/lookup/cin?value=l17110mh1973plc019786')
        ->assertOk()
        ->assertJsonPath('data.cin', 'L17110MH1973PLC019786')
        ->assertJsonPath('data.incorporated_on', '1973-06-15')
        ->assertJsonPath('data.category', 'limited_by_shares')
        ->assertJsonPath('existing', null);

    expect(Company::query()->count())->toBe(0); // a lookup never saves anything
});

test('a lookup reports a company that already holds the number', function () {
    $this->seed(StateSeeder::class);
    signIn(permissions: ['companies.manage']);
    $company = Company::factory()->create(['cin' => 'L17110MH1973PLC019786', 'pan' => 'AAACR5055K']);
    CompanyGstin::factory()->for($company)->create(['gstin' => '27AAACR5055K1Z7']);

    foreach (['cin' => 'L17110MH1973PLC019786', 'pan' => 'AAACR5055K', 'gstin' => '27AAACR5055K1Z7'] as $type => $value) {
        $this->getJson("/companies/lookup/{$type}?value={$value}")
            ->assertOk()
            ->assertJsonPath('existing.id', $company->ulid);
    }
});

test('invalid identifiers and unknown types are refused before any lookup', function () {
    signIn(permissions: ['companies.manage']);

    $this->getJson('/companies/lookup/cin?value=NA')->assertStatus(422);
    $this->getJson('/companies/lookup/gstin?value=27AAACR5055K1Z8')->assertStatus(422); // bad check digit
    $this->getJson('/companies/lookup/pan?value=')->assertStatus(422);
    $this->getJson('/companies/lookup/llpin?value=AAB-1234')->assertNotFound();
});

test('not-found and service failures come back as a readable message', function () {
    signIn(permissions: ['companies.manage']);

    $this->getJson('/companies/lookup/cin?value=U65990MH2010PTC000000')
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'No company record'));
    $this->getJson('/companies/lookup/pan?value=ERRCR5055K')
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not responding'));
});

test('lookups are rate limited', function () {
    signIn(permissions: ['companies.manage']);

    foreach (range(1, 30) as $i) {
        $this->getJson('/companies/lookup/pan?value=AAACR5055K')->assertOk();
    }
    $this->getJson('/companies/lookup/pan?value=AAACR5055K')->assertStatus(429);
});

test('successful lookups are cached, failures are not', function () {
    $inner = Mockery::mock(CompanyLookup::class);
    $inner->shouldReceive('pan')->once()->andReturn(new PanDetails('AAACR5055K', 'RELIANCE'));
    $inner->shouldReceive('cin')->twice()->andThrow(LookupFailed::unavailable());
    $cached = new CachedCompanyLookup($inner, 24);

    $cached->pan('AAACR5055K');
    $cached->pan('AAACR5055K');
    foreach ([1, 2] as $attempt) {
        expect(fn () => $cached->cin('L17110MH1973PLC019786'))->toThrow(LookupFailed::class);
    }
});

test('the Codium driver signs in once and maps the CIN response', function () {
    Http::fake([
        'codium.test/login' => Http::response(['access_token' => 'tok-1']),
        'codium.test/cin' => Http::response(['data' => ['status_code' => 200, 'data' => [
            'company_name' => 'RELIANCE INDUSTRIES LIMITED',
            'details' => ['company_info' => [
                'cin' => 'L17110MH1973PLC019786',
                'date_of_incorporation' => '08/05/1973',
                'company_category' => 'Company limited by Shares',
                'company_status' => 'Active',
                'email_id' => 'investor.relations@ril.com',
                'registered_address' => '3rd Floor, Maker Chambers IV, Mumbai 400021',
            ]],
        ]]]),
    ]);

    $details = codium()->cin('L17110MH1973PLC019786');
    codium()->cin('L17110MH1973PLC019786');

    expect($details->name)->toBe('RELIANCE INDUSTRIES LIMITED')
        ->and($details->incorporatedOn)->toBe('1973-05-08')
        ->and($details->category)->toBe(CompanyCategory::LimitedByShares)
        ->and($details->status)->toBe('Active');

    Http::assertSentCount(3); // one sign-in (token cached), two lookups
    Http::assertSent(fn (Request $r) => $r->url() === 'https://codium.test/login'
        && $r['company_code'] === 'BTL' && $r->hasHeader('apikey', 'jwt-key'));
    Http::assertSent(fn (Request $r) => $r->url() === 'https://codium.test/cin'
        && $r['cin'] === 'L17110MH1973PLC019786'
        && $r->hasHeader('Authorization', 'Bearer tok-1')
        && $r->hasHeader('apikey', 'surepass-key')
        && $r->hasHeader('token', 'stack-token'));
});

test('the Codium driver maps GST and PAN responses', function () {
    Http::fake([
        'codium.test/login' => Http::response(['access_token' => 'tok-1']),
        'codium.test/gst' => Http::response(['data' => ['data' => [
            'gstin' => '27AAACR5055K1Z7',
            'legal_name' => 'RELIANCE INDUSTRIES LIMITED',
            'business_name' => 'NA',
            'date_of_registration' => '2017-07-01',
            'gstin_status' => 'Cancelled',
            'date_of_cancellation' => '31-03-2023',
            'contact_details' => ['principal' => ['address' => 'Maker Chambers IV, Mumbai, Maharashtra, 400021']],
        ]]]),
        'codium.test/pan' => Http::response(['data' => ['data' => ['pan_number' => 'AAACR5055K', 'full_name' => 'RELIANCE INDUSTRIES LIMITED']]]),
    ]);

    $gst = codium()->gstin('27AAACR5055K1Z7');
    expect($gst->legalName)->toBe('RELIANCE INDUSTRIES LIMITED')
        ->and($gst->tradeName)->toBeNull() // "NA" means none
        ->and($gst->registeredOn)->toBe('2017-07-01')
        ->and($gst->cancelledOn)->toBe('2023-03-31')
        ->and($gst->isActive())->toBeFalse();

    expect(codium()->pan('AAACR5055K')->name)->toBe('RELIANCE INDUSTRIES LIMITED');
});

test('the Codium driver re-signs in once when its token is rejected', function () {
    Http::fake([
        'codium.test/login' => Http::sequence()->push(['access_token' => 'old'])->push(['access_token' => 'new']),
        'codium.test/pan' => Http::sequence()
            ->push([], 401)
            ->push(['data' => ['data' => ['pan_number' => 'AAACR5055K', 'full_name' => 'RIL']]]),
    ]);

    expect(codium()->pan('AAACR5055K')->name)->toBe('RIL');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://codium.test/pan' && $r->hasHeader('Authorization', 'Bearer new'));
});

test('the Codium driver turns empty and failed responses into readable errors', function (array $login, array $cin, string $message) {
    Http::fake([
        'codium.test/login' => Http::response($login[0], $login[1]),
        'codium.test/cin' => Http::response($cin[0], $cin[1]),
    ]);

    expect(fn () => codium()->cin('L17110MH1973PLC019786'))->toThrow(LookupFailed::class, $message);
})->with([
    'empty record' => [[['access_token' => 'tok'], 200], [['data' => ['data' => null], 'error' => 'Invalid CIN'], 200], 'No company record'],
    'server error' => [[['access_token' => 'tok'], 200], [['error' => 'oops'], 500], 'not responding'],
    'sign-in refused' => [[['error' => 'bad credentials'], 401], [[], 200], 'not responding'],
]);
