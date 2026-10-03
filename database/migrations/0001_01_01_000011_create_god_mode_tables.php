<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every God Mode correction, as it was made. Rows are never updated or deleted (the model
        // refuses); undoing a change is a new row that points at the one it reverts.
        Schema::create('god_mode_changes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('editor', 40)->comment('What was corrected, e.g. company, fees, letter-wording');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->json('before');
            $table->json('after');
            $table->boolean('can_roll_back');
            $table->foreignId('reverts_change_id')->nullable()->unique()->constrained('god_mode_changes')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id']);
        });

        // EL numbers taken out of use by a renumbering. They can never be issued again.
        Schema::create('retired_el_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('el_number', 40)->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('god_mode_change_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_el_numbers');
        Schema::dropIfExists('god_mode_changes');
    }
};
