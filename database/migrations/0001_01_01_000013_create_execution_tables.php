<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People outside Beacon who sign documents under a power of attorney (Stack's POA master).
        Schema::create('poa_holders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('email', 255)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_till')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // A deal document sent for execution: scheduled with a place, time and signatory, then the
        // executed copy is uploaded and a different person verifies it. One per deal document.
        Schema::create('deal_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('deal_document_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->string('place', 100)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('signatory_type', 10)->nullable();
            $table->foreignId('signatory_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('poa_holder_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('document_date')->nullable();
            $table->date('executed_on')->nullable();
            $table->text('comments')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('checker_comment')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->foreignId('picked_up_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_executions');
        Schema::dropIfExists('poa_holders');
    }
};
