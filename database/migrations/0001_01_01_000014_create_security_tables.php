<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_types', 'charge_types'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Firms on Beacon's panel (CAs, valuers …) that issue due diligence certificates.
        Schema::create('empanelled_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 200);
            $table->string('city', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // What a security is over (book debts, listed shares, land …), grouped by asset type.
        Schema::create('security_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->foreignId('asset_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // The kind of security a legal document creates (hypothecation, mortgage, pledge, guarantee).
        Schema::table('legal_document_types', function (Blueprint $table) {
            $table->string('security_nature', 20)->nullable()->after('category');
        });

        // A security created under one of the deal's legal documents: whose asset, over what, which charge.
        Schema::create('deal_securities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_document_id')->constrained()->restrictOnDelete();
            $table->string('nature', 20);
            $table->string('asset_owner', 255);
            $table->string('owner_id_type', 10)->nullable();
            $table->string('owner_id_number', 25)->nullable();
            $table->foreignId('asset_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('charge_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('pertaining_to', 255)->nullable();
            $table->boolean('is_encumbered')->nullable();
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('city', 120)->nullable();
            $table->foreignId('state_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('form_of_securities', 255)->nullable();
            $table->string('confirming_party', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('deal_security_security_type', function (Blueprint $table) {
            $table->foreignId('deal_security_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_type_id')->constrained()->restrictOnDelete();
            $table->primary(['deal_security_id', 'security_type_id']);
        });

        // A registration of securities: a ROC charge, a CERSAI registration or a depository pledge.
        // Its history (created, modified, satisfied / pledged, unpledged) is in the events table.
        Schema::create('security_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('status', 15)->index();
            $table->string('reference', 60)->nullable(); // ROC charge id / CERSAI asset id / ISIN
            $table->decimal('amount', 18, 2)->nullable();
            // Pledges only
            $table->string('security_name', 255)->nullable();
            $table->unsignedBigInteger('quantity')->nullable();
            $table->decimal('face_value', 18, 2)->nullable();
            $table->string('depository', 10)->nullable();
            $table->string('pledgor_dp_id', 20)->nullable();
            $table->string('pledgor_client_id', 20)->nullable();
            $table->string('pledgee_dp_id', 20)->nullable();
            $table->string('pledgee_client_id', 20)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('legacy_key', 30)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('deal_security_registration', function (Blueprint $table) {
            $table->foreignId('security_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_security_id')->constrained()->restrictOnDelete();
            $table->primary(['security_registration_id', 'deal_security_id'], 'deal_security_registration_primary');
        });

        Schema::create('security_registration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_registration_id')->constrained()->cascadeOnDelete();
            $table->string('action', 15);
            $table->date('happened_on');
            $table->string('filing_reference', 60)->nullable(); // SRN / CERSAI transaction id
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('legacy_key', 30)->nullable()->unique();
            $table->timestamps();
        });

        // Due diligence: ROC search reports, security certificates, NOCs, the security cover
        // certificate, annexures and other documents, each uploaded and checked by someone else.
        Schema::create('deal_diligence_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('title', 500);
            $table->foreignId('deal_security_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('asset_owner', 255)->nullable();
            $table->foreignId('empanelled_agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference', 60)->nullable(); // UDIN / charge id
            $table->string('status', 20)->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('checker_comment')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('legacy_key', 30)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_diligence_items');
        Schema::dropIfExists('security_registration_events');
        Schema::dropIfExists('deal_security_registration');
        Schema::dropIfExists('security_registrations');
        Schema::dropIfExists('deal_security_security_type');
        Schema::dropIfExists('deal_securities');
        Schema::table('legal_document_types', function (Blueprint $table) {
            $table->dropColumn('security_nature');
        });
        Schema::dropIfExists('security_types');
        Schema::dropIfExists('empanelled_agencies');
        Schema::dropIfExists('charge_types');
        Schema::dropIfExists('asset_types');
    }
};
