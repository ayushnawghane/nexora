<?php

use App\Enums\AddressType;
use App\Enums\CompanyClass;
use App\Enums\EntityType;
use App\Legacy\LegacyAlias;
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
    legacySchema();
    $mh = State::query()->where('gst_code', '27')->value('id');
    Pincode::query()->create(['pincode' => '400069', 'city' => 'Mumbai', 'state_id' => $mh]);
    ContactType::query()->create(['name' => 'Finance']);

    $gstin = CompanyGstinFactory::gstinFor('27', 'AAACA1234C');
    legacyRows('master_cin', [
        ['id' => 10, 'cin' => 'U65999MH2010PTC123456', 'pan_number' => null, 'company_name' => ' Aadhar  Housing Finance Limited ',
            'incorp_date' => '2010-02-01', 'address' => 'Natraj by Rustomjee, Andheri East, Mumbai 400 069', 'formerly_known' => 'Aadhar Housing Finance Limited'],
        ['id' => 11, 'cin' => 'u65999mh2010ptc123456', 'pan_number' => null, 'company_name' => 'Aadhar Housing (duplicate entry)'],
        ['id' => 12, 'cin' => 'NA', 'pan_number' => 'BADPAN', 'company_name' => 'Ramesh Kumar'],
        ['id' => 13, 'cin' => '1234', 'pan_number' => null, 'company_name' => 'Test Junk', 'incorp_date' => '1999-01-01', 'address' => 'No pincode here'],
    ]);
    legacyRows('master_gst_no', [
        ['id' => 1, 'company_id' => 11, 'gstin' => strtolower($gstin), 'name' => 'AADHAR HOUSING FINANCE LIMITED', 'registrationDate' => '2017-07-01', 'status' => 'Active'],
        ['id' => 2, 'company_id' => 10, 'gstin' => '27AAACA1234C1Z0', 'status' => 'Active'],
        ['id' => 3, 'company_id' => 12, 'gstin' => 'NA'],
        ['id' => 4, 'company_id' => 10, 'gstin' => $gstin, 'status' => null], // entered twice
    ]);
    legacyRows('transaction', [['id' => 500, 'company_id' => 10, 'company_address_id' => 3]]);
    legacyRows('company_address_master', [
        ['id' => 1, 'company_id' => 10, 'master_gst_id' => 1, 'billing_name' => 'AADHAR HOUSING FINANCE LIMITED', 'billing_address' => '8th Floor, Unit 802, Natraj by Rustomjee, M.V. Road, Andheri East', 'pincode' => '400069'],
        ['id' => 2, 'company_id' => 12, 'master_gst_id' => null, 'billing_address' => 'Somewhere without a pincode', 'pincode' => null],
        // No company id, but a transaction is billed to it; linked to the duplicate GSTIN row.
        ['id' => 3, 'company_id' => 0, 'master_gst_id' => 4, 'billing_address' => 'Andheri East branch', 'pincode' => '400069'],
    ]);
    legacyRows('company_contact_master', [
        ['id' => 1, 'company_id' => 11, 'title' => 'mr.', 'contact_name' => 'Priya Shah', 'email' => 'Priya@Aadhar.test', 'mobile' => '98200 12345', 'contact_type' => 'finance'],
        ['id' => 2, 'company_id' => 10, 'title' => 'Dr', 'contact_name' => 'Priya S', 'email' => 'priya@aadhar.test', 'mobile' => null],
        ['id' => 3, 'company_id' => 10, 'title' => null, 'contact_name' => 'Nobody', 'email' => 'not-an-email', 'mobile' => '123'],
        ['id' => 4, 'company_id' => 10, 'title' => null, 'contact_name' => null, 'email' => 'accounts@aadhar.test', 'mobile' => null],
    ]);
});

test('companies, GSTINs, addresses and contacts come over with duplicates merged and gaps filled', function () {
    $this->artisan('legacy:import', ['area' => 'companies'])->assertSuccessful();

    // The same CIN entered twice becomes one company; the copy resolves to it.
    $aadhar = Company::query()->where('legacy_id', 10)->sole();
    expect(Company::query()->count())->toBe(3)
        ->and(LegacyAlias::query()->where('legacy_id', 11)->value('target_id'))->toBe($aadhar->id)
        ->and($aadhar->name)->toBe('Aadhar Housing Finance Limited')
        ->and($aadhar->entity_type)->toBe(EntityType::Company)
        ->and($aadhar->company_class)->toBe(CompanyClass::Private)
        ->and($aadhar->formerly_known_as)->toBeNull()
        ->and($aadhar->pan)->toBe('AAACA1234C'); // taken from its GSTIN

    $ramesh = Company::query()->where('legacy_id', 12)->sole();
    expect($ramesh->entity_type)->toBe(EntityType::Other)->and($ramesh->cin)->toBeNull()->and($ramesh->pan)->toBeNull();

    // A GSTIN of the duplicate lands on the merged company; one with a bad check digit is rejected.
    expect(CompanyGstin::query()->sole()->company_id)->toBe($aadhar->id);

    $billing = CompanyAddress::query()->where('legacy_id', 1)->sole();
    expect($billing->city)->toBe('Mumbai')
        ->and($billing->pincode)->toBe('400069')
        ->and($billing->company_gstin_id)->toBe(CompanyGstin::query()->sole()->id)
        ->and($billing->billing_name)->toBeNull(); // same as the company name
    expect(CompanyAddress::query()->where('legacy_id', 2)->exists())->toBeFalse();

    $registered = CompanyAddress::query()->where('company_id', $aadhar->id)->where('type', AddressType::Registered)->sole();
    expect($registered->pincode)->toBe('400069')->and($registered->line1)->toContain('Natraj');

    // Contacts: an email is kept once per company, mobiles are cleaned, unreachable contacts rejected.
    $priya = CompanyContact::query()->where('legacy_id', 1)->sole();
    expect($priya->email)->toBe('priya@aadhar.test')
        ->and($priya->mobile)->toBe('9820012345')
        ->and($priya->salutation)->toBe('Mr')
        ->and($priya->contact_type_id)->toBe(ContactType::query()->value('id'))
        // #2 repeats #1's email and has no mobile, so nothing is left to reach them by.
        ->and(CompanyContact::query()->where('legacy_id', 2)->exists())->toBeFalse()
        ->and(CompanyContact::query()->where('legacy_id', 3)->exists())->toBeFalse();

    // An unnamed mailbox contact is kept, named after its email.
    expect(CompanyContact::query()->where('legacy_id', 4)->value('name'))->toBe('accounts@aadhar.test');

    // An address with no company id is placed through the transaction billed to it, and its link
    // to the duplicate GSTIN row resolves to the first copy.
    $branch = CompanyAddress::query()->where('legacy_id', 3)->sole();
    expect($branch->company_id)->toBe($aadhar->id)->and($branch->company_gstin_id)->toBe(CompanyGstin::query()->sole()->id);

    // Re-running changes nothing.
    $this->artisan('legacy:import', ['area' => 'companies'])->assertSuccessful();
    expect(Company::query()->count())->toBe(3)
        ->and(CompanyGstin::query()->count())->toBe(1)
        ->and(CompanyAddress::query()->count())->toBe(3)
        ->and(CompanyContact::query()->count())->toBe(2);
});
