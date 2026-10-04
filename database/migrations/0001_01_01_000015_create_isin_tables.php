<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A series of debentures issued under the deal, identified by its ISIN.
        Schema::create('deal_isins', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->char('isin', 12);
            $table->text('series_name')->nullable();
            $table->string('listing', 10)->nullable();
            $table->string('exchange', 10)->nullable();
            $table->string('depository', 10)->nullable();
            $table->string('placement', 10)->nullable();
            $table->date('allotment_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('coupon_type', 12)->nullable();
            $table->decimal('coupon_rate', 8, 4)->nullable();
            $table->string('coupon_description', 255)->nullable();
            $table->string('interest_frequency', 12)->nullable();
            $table->string('principal_frequency', 12)->nullable();
            $table->string('day_count', 12)->nullable();
            $table->string('holiday_convention', 10)->nullable();
            $table->date('put_date')->nullable();
            $table->date('call_date')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['transaction_id', 'isin']);
            $table->index('isin');
        });

        // Debentures allotted under an ISIN: the first issue and any further (re-issued) tranches.
        Schema::create('isin_allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_isin_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->date('allotment_date');
            $table->date('issue_opened_on')->nullable();
            $table->date('issue_closed_on')->nullable();
            $table->decimal('face_value', 18, 2);
            $table->unsignedBigInteger('quantity_offered')->nullable();
            $table->unsignedBigInteger('quantity_allotted');
            $table->decimal('amount', 18, 2);
            $table->string('credit_depository', 10)->nullable();
            $table->date('credited_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // The interest and principal schedule: one row per due date, with what was paid.
        Schema::create('isin_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_isin_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->date('due_on');
            $table->date('original_due_on')->nullable();
            $table->string('due_date_reason', 500)->nullable();
            $table->string('status', 15)->index();
            $table->date('paid_on')->nullable();
            $table->string('redemption_basis', 12)->nullable();
            $table->decimal('face_value', 18, 2)->nullable();
            $table->decimal('quantity', 18, 2)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('legacy_key', 30)->nullable()->unique();
            $table->timestamps();
            $table->unique(['deal_isin_id', 'kind', 'due_on']);
            $table->index('due_on');
        });

        // Reminder emails sent about a payment, so the daily job never sends the same one twice a day.
        Schema::create('isin_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isin_payment_id')->constrained()->cascadeOnDelete();
            $table->date('sent_on');
            $table->text('recipients');
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['isin_payment_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isin_reminders');
        Schema::dropIfExists('isin_payments');
        Schema::dropIfExists('isin_allotments');
        Schema::dropIfExists('deal_isins');
    }
};
