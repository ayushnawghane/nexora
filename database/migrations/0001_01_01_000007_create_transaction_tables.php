<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free counters (EL numbers per financial year). Rows are locked while a number is taken.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->string('el_number', 40)->nullable()->unique();
            $table->date('el_date')->nullable();
            $table->string('deal_code', 40)->nullable()->unique();
            $table->foreignId('transaction_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('arranger_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vertical_team_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('relationship_manager_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('signatory_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('origin', 10);
            $table->text('brief')->nullable();
            $table->timestamp('schedule_verified_at')->nullable();
            $table->foreignId('schedule_verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
        });

        Schema::create('transaction_issue_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('listing', 10);
            $table->string('issue_type', 30);
            $table->boolean('is_secured');
            $table->boolean('is_rated');
            $table->char('currency', 3)->default('INR');
            $table->decimal('base_issue_size', 18, 2);
            $table->decimal('green_shoe_size', 18, 2)->default(0);
            $table->decimal('total_issue_size', 18, 2);
            $table->unsignedSmallInteger('tenure_months');
            $table->unsignedSmallInteger('tenure_days')->default(0);
            $table->timestamps();
        });

        // How the issue splits across debenture types; sums must equal the issue details totals.
        Schema::create('transaction_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('instrument', 10);
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('green_shoe_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['transaction_id', 'instrument']);
        });

        Schema::create('transaction_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_contact_id')->constrained()->restrictOnDelete();
            $table->string('recipient', 5);
            $table->timestamps();
            $table->unique(['transaction_id', 'company_contact_id']);
        });

        Schema::create('fee_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('amount_type', 10);
            $table->decimal('amount', 18, 2)->nullable()->comment('Fixed fee in rupees (per annum for recurring fees)');
            $table->decimal('percent', 9, 6)->nullable()->comment('Percentage of the issue size');
            $table->decimal('annual_amount', 18, 2)->comment('Resolved fee the schedule is built from');
            $table->string('basis', 30);
            $table->string('frequency', 20);
            $table->string('start_reference', 30);
            $table->date('start_date');
            $table->string('timing', 10);
            $table->string('escalation_type', 10)->default('none');
            $table->decimal('escalation_value', 18, 2)->nullable();
            $table->unsignedTinyInteger('escalation_every_years')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'kind']);
        });

        // Generated from the fee line; regenerated whenever the fee or the issue details change.
        Schema::create('fee_schedule_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_line_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('from_date');
            $table->date('to_date');
            $table->date('bill_date');
            $table->unsignedSmallInteger('days');
            $table->unsignedSmallInteger('days_in_year');
            $table->decimal('base_amount', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->string('financial_year', 12);
            $table->boolean('prorated');
            $table->timestamps();
            $table->unique(['fee_line_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_schedule_periods');
        Schema::dropIfExists('fee_lines');
        Schema::dropIfExists('transaction_contacts');
        Schema::dropIfExists('transaction_instruments');
        Schema::dropIfExists('transaction_issue_details');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('number_sequences');
    }
};
