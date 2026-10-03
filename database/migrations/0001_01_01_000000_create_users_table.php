<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('emp_code', 20)->unique();
            $table->string('name', 150);
            $table->string('email')->unique();
            $table->string('mobile', 20)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_authorised_signatory')->default(false);
            $table->string('signature_path')->nullable();
            $table->date('date_of_joining')->nullable();

            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->string('theme', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('emp_code', 50);
            $table->boolean('successful');
            $table->string('reason', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['emp_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
