<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where an active deal is in its life (Preliminary → Documentation → Live → Redeemed …).
        // Set when the engagement letter is issued; only changes through DealStatus::canTransitionTo().
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('deal_status', 30)->nullable()->after('status')->index();
            $table->date('deal_status_since')->nullable()->after('deal_status');
        });

        // A request to move a deal to another status. Hold applies at once; everything else waits
        // for the approval teams it needs. At most one open request per deal (enforced under a row lock).
        Schema::create('deal_status_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->date('effective_on');
            $table->text('reason');
            $table->string('noc_path')->nullable();
            $table->string('noc_name')->nullable();
            $table->boolean('needs_management');
            $table->boolean('needs_accounts');
            $table->string('status', 20)->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // One vote per team and one per person: the same person can't approve for both teams.
        Schema::create('deal_status_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_status_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('team', 20);
            $table->string('decision', 10);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['deal_status_request_id', 'team']);
            $table->unique(['deal_status_request_id', 'user_id']);
        });

        // Every status a deal has actually had, in order.
        Schema::create('deal_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->date('effective_on');
            $table->foreignId('deal_status_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // Who the deal is billed to: one of the company's addresses, the GSTIN invoices carry (if any),
        // and the resulting place of supply that decides CGST + SGST or IGST.
        Schema::create('deal_billing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_address_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_gstin_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('place_of_supply_state_id')->constrained('states')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('deal_billing_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_contact_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['transaction_id', 'company_contact_id']);
        });

        // Checklist items every deal's job sheet carries. `listing` null = all deals.
        Schema::create('job_sheet_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('listing', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // A deal's progress on one job sheet activity: the maker records it, a different person checks it.
        Schema::create('deal_job_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_sheet_activity_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->date('received_on');
            $table->foreignId('maker_id')->constrained('users')->restrictOnDelete();
            $table->text('maker_comment')->nullable();
            $table->timestamp('made_at');
            $table->foreignId('checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('checker_comment')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'job_sheet_activity_id'], 'deal_job_sheet_entries_deal_activity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_job_sheet_entries');
        Schema::dropIfExists('job_sheet_activities');
        Schema::dropIfExists('deal_billing_contacts');
        Schema::dropIfExists('deal_billing');
        Schema::dropIfExists('deal_status_changes');
        Schema::dropIfExists('deal_status_votes');
        Schema::dropIfExists('deal_status_requests');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['deal_status']);
            $table->dropColumn(['deal_status', 'deal_status_since']);
        });
    }
};
