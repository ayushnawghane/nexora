<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lead_sources', 'contact_types', 'transaction_types'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('arrangers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('cin', 21)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('cin', 21)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // GST state codes are fixed by law (e.g. 27 = Maharashtra); seeded, not edited.
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->char('gst_code', 2)->unique();
            $table->boolean('is_union_territory')->default(false);
            $table->timestamps();
        });

        Schema::create('pincodes', function (Blueprint $table) {
            $table->id();
            $table->char('pincode', 6);
            $table->string('city', 120);
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pincode', 'city']);
            $table->index('pincode');
        });

        Schema::table('vertical_teams', function (Blueprint $table) {
            $table->string('legal_email')->nullable()->after('email');
            $table->string('compliance_email')->nullable()->after('legal_email');
            $table->string('billing_email')->nullable()->after('compliance_email');
        });
    }

    public function down(): void
    {
        Schema::table('vertical_teams', function (Blueprint $table) {
            $table->dropColumn(['legal_email', 'compliance_email', 'billing_email']);
        });
        Schema::dropIfExists('pincodes');
        Schema::dropIfExists('states');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('arrangers');
        foreach (['transaction_types', 'contact_types', 'lead_sources'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
