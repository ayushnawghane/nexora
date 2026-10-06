<?php

use App\Enums\InvoiceKind;
use App\Enums\InvoiceLineKind;
use App\Enums\InvoiceStatus;
use App\Models\DealExpense;
use App\Models\Invoice;
use App\Models\InvoiceReceipt;
use App\Models\NumberSequence;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StateSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->seed([PermissionSeeder::class, StateSeeder::class]);
    legacySchema();

    legacyRows('master_product', [['id' => 4, 'name' => 'Debenture Trustee', 'code' => 'DEB']]);
    legacyRows('users', [['id' => 111, 'name' => 'Accounts Officer', 'emp_code' => '111', 'email' => 'acc@b.test', 'password' => bcrypt('x')]]);
    legacyRows('master_cin', [['id' => 10, 'cin' => 'U65999MH2010PTC123456', 'company_name' => 'Aadhar Housing Finance Limited']]);
    legacyRows('transaction_status_master', [['id' => 10, 'status' => 'Live']]);
    legacyRows('transaction', [
        ['id' => 5001, 'product_id' => 4, 'cl_no' => 'BTL/DEB/EL/25-26/40', 'company_id' => 10, 'listed_unlisted' => 'Listed', 'secured' => 'Secured',
            'total_issue_size' => 2000000000, 'status' => 'Live', 'status_id' => 10, 'cl_date' => '2025-05-10', 'created_by' => 111],
        ['id' => 6001, 'product_id' => 1, 'cl_no' => 'BTL/SEC/1', 'company_id' => 10, 'status' => 'Live', 'status_id' => 10, 'cl_date' => '2025-05-10'], // another product
    ]);

    $bill = fn (array $row) => array_merge([
        'con_id' => 5001, 'billing_name' => 'Aadhar Housing Finance Ltd', 'billing_address' => 'Mumbai 400001', 'state' => 'Maharashtra',
        'gst_no' => '27AABCA1234B1ZH', 'user_id' => 111, 'is_gst_apply' => 0, 'bill_start_date' => '2025-04-01', 'bill_end_date' => '2026-03-31',
        'accep_manually' => 0, 'service_manually' => 0, 'other_amnt' => 0, 'ope_amount' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0,
    ], $row);
    legacyRows('tblpushbillingdata', [
        // Proforma for acceptance + service, its tax invoice and a credit note against that.
        $bill(['id' => 1, 'invoice_type' => 'Proforma', 'invoice_no' => 'BTL/2526/INV012', 'status' => '2', 'created_date' => '2025-05-12 10:00:00',
            'accep_manually' => 100000, 'service_manually' => 200000, 'sub_total' => 300000, 'cgst' => 27000, 'sgst' => 27000, 'grand_total' => 354000]),
        $bill(['id' => 2, 'invoice_type' => 'Tax', 'invoice_no' => 'BTL/2526/TAX009', 'proforma_id' => 'BTL/2526/INV012', 'status' => '4', 'created_date' => '2025-05-20 11:00:00',
            'accep_manually' => 100000, 'service_manually' => 200000, 'sub_total' => 300000, 'cgst' => 27000, 'sgst' => 27000, 'grand_total' => 354000]),
        $bill(['id' => 3, 'invoice_type' => 'Credit', 'invoice_no' => 'BTL/2526/CN004', 'proforma_id' => 'BTL/2526/TAX009', 'status' => '6', 'created_date' => '2025-06-01 09:00:00',
            'service_manually' => 50000, 'brk_amnt' => 50000, 'sub_total' => 50000, 'cgst' => 4500, 'sgst' => 4500, 'grand_total' => 59000]),
        // A cancelled proforma, and a second proforma reusing a number (Stack's counter had no lock).
        $bill(['id' => 4, 'invoice_type' => 'Proforma', 'invoice_no' => 'BTL/2526/INV013', 'status' => '13', 'is_cancelled' => 1, 'cancelled_reason' => 'Wrong client',
            'created_date' => '2025-06-02 09:00:00', 'other_amnt' => 10000, 'other_desc' => 'Inspection fee', 'sub_total' => 10000, 'igst' => 1800, 'grand_total' => 11800]),
        $bill(['id' => 5, 'invoice_type' => 'Proforma', 'invoice_no' => 'BTL/2526/INV012', 'status' => '2', 'created_date' => '2025-06-03 09:00:00',
            'other_amnt' => 5000, 'sub_total' => 5000, 'cgst' => 450, 'sgst' => 450, 'grand_total' => 5900]),
        // A debit note: Stack's reimbursement of out-of-pocket expenses.
        $bill(['id' => 6, 'invoice_type' => 'Debit', 'invoice_no' => 'BTL/2627/DN003', 'status' => '7', 'created_date' => '2026-04-10 09:00:00', 'sub_total' => 0, 'ope_amount' => 8000, 'grand_total' => 8000]),
        // Inactive (replaced in Stack), another product's bill, and Stack's odd "other" type.
        $bill(['id' => 7, 'invoice_type' => 'Proforma', 'invoice_no' => 'BTL/2526/INV099', 'is_active' => 0, 'sub_total' => 1, 'grand_total' => 1]),
        $bill(['id' => 8, 'con_id' => 6001, 'invoice_type' => 'Proforma', 'invoice_no' => 'BTL/2526/INV500', 'sub_total' => 1, 'grand_total' => 1]),
        $bill(['id' => 9, 'invoice_type' => 'other', 'invoice_no' => 'X1', 'sub_total' => 1, 'grand_total' => 1]),
    ]);
    legacyRows('tblpushbilling_conid_mapper', [
        ['con_id' => 5001, 'tblpushbill_id' => 1, 'taxinvoice_id' => 2, 'credit_id' => 3],
        ['con_id' => 5001, 'debit_id' => 6],
        ['con_id' => 6001, 'tblpushbill_id' => 8],
    ]);
    legacyRows('tblpushbilling_hsn_mapper', [['tblpushbill_id' => 1, 'hsn' => '997156']]);
    legacyRows('tbl_einvoice_details', [['bill_id' => 2, 'irn' => str_repeat('ab', 32), 'ack_no' => '112510000000001', 'ack_date' => '2025-05-20 11:05:00']]);
    legacyRows('payment_update', [
        ['id' => 901, 'tbl_pushbilling_id' => 1, 'received_amount' => 270000, 'tds_amount' => 30000, 'recieved_amt_date' => '2025-05-18', 'utr_no' => 'HDFC/NEFT/123', 'created_by' => 111],
        ['id' => 902, 'tbl_pushbilling_id' => 2, 'received_amount' => 1000, 'tds_amount' => 0, 'tds_date' => '2025-05-25', 'utr_no' => 'cheque no 4411 dated 25 May'], // on the tax invoice, no date received
        ['id' => 903, 'tbl_pushbilling_id' => 6, 'received_amount' => 0, 'tds_amount' => 0, 'recieved_amt_date' => '2026-04-11'], // nothing received
    ]);
    legacyRows('ope_billing_details', [
        ['id' => 801, 'con_id' => 5001, 'bill_id' => 6, 'pocket_amount' => 8000, 'remark' => 'Stamp duty on DTD', 'created_by' => 111, 'created_at' => '2026-04-09 10:00:00'],
        ['id' => 802, 'con_id' => 5001, 'bill_id' => null, 'pocket_amount' => 0],
    ]);
});

test('Stack\'s invoices come over by kind with their chain, statuses, lines, IRNs and payments', function () {
    $this->artisan('legacy:import', ['area' => 'all'])->assertSuccessful();

    $proforma = Invoice::query()->where('legacy_id', 1)->sole();
    $tax = Invoice::query()->where('legacy_id', 2)->sole();
    $credit = Invoice::query()->where('legacy_id', 3)->sole();
    expect($proforma->kind)->toBe(InvoiceKind::Proforma)
        ->and($proforma->status)->toBe(InvoiceStatus::Converted)
        ->and($proforma->sac)->toBe('997156')
        ->and($proforma->financial_year)->toBe('2526')
        ->and($proforma->serial)->toBe(12)
        ->and($proforma->billed_gstin)->toBe('27AABCA1234B1ZH')
        ->and($proforma->cgst_rate)->toBe('9.00')
        ->and($proforma->created_by)->toBe(User::query()->where('legacy_id', 111)->value('id'))
        ->and($proforma->lines()->pluck('kind')->all())->toBe([InvoiceLineKind::Acceptance, InvoiceLineKind::Service])
        ->and($tax->parent_id)->toBe($proforma->id)
        ->and($tax->irn)->toBe(str_repeat('ab', 32))
        ->and($tax->ack_no)->toBe('112510000000001')
        ->and($credit->kind)->toBe(InvoiceKind::CreditNote)
        ->and($credit->parent_id)->toBe($tax->id)
        ->and($credit->total)->toBe('59000.00');

    // Payments: on the proforma; the one Stack put on the tax invoice moves there, dated from TDS.
    $receipts = InvoiceReceipt::query()->orderBy('legacy_id')->get();
    expect($receipts->pluck('invoice_id')->all())->toBe([$proforma->id, $proforma->id])
        ->and($receipts[1]->received_on->toDateString())->toBe('2025-05-25')
        ->and($receipts[1]->utr)->toBeNull()
        ->and($receipts[1]->remark)->toContain('cheque no 4411')
        // 354000 − credit note 59000 − 270000 − 30000 − 1000
        ->and($proforma->fresh()->balance_due)->toBe('0.00');

    $cancelled = Invoice::query()->where('legacy_id', 4)->sole();
    expect($cancelled->status)->toBe(InvoiceStatus::Cancelled)
        ->and($cancelled->cancel_reason)->toBe('Wrong client')
        ->and($cancelled->inter_state)->toBeTrue()
        ->and($cancelled->lines()->sole()->description)->toBe('Inspection fee');
    expect(Invoice::query()->where('legacy_id', 5)->value('number'))->toBe('BTL/2526/INV012-5');

    $bill = Invoice::query()->where('legacy_id', 6)->sole();
    expect($bill->kind)->toBe(InvoiceKind::Reimbursement)
        ->and($bill->gst_applies)->toBeFalse()
        ->and($bill->total)->toBe('8000.00')
        ->and($bill->balance_due)->toBe('8000.00');
    $expense = DealExpense::query()->sole();
    expect($expense->invoice_id)->toBe($bill->id)->and($expense->description)->toBe('Stamp duty on DTD');

    expect(Invoice::query()->whereIn('legacy_id', [7, 8, 9])->exists())->toBeFalse()
        ->and(NumberSequence::query()->where('key', 'invoice:proforma:2526')->value('last_value'))->toBe(13)
        ->and(NumberSequence::query()->where('key', 'invoice:reimbursement:2627')->value('last_value'))->toBe(3);

    // Running it again changes nothing.
    $before = Invoice::query()->orderBy('id')->get()->map->only(['id', 'number', 'status', 'total', 'balance_due', 'updated_at'])->all();
    $this->artisan('legacy:import', ['area' => 'billing'])->assertSuccessful();
    expect(Invoice::query()->orderBy('id')->get()->map->only(['id', 'number', 'status', 'total', 'balance_due', 'updated_at'])->all())->toEqual($before)
        ->and(InvoiceReceipt::query()->count())->toBe(2)
        ->and(DealExpense::query()->count())->toBe(1);
});
