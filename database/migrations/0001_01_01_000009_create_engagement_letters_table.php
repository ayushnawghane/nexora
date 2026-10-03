<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every issued or corrected letter is a new version; earlier versions are kept as issued.
        Schema::create('engagement_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('el_number', 40);
            $table->date('el_date');
            $table->longText('body_html')->comment('Rendered letter as issued (snapshot)');
            $table->string('pdf_path');
            $table->string('reason', 500)->nullable()->comment('Why a new version was made');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['transaction_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_letters');
    }
};
