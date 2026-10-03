<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Small app-wide values that admins change from the UI (instead of .env), e.g. Beacon's GSTIN.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // GST rates by effective date. An invoice uses the row in force on its date, so rows that
        // have taken effect are never edited; a change is a new row with a later date.
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->unique();
            $table->decimal('cgst', 5, 2);
            $table->decimal('sgst', 5, 2);
            $table->decimal('igst', 5, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('settings');
    }
};
