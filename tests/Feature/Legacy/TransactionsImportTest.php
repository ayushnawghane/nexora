<?php

use App\Enums\DealStatus;
use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Enums\Recipient;
use App\Enums\StatusRequestState;
use App\Enums\TransactionStatus;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\DealStatusRequest;
use App\Models\NumberSequence;
use App\Models\Pincode;
use App\Models\State;
use App\Models\Transaction;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    legacySchema();
    Pincode::query()->create(['pincode' => '400069', 'city' => 'Mumbai', 'state_id' => State::query()->where('gst_code', '27')->value('id')]);

    legacyRows('master_product', [['id' => 4, 'name' => 'Debenture Trustee', 'code' => 'DEB']]);
    legacyRows('users', [['id' => 111, 'name' => 'Ops Officer', 'emp_code' => '111', 'email' => 'ops@b.test', 'password' => bcrypt('x')]]);
    legacyRows('master_cin', [['id' => 10, 'cin' => 'U65999MH2010PTC123456', 'company_name' => 'Aadhar Housing Finance Limited']]);
    legacyRows('company_address_master', [['id' => 1, 'company_id' => 10, 'billing_address' => 'Andheri East', 'pincode' => '400069']]);
    legacyRows('company_contact_master', [
        ['id' => 1, 'company_id' => 10, 'contact_name' => 'Priya Shah', 'email' => 'priya@aadhar.test'],
        ['id' => 2, 'company_id' => 10, 'contact_name' => 'Accounts', 'email' => 'accounts@aadhar.test'],
    ]);
    legacyRows('transaction_status_master', [
        ['id' => 6, 'status' => 'Preliminary'], ['id' => 10, 'status' => 'Live'], ['id' => 11, 'status' => 'Requested Redemption'], ['id' => 13, 'status' => 'Redeemed'],
    ]);
    legacyRows('master_frequency', [
        ['id' => 2, 'type' => 1, 'flag' => 1, 'frequency_id' => 2, 'frequency' => 'Of Issue Size'],
        ['id' => 6, 'type' => 1, 'flag' => 2, 'frequency_id' => 1, 'frequency' => 'One Time'],
        ['id' => 7, 'type' => 1, 'flag' => 3, 'frequency_id' => 1, 'frequency' => 'Engagement Letter Date'],
        ['id' => 10, 'type' => 1, 'flag' => 4, 'frequency_id' => 1, 'frequency' => 'Payable in Advance '],
        ['id' => 18, 'type' => 2, 'flag' => 2, 'frequency_id' => 1, 'frequency' => 'Annually'],
        ['id' => 21, 'type' => 2, 'flag' => 2, 'frequency_id' => 12, 'frequency' => 'Monthly'],
        ['id' => 24, 'type' => 2, 'flag' => 3, 'frequency_id' => 2, 'frequency' => 'DTA/BTA Execution Date'],
        ['id' => 28, 'type' => 2, 'flag' => 4, 'frequency_id' => 2, 'frequency' => 'Payable in Arrears on Pro Rata Basis'],
    ]);

    legacyRows('transaction', [
        // A live deal with fees, billed periods, two letter versions, a history and billing.
        ['id' => 5001, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'deal_id' => null, 'company_id' => 10, 'listed_unlisted' => 'Listed', 'issue_type' => 'Private Placement',
            'secured' => 'Secured', 'rated' => 'Yes', 'issue_pool_size' => 0, 'total_issue_size' => 2000000000, 'tenure_months' => 36, 'status' => 'Live', 'status_id' => 10,
            'cl_date' => '2025-05-10', 'originated_by' => 'BD', 'created_by' => 111, 'is_schedule_verified' => 1],
        // Waiting in Stack for its redemption to be approved.
        ['id' => 5002, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/41', 'company_id' => 10, 'issue_pool_size' => 500000000, 'status' => 'Requested Redemption', 'status_id' => 11, 'cl_date' => '2025-06-01', 'created_by' => 111],
        ['id' => 5003, 'product_id' => 4, 'cl_no' => null, 'company_id' => 10, 'status' => 'Draft', 'status_id' => 1, 'created_by' => 111],
        ['id' => 5004, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/42', 'company_id' => 10, 'status' => 'Live', 'is_deleted' => 1],
        ['id' => 5005, 'product_id' => 14, 'cl_no' => 'BTL/SEZ-DA/EL/25-26/55', 'company_id' => 10, 'status' => 'Live'], // another product: only its EL number counts
    ]);
    legacyRows('issue_details', [['con_id' => 5001, 'issue_pool_size' => 0, 'ncd_issue' => 1500000000, 'ocd_issue' => 500000000]]);
    legacyRows('transaction_contact', [
        ['con_id' => 5001, 'contact_id' => 1, 'recipient' => 'to', 'billing_recipient' => null],
        ['con_id' => 5001, 'contact_id' => 2, 'recipient' => 'na', 'billing_recipient' => 'to'],
    ]);
    legacyRows('acceptance_fees', [['con_id' => 5001, 'accept_amount_type' => 1, 'accpt_amount' => 65000, 'accpt_lavy' => 2, 'accpt_frequency' => 1, 'accpt_effect_day' => 1, 'accpt_payment_term' => 1]]);
    legacyRows('service_fees', [
        ['con_id' => 5001, 'serv_amount_type' => 1, 'service_amount' => 120000, 'service_frequency' => 21, 'service_eff_day' => 2, 'service_pay_term' => 2,
            'service_escalation' => 1, 'service_escalation_type' => 2, 'service_escalation_fees' => 5, 'service_years' => 3],
        ['con_id' => 5002, 'serv_amount_type' => 1, 'service_amount' => null], // no amount in Stack
    ]);
    legacyRows('master_cl_schedules', [
        ['id' => 901, 'con_id' => 5001, 'tab_id' => 2, 'fy' => 'FY 2025-26', 'bill_date' => '2025-06-01', 'from_date' => '2025-06-01', 'to_date' => '2025-06-30', 'no_of_days' => 30, 'no_of_days_in_year' => 365, 'base_amount' => 10000, 'applicable_fees' => 10000],
        ['id' => 902, 'con_id' => 5001, 'tab_id' => 11, 'fy' => 'FY 2025-26', 'from_date' => '2025-06-01', 'to_date' => '2025-06-30', 'base_amount' => 999, 'applicable_fees' => 999],
    ]);
    legacyRows('el_versioning', [
        ['id' => 71, 'con_id' => 5001, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'revised_count' => 0, 'upload_id' => 3001, 'created_by' => 111],
        ['id' => 72, 'con_id' => 5001, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'revised_count' => 1, 'upload_id' => 3002, 'created_by' => 111],
        ['id' => 73, 'con_id' => 5002, 'cl_no' => 'BTL/DEB/EL/25-26/3830', 'revised_count' => 0, 'upload_id' => 3003, 'created_by' => 111], // Stack data error
    ]);
    legacyRows('transaction_billing_address', [['con_id' => 5001, 'address_id' => 1]]);
    legacyRows('transaction_status_log', [
        ['tran_id' => 5001, 'status_id' => 6, 'created_by' => 111, 'created_date' => '2025-05-10 10:00:00'],
        ['tran_id' => 5001, 'status_id' => 10, 'created_by' => 111, 'created_date' => '2025-07-01 10:00:00'],
    ]);
    legacyRows('update_status', [['con_id' => 5002, 'previous_status' => '10', 'new_status' => '13', 'redemption_date' => '2025-09-30', 'upload_id' => 4001, 'created_by' => 111]]);
});

test('DT deals come over with issue, contacts, fees, billed periods, letters, history and billing', function () {
    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    expect(Transaction::query()->count())->toBe(3); // the deleted deal and the other product's deal are left out

    $live = Transaction::query()->where('legacy_id', 5001)->sole();
    expect($live->status)->toBe(TransactionStatus::Active)
        ->and($live->deal_status)->toBe(DealStatus::Live)
        ->and($live->deal_status_since->toDateString())->toBe('2025-07-01')
        ->and($live->el_number)->toBe('BTL/DEB/EL/25-26/40')
        ->and($live->el_date->toDateString())->toBe('2025-05-10')
        ->and($live->deal_code)->toBe('DEB/25-26/5001')
        ->and($live->company_id)->toBe(Company::query()->where('legacy_id', 10)->value('id'))
        ->and($live->isScheduleVerified())->toBeTrue();

    // Issue size taken from the deal when the issue row says 0; the instrument split kept.
    expect($live->issueDetail->total_issue_size)->toBe('2000000000.00')
        ->and($live->issueDetail->is_rated)->toBeTrue()
        ->and($live->instruments()->pluck('base_amount', 'instrument')->all())->toBe(['ncd' => '1500000000.00', 'ocd' => '500000000.00']);

    // Only "to"/"cc" people are letter contacts; billing recipients go to the deal's billing.
    expect($live->contacts()->sole()->recipient)->toBe(Recipient::To)
        ->and($live->billing->company_address_id)->toBe(CompanyAddress::query()->where('legacy_id', 1)->value('id'))
        ->and($live->billingContacts()->pluck('company_contacts.id')->all())->toBe([CompanyContact::query()->where('legacy_id', 2)->value('id')]);

    // Fee options read from either kind of Stack code; billed periods kept as billed.
    $acceptance = $live->feeLines()->where('kind', FeeKind::Acceptance)->sole();
    $service = $live->feeLines()->where('kind', FeeKind::Service)->sole();
    expect($acceptance->frequency)->toBe(FeeFrequency::OneTime)
        ->and($acceptance->start_reference)->toBe(FeeStartReference::ElDate)
        ->and($acceptance->start_date->toDateString())->toBe('2025-05-10')
        ->and($service->amount_type)->toBe(FeeAmountType::Fixed)
        ->and($service->frequency)->toBe(FeeFrequency::Monthly)
        ->and($service->start_reference)->toBe(FeeStartReference::DtaExecution)
        ->and($service->start_date->toDateString())->toBe('2025-06-01') // from its first billed period
        ->and($service->timing)->toBe(FeeTiming::Arrears)
        ->and($service->escalation_type)->toBe(EscalationType::Percent)
        ->and($service->periods()->sole()->amount)->toBe('10000.00');

    // Letter versions arrive without PDFs, numbered in order.
    $letters = $live->engagementLetters()->get();
    expect($letters->pluck('version')->all())->toBe([2, 1])
        ->and($letters->first()->hasPdf())->toBeFalse()
        ->and($letters->first()->legacy_upload_id)->toBe(3002);
    $this->get("/transactions/{$live->ulid}/letters/1")->assertStatus(302); // fine to call without a user: redirected to sign in

    expect($live->statusChanges()->orderBy('id')->pluck('to_status')->map->value->all())->toBe(['preliminary', 'live']);

    // The pending redemption arrives as Live with an open request for Management and Accounts.
    $pending = Transaction::query()->where('legacy_id', 5002)->sole();
    $request = DealStatusRequest::query()->where('transaction_id', $pending->id)->sole();
    expect($pending->deal_status)->toBe(DealStatus::Live)
        ->and($request->status)->toBe(StatusRequestState::Open)
        ->and($request->to_status)->toBe(DealStatus::Redeemed)
        ->and($request->effective_on->toDateString())->toBe('2025-09-30')
        ->and($request->teams())->toHaveCount(2);

    expect(Transaction::query()->where('legacy_id', 5003)->value('status'))->toBe(TransactionStatus::Draft);

    // The EL sequence continues after the highest number Stack used, any product, any letter version.
    expect(NumberSequence::query()->where('key', 'el:25-26')->value('last_value'))->toBe(3830);

    // Running it again changes nothing.
    $this->artisan('legacy:import', ['area' => 'transactions'])->assertSuccessful();
    expect(Transaction::query()->count())->toBe(3)
        ->and($live->engagementLetters()->count())->toBe(2)
        ->and($live->statusChanges()->count())->toBe(2)
        ->and(DealStatusRequest::query()->count())->toBe(1);
});

test('letter PDFs are attached from a copy of the Stack uploads folder when one is configured', function () {
    $root = sys_get_temp_dir().'/nexora-stack-uploads-'.uniqid();
    mkdir("{$root}/execution/el", 0777, true);
    file_put_contents("{$root}/execution/el/5001_offer_letter_0.pdf", '%PDF-1.4 legacy letter');
    legacyRows('upload_file', [
        ['id' => 3001, 'path' => 'execution/el/5001_offer_letter_0.pdf'],
        ['id' => 3002, 'path' => 'execution/el/missing.pdf'],
    ]);

    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();
    $live = Transaction::query()->where('legacy_id', 5001)->sole();
    expect($live->engagementLetters()->where('version', 1)->sole()->hasPdf())->toBeFalse(); // no copy configured yet

    config(['legacy.uploads_path' => $root]);
    $this->artisan('legacy:import', ['area' => 'transactions'])
        ->expectsOutputToContain('PDF not found in the uploads copy: execution/el/missing.pdf')
        ->assertSuccessful();

    $v1 = $live->engagementLetters()->where('version', 1)->sole();
    expect($v1->hasPdf())->toBeTrue()
        ->and(Storage::disk('local')->get($v1->pdf_path))->toBe('%PDF-1.4 legacy letter')
        ->and($live->engagementLetters()->where('version', 2)->sole()->hasPdf())->toBeFalse();

    unlink("{$root}/execution/el/5001_offer_letter_0.pdf");
    rmdir("{$root}/execution/el");
    rmdir("{$root}/execution");
    rmdir($root);
});
