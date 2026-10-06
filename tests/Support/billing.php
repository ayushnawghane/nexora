<?php

/*
 * Shared helpers for billing tests.
 */

use App\Enums\DealStatus;
use App\Enums\EscalationType;
use App\Enums\FeeAmountType;
use App\Enums\FeeBasis;
use App\Enums\FeeFrequency;
use App\Enums\FeeKind;
use App\Enums\FeeStartReference;
use App\Enums\FeeTiming;
use App\Models\CompanyAddress;
use App\Models\CompanyContact;
use App\Models\CompanyGstin;
use App\Models\FeeLine;
use App\Models\Setting;
use App\Models\State;
use App\Models\Transaction;
use App\Models\User;
use Database\Factories\CompanyGstinFactory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Database\Seeders\TaxRateSeeder;

/**
 * A live deal billed to a Maharashtra address (Beacon's home state, so CGST + SGST), optionally under
 * a GSTIN, with one billing contact, an acceptance fee of ₹1,00,000 and an annual service fee of
 * ₹2,00,000 billed in advance (periods FY 25-26 to FY 27-28, the first prorated from 1 April 2025).
 */
function billableDeal(bool $withGstin = true, string $stateCode = '27'): Transaction
{
    test()->seed([StateSeeder::class, TaxRateSeeder::class]);
    Setting::put(Setting::BEACON_GSTIN, CompanyGstinFactory::gstinFor('27', 'AAGCB5444C'));

    $deal = Transaction::factory()->deal(DealStatus::Live)->create();
    $state = State::query()->where('gst_code', $stateCode)->value('id');
    $gstin = $withGstin ? CompanyGstin::factory()->for($deal->company)->create(['state_id' => $state, 'gstin' => CompanyGstinFactory::gstinFor($stateCode, $deal->company->pan)]) : null;
    $address = CompanyAddress::factory()->for($deal->company)->create(['state_id' => $state, 'company_gstin_id' => $gstin?->id, 'billing_name' => 'Issuer Finance Ltd']);
    $deal->billing()->create(['company_address_id' => $address->id, 'company_gstin_id' => $gstin?->id, 'place_of_supply_state_id' => $state, 'updated_by' => $deal->created_by]);
    $deal->billingContacts()->attach(CompanyContact::factory()->for($deal->company)->create(['email' => 'accounts@issuer.test']));

    $acceptance = feeLine($deal, FeeKind::Acceptance, '100000', FeeFrequency::OneTime);
    $acceptance->periods()->create(['sequence' => 1, 'from_date' => '2025-04-01', 'to_date' => '2025-04-01', 'bill_date' => '2025-04-01', 'days' => 1, 'days_in_year' => 365, 'base_amount' => '100000', 'amount' => '100000', 'financial_year' => '25-26', 'prorated' => false]);
    $service = feeLine($deal, FeeKind::Service, '200000', FeeFrequency::Annual);
    foreach ([['2025-04-01', '2026-03-31', '25-26'], ['2026-04-01', '2027-03-31', '26-27'], ['2027-04-01', '2028-03-31', '27-28']] as $i => [$from, $to, $fy]) {
        $service->periods()->create(['sequence' => $i + 1, 'from_date' => $from, 'to_date' => $to, 'bill_date' => $from, 'days' => 365, 'days_in_year' => 365, 'base_amount' => '200000', 'amount' => '200000', 'financial_year' => $fy, 'prorated' => false]);
    }

    return $deal;
}

function feeLine(Transaction $deal, FeeKind $kind, string $amount, FeeFrequency $frequency): FeeLine
{
    return $deal->feeLines()->create([
        'kind' => $kind, 'amount_type' => FeeAmountType::Fixed, 'amount' => $amount, 'annual_amount' => $amount,
        'basis' => FeeBasis::IssueSize, 'frequency' => $frequency, 'start_reference' => FeeStartReference::CustomDate,
        'start_date' => '2025-04-01', 'timing' => FeeTiming::Advance, 'escalation_type' => EscalationType::None,
    ]);
}

/** A user (not signed in) with the given billing permissions. */
function billingUser(array $permissions = ['billing.view', 'billing.raise', 'billing.approve', 'billing.receipts']): User
{
    test()->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['deals.view', ...$permissions]);

    return $user;
}
