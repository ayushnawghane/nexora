<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every billing document of a deal: proforma, tax invoice, credit note, reimbursement bill.
        // Who is billed is copied onto the invoice when it's issued, so later edits to the company
        // or the deal's billing never change an issued invoice.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('status', 12);
            // Tax invoice → the proforma it came from; credit note → the tax invoice it reduces.
            $table->foreignId('parent_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('number', 40)->nullable();
            $table->char('financial_year', 4)->nullable();
            $table->unsignedInteger('serial')->nullable();
            $table->date('invoice_date')->nullable();

            $table->string('billed_name', 255)->nullable();
            $table->text('billed_address')->nullable();
            $table->char('billed_gstin', 15)->nullable();
            $table->foreignId('place_of_supply_state_id')->nullable()->constrained('states')->restrictOnDelete();
            $table->boolean('inter_state')->nullable();
            $table->string('sac', 8)->nullable();
            $table->boolean('gst_applies')->default(true);

            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('taxable_amount', 18, 2)->default(0);
            $table->decimal('non_taxable_amount', 18, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('cgst', 18, 2)->default(0);
            $table->decimal('sgst', 18, 2)->default(0);
            $table->decimal('igst', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            // Proformas and reimbursement bills: total less credit notes, receipts and TDS. Kept up
            // to date by every action that moves money, so lists can filter on it.
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->text('notes')->nullable();

            $table->string('irn', 64)->nullable()->unique();
            $table->string('ack_no', 32)->nullable();
            $table->dateTime('ack_at')->nullable();
            $table->text('signed_qr')->nullable();
            $table->dateTime('irn_cancelled_at')->nullable();

            $table->string('pdf_path', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('returned_reason')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('issued_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            // Unique per kind: in 2018-19 Stack gave a tax invoice its proforma's number.
            $table->unique(['kind', 'number']);
            $table->index(['kind', 'status']);
            $table->index('invoice_date');
            $table->index('balance_due');
        });

        // Out-of-pocket expenses Beacon paid for the deal, billed back on a reimbursement bill
        // (or on a proforma, without GST). `invoice_id` is the invoice billing it, if any.
        Schema::create('deal_expenses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->date('incurred_on')->nullable();
            $table->string('description', 255);
            $table->decimal('amount', 18, 2);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // What an invoice charges. A line billing a fee period or an expense points at it; a credit
        // note line points at the tax invoice line it reduces.
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind', 12);
            $table->string('description', 255);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->boolean('taxable');
            $table->decimal('amount', 18, 2);
            $table->foreignId('fee_schedule_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('deal_expense_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('credited_line_id')->nullable()->constrained('invoice_lines')->restrictOnDelete();
            $table->timestamps();
        });

        // The invoice currently billing a fee period (the proforma that took it). Cleared when the
        // draft is discarded or the invoice is cancelled, so the period can be billed again.
        Schema::table('fee_schedule_periods', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('prorated')->constrained()->nullOnDelete();
        });

        // Money received against a tax invoice or reimbursement bill. TDS the client deducted counts
        // towards settling it. A wrong receipt is reversed with a reason, never deleted.
        Schema::create('invoice_receipts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->date('received_on');
            $table->decimal('amount', 18, 2);
            $table->decimal('tds_amount', 18, 2)->default(0);
            $table->string('utr', 40)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // Each time an invoice was emailed, and to whom.
        Schema::create('invoice_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->text('recipients');
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_mails');
        Schema::dropIfExists('invoice_receipts');
        Schema::table('fee_schedule_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('deal_expenses');
        Schema::dropIfExists('invoices');
    }
};
