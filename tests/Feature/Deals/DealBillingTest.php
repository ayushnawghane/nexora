<?php

use App\Enums\DealStatus;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\Setting;
use App\Models\State;
use App\Models\Transaction;
use Database\Factories\CompanyGstinFactory;
use Database\Seeders\StateSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(StateSeeder::class);
    $this->deal = Transaction::factory()->deal()->create();
    $this->company = $this->deal->company;
    $this->maharashtra = State::query()->where('gst_code', '27')->value('id');
    $this->karnataka = State::query()->where('gst_code', '29')->value('id');
    $this->contact = CompanyContact::factory()->for($this->company)->create(['email' => 'cfo@issuer.test']);
});

function saveBilling(Transaction $deal, array $data)
{
    return test()->put("/deals/{$deal->ulid}/billing", $data);
}

test('an address with a GSTIN bills under that GSTIN, and its state is the place of supply', function () {
    dealUser(['deals.edit']);
    $gstin = CompanyGstin::factory()->for($this->company)->create(['state_id' => $this->karnataka]);
    $address = CompanyAddress::factory()->for($this->company)->create(['state_id' => $this->karnataka, 'pincode' => '560001', 'company_gstin_id' => $gstin->id]);

    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id]])
        ->assertRedirect("/deals/{$this->deal->ulid}?tab=billing")
        ->assertSessionHasNoErrors();

    $billing = $this->deal->billing()->sole();
    expect($billing->company_gstin_id)->toBe($gstin->id)
        ->and($billing->place_of_supply_state_id)->toBe($this->karnataka)
        ->and($this->deal->billingContacts()->pluck('company_contacts.id')->all())->toBe([$this->contact->id]);
});

test('without a GSTIN the address state is the place of supply; tax mode follows Beacon\'s home state', function () {
    Setting::put(Setting::BEACON_GSTIN, CompanyGstinFactory::gstinFor('27', 'AAACB1234C'));
    dealUser(['deals.edit']);
    $address = CompanyAddress::factory()->for($this->company)->create(['state_id' => $this->maharashtra]);

    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id]])->assertSessionHasNoErrors();
    expect($this->deal->billing()->sole()->place_of_supply_state_id)->toBe($this->maharashtra);

    $this->get("/deals/{$this->deal->ulid}?tab=billing")->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Deals/Show')
        ->where('billing.current.tax_mode', 'intra')
        ->where('billing.current.gstin', null)
        ->where('billing.home_state_configured', true));
});

test('the GSTIN must match the address and both must be the company\'s own active records', function () {
    dealUser(['deals.edit']);
    // Entity numbers 1 and 2 fixed, so the two GSTINs can never come out the same.
    $karnatakaGstin = CompanyGstin::factory()->for($this->company)->create(['state_id' => $this->karnataka, 'gstin' => CompanyGstinFactory::gstinFor('29', $this->company->pan, '1')]);
    $mumbai = CompanyAddress::factory()->for($this->company)->create(['state_id' => $this->maharashtra]);
    $tied = CompanyAddress::factory()->for($this->company)->create(['state_id' => $this->karnataka, 'pincode' => '560001', 'company_gstin_id' => $karnatakaGstin->id]);
    $otherGstin = CompanyGstin::factory()->for($this->company)->create(['state_id' => $this->karnataka, 'gstin' => CompanyGstinFactory::gstinFor('29', $this->company->pan, '2')]);
    $foreign = CompanyAddress::factory()->create();
    $inactive = CompanyAddress::factory()->for($this->company)->create(['is_active' => false]);

    saveBilling($this->deal, ['company_address_id' => $mumbai->id, 'company_gstin_id' => $karnatakaGstin->id, 'contact_ids' => [$this->contact->id]])
        ->assertSessionHasErrors(['company_gstin_id' => 'The GSTIN is registered in Karnataka but the address is in Maharashtra. Pick an address in the GSTIN\'s state.']);
    saveBilling($this->deal, ['company_address_id' => $tied->id, 'company_gstin_id' => $otherGstin->id, 'contact_ids' => [$this->contact->id]])
        ->assertSessionHasErrors('company_gstin_id');
    saveBilling($this->deal, ['company_address_id' => $foreign->id, 'contact_ids' => [$this->contact->id]])->assertSessionHasErrors('company_address_id');
    saveBilling($this->deal, ['company_address_id' => $inactive->id, 'contact_ids' => [$this->contact->id]])->assertSessionHasErrors('company_address_id');
    expect($this->deal->billing()->exists())->toBeFalse();
});

test('billing contacts must be the company\'s and at least one needs an email', function () {
    dealUser(['deals.edit']);
    $address = CompanyAddress::factory()->for($this->company)->create();
    $noEmail = CompanyContact::factory()->for($this->company)->create(['email' => null, 'mobile' => '9876543210']);
    $stranger = CompanyContact::factory()->create();

    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => []])->assertSessionHasErrors('contact_ids');
    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$noEmail->id]])
        ->assertSessionHasErrors(['contact_ids' => 'At least one billing contact needs an email address.']);
    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id, $stranger->id]])->assertSessionHasErrors('contact_ids');

    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id, $noEmail->id]])->assertSessionHasNoErrors();
    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id]])->assertSessionHasNoErrors();
    expect($this->deal->billingContacts()->count())->toBe(1);
});

test('billing needs the edit permission and an open deal', function () {
    $address = CompanyAddress::factory()->for($this->company)->create();
    dealUser();
    saveBilling($this->deal, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id]])->assertForbidden();

    dealUser(['deals.edit']);
    $closed = Transaction::factory()->deal(DealStatus::Redeemed)->create(['company_id' => $this->company->id]);
    saveBilling($closed, ['company_address_id' => $address->id, 'contact_ids' => [$this->contact->id]])->assertForbidden();
});
