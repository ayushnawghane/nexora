<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clients and counterparties. Companies are deactivated, never deleted: deals reference them.
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('entity_type', 20);
            $table->string('cin', 21)->nullable()->unique()->comment('CIN for companies, LLPIN for LLPs');
            $table->string('name', 255)->index();
            $table->string('formerly_known_as', 255)->nullable();
            $table->char('pan', 10)->nullable()->unique();
            $table->string('company_class', 30)->nullable();
            $table->string('category', 30)->nullable();
            $table->date('incorporated_on')->nullable();
            $table->boolean('is_listed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_gstins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->char('gstin', 15)->unique();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->string('legal_name', 255)->nullable();
            $table->string('trade_name', 255)->nullable();
            $table->date('registered_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('billing_name', 255)->nullable()->comment('Name printed on invoices, when it differs from the company name');
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 120);
            $table->char('pincode', 6);
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_gstin_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'type']);
        });

        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('salutation', 10)->nullable();
            $table->string('name', 150);
            $table->string('designation', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('mobile', 16)->nullable();
            $table->string('landline', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('company_gstins');
        Schema::dropIfExists('companies');
    }
};
