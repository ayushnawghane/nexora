<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per submission for approval. A re-submission after rejection is a new request,
        // so every round of voting stays on record.
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('decision', 10);
            $table->boolean('is_head')->comment('Whether the voter was a head approver when voting');
            $table->text('comment')->nullable();
            $table->string('via', 10)->comment('app or email');
            $table->timestamps();
            $table->unique(['approval_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_votes');
        Schema::dropIfExists('approval_requests');
    }
};
