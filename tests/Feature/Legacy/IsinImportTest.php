<?php

use App\Enums\AllotmentKind;
use App\Enums\CouponType;
use App\Enums\DayCount;
use App\Enums\HolidayConvention;
use App\Enums\IsinPaymentKind;
use App\Enums\IsinPaymentStatus;
use App\Enums\Listing;
use App\Enums\PaymentFrequency;
use App\Enums\RedemptionBasis;
use App\Models\DealIsin;
use App\Models\DocumentFile;
use App\Models\IsinAllotment;
use App\Models\IsinPayment;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    legacySchema();

    legacyRows('master_product', [['id' => 4, 'name' => 'Debenture Trustee', 'code' => 'DEB']]);
    legacyRows('users', [['id' => 111, 'name' => 'Ops Officer', 'emp_code' => '111', 'email' => 'ops@b.test', 'password' => bcrypt('x')]]);
    legacyRows('master_cin', [['id' => 10, 'cin' => 'U65999MH2010PTC123456', 'company_name' => 'Aadhar Housing Finance Limited']]);
    legacyRows('transaction_status_master', [['id' => 10, 'status' => 'Live']]);
    legacyRows('transaction', [
        ['id' => 5001, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'company_id' => 10, 'listed_unlisted' => 'Listed', 'secured' => 'Secured',
            'total_issue_size' => 2000000000, 'status' => 'Live', 'status_id' => 10, 'cl_date' => '2025-05-10', 'created_by' => 111],
    ]);
    legacyRows('upload_file', [
        ['id' => 7501, 'name' => 'NSDL credit.pdf', 'path' => 'isin/credit.pdf', 'created_by' => 111],
        ['id' => 7502, 'name' => 'Interest UTR.pdf', 'path' => 'isin/utr.pdf'],
    ]);

    legacyRows('mon_payment_schedule_new', [
        ['id' => 1, 'con_id' => 5001, 'isin' => ' ine471x07014', 'seriesname' => 'Series A', 'listing_status' => 0, 'stock_exchange' => 'BSE', 'depository' => 'NSDL',
            'placement_type' => 1, 'allotmentdate' => '2025-06-15', 'payment_redumtion_date' => '2028-06-15', 'base_coupon_rate' => 'Fixed', 'coupon_desc' => '9.50%',
            'couponrate' => '2', 'interest_frequency' => 2, 'principal_frequency' => 5, 'interest_weekend' => 'succeed',
            'put_date' => 'a:2:{i:0;s:10:"2027-06-15";i:1;s:10:"15/12/2027";}', 'call_date' => 'a:1:{i:0;s:0:"";}', 'user_id' => 111],
        ['id' => 2, 'con_id' => 5001, 'isin' => 'test 29 april'], // a Stack test entry
        ['id' => 3, 'con_id' => 5001, 'isin' => 'INE471X07014', 'seriesname' => '-'], // the same ISIN again
        ['id' => 4, 'con_id' => 5001, 'isin' => 'INE002A01018', 'active' => 0], // removed
        ['id' => 5, 'con_id' => 5001, 'isin' => 'INE244L07366', 'seriesname' => '-', 'base_coupon_rate' => 'Ref Linked', 'coupon_desc' => 'NIFTY 50 INDEX LINKED'],
        ['id' => 6, 'con_id' => 5001, 'isin' => 'INE027208037', 'payment_redumtion_date' => '9999-09-30', 'interest_frequency' => 4], // perpetual
    ]);
    legacyRows('mon_isin_details_new', [
        ['id' => 11, 'con_id' => 5001, 'mon_id' => 1, 'type' => 'IA', 'allotment_date' => '2025-06-15', 'face_value' => 100000, 'qty_issued' => 20000, 'qty_subscribed' => 15000, 'subscription_total' => 1500000000, 'created_by' => 111],
        ['id' => 12, 'con_id' => 5001, 'mon_id' => 1, 'type' => 'AA', 'allotment_date' => '2025-08-01', 'face_value' => 100000, 'qty_subscribed' => 5000],
        ['id' => 13, 'con_id' => 5001, 'mon_id' => 1], // an empty placeholder
        ['id' => 14, 'con_id' => 5001, 'mon_id' => 2, 'type' => 'IA', 'face_value' => 100, 'qty_subscribed' => 1], // its ISIN wasn't imported
    ]);
    legacyRows('mon_payment_listing_new', [['id' => 1, 'con_id' => 5001, 'allotment_id' => 11, 'type' => 'NSDL', 'listing_date' => '2025-06-17', 'upload_id' => '7501']]);
    legacyRows('mon_paymt_interst_sch_new', [
        ['id' => 101, 'con_id' => 5001, 'pay_schedule_id' => 1, 'in_due_date' => '2025-09-15', 'in_status' => 'Delayed Payment', 'in_paid_date' => '2025-09-18',
            'intrest_total_amnt' => '35,13,699.00', 'upload_id' => 7502, 'updated_by' => 111],
        ['id' => 102, 'con_id' => 5001, 'pay_schedule_id' => 1, 'in_due_date' => '2025-12-15', 'in_status' => 'Due'],
        ['id' => 103, 'con_id' => 5001, 'pay_schedule_id' => 1, 'in_due_date' => '2025-12-15', 'in_status' => 'Paid', 'in_paid_date' => '2025-12-15', 'intrest_total_amnt' => '3500000'], // the same date again, settled
        ['id' => 104, 'con_id' => 5001, 'pay_schedule_id' => 1, 'in_due_date' => '2026-03-15', 'in_status' => 'Due', 'active' => 0], // replaced in Stack
        ['id' => 105, 'con_id' => 5001, 'pay_schedule_id' => 1, 'in_due_date' => null],
        ['id' => 106, 'con_id' => 5001, 'pay_schedule_id' => 6, 'in_due_date' => '2026-09-30', 'in_status' => 'Due'],
        ['id' => 107, 'con_id' => 5001, 'pay_schedule_id' => 6, 'in_due_date' => '9999-09-30', 'in_status' => 'Due'],
    ]);
    legacyRows('mon_paymt_prin_sch_new', [
        ['id' => 201, 'con_id' => 5001, 'pay_schedule_id' => 1, 'due_date' => '2028-06-15', 'status' => 'Redeemed Earlier', 'redemp_type' => 'fr', 'prin_total_amnt' => '2000000000'],
        ['id' => 202, 'con_id' => 5001, 'pay_schedule_id' => 2, 'due_date' => '2028-06-15', 'status' => 'Due'], // test ISIN
    ]);
});

test('ISINs, allotments and schedules come over; test entries, repeats and removed rows are handled', function () {
    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    $deal = Transaction::query()->where('legacy_id', 5001)->sole();
    $ops = User::query()->where('legacy_id', 111)->value('id');

    expect($deal->isins()->orderBy('isin')->pluck('isin')->all())->toBe(['INE027208037', 'INE244L07366', 'INE471X07014']);
    $isin = DealIsin::query()->where('isin', 'INE471X07014')->sole();
    expect($isin->legacy_id)->toBe(1)
        ->and($isin->series_name)->toBe('Series A')
        ->and($isin->listing)->toBe(Listing::Listed)
        ->and($isin->coupon_type)->toBe(CouponType::Fixed)
        ->and($isin->coupon_rate)->toBe('9.5000')
        ->and($isin->day_count)->toBe(DayCount::Actual365)
        ->and($isin->interest_frequency)->toBe(PaymentFrequency::Quarterly)
        ->and($isin->principal_frequency)->toBe(PaymentFrequency::Bullet)
        ->and($isin->holiday_convention)->toBe(HolidayConvention::Following)
        ->and($isin->put_date->toDateString())->toBe('2027-06-15') // the first of Stack's serialised list
        ->and($isin->call_date)->toBeNull()
        ->and($isin->created_by)->toBe($ops);
    $linked = DealIsin::query()->where('isin', 'INE244L07366')->sole();
    expect($linked->coupon_type)->toBe(CouponType::Linked)
        ->and($linked->coupon_rate)->toBeNull()
        ->and($linked->coupon_description)->toBe('NIFTY 50 INDEX LINKED')
        ->and($linked->series_name)->toBeNull();

    [$initial, $tranche] = $isin->allotments()->get()->all();
    expect(IsinAllotment::query()->count())->toBe(2)
        ->and($initial->kind)->toBe(AllotmentKind::Initial)
        ->and($initial->amount)->toBe('1500000000.00')
        ->and($initial->credit_depository)->toBe('NSDL')
        ->and($initial->credited_on->toDateString())->toBe('2025-06-17')
        ->and($initial->files()->sole()->original_name)->toBe('NSDL credit.pdf')
        ->and($tranche->kind)->toBe(AllotmentKind::Additional)
        ->and($tranche->amount)->toBe('500000000.00'); // worked out when Stack has no total

    $interest = $isin->payments()->where('kind', IsinPaymentKind::Interest)->get();
    expect($interest->map(fn (IsinPayment $p) => [$p->due_on->toDateString(), $p->status])->all())->toBe([
        ['2025-09-15', IsinPaymentStatus::Paid],
        ['2025-12-15', IsinPaymentStatus::Paid], // the repeat's settled status wins
    ]);
    expect($interest[0]->amount)->toBe('3513699.00')
        ->and($interest[0]->paid_on->toDateString())->toBe('2025-09-18')
        ->and($interest[0]->remark)->toBe('Delayed payment (Stack)')
        ->and($interest[0]->recorded_by)->toBe($ops)
        ->and($interest[0]->files()->sole()->original_name)->toBe('Interest UTR.pdf')
        ->and($interest[1]->amount)->toBe('3500000.00');

    $principal = $isin->payments()->where('kind', IsinPaymentKind::Principal)->sole();
    expect($principal->status)->toBe(IsinPaymentStatus::RedeemedEarly)
        ->and($principal->redemption_basis)->toBe(RedemptionBasis::Full)
        ->and(IsinPayment::query()->count())->toBe(4);

    // A perpetual ISIN: Stack's 9999 maturity is left blank and its placeholder dates are dropped.
    $perpetual = DealIsin::query()->where('isin', 'INE027208037')->sole();
    expect($perpetual->maturity_date)->toBeNull()
        ->and($perpetual->payments()->pluck('due_on')->map->toDateString()->all())->toBe(['2026-09-30']);

    // Running it again changes nothing.
    $counts = fn () => [DealIsin::query()->count(), IsinAllotment::query()->count(), IsinPayment::query()->count(), DocumentFile::query()->count()];
    $before = $counts();
    $this->artisan('legacy:import', ['area' => 'isin'])->assertSuccessful();
    expect($counts())->toBe($before);
});
