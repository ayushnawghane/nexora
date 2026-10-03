<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verticals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->unique();
            $table->foreignId('signatory_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vertical_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vertical_id')->constrained()->restrictOnDelete();
            $table->string('name', 150)->unique();
            $table->string('email')->nullable()->unique();
            $table->foreignId('signatory_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_vertical', function (Blueprint $table) {
            $table->foreignId('vertical_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['vertical_id', 'product_id']);
        });

        Schema::create('product_vertical_team', function (Blueprint $table) {
            $table->foreignId('vertical_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['vertical_team_id', 'product_id']);
        });

        Schema::create('user_vertical', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vertical_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'vertical_id']);
        });

        Schema::create('user_vertical_team', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vertical_team_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'vertical_team_id']);
        });

        Schema::create('product_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_user');
        Schema::dropIfExists('user_vertical_team');
        Schema::dropIfExists('user_vertical');
        Schema::dropIfExists('product_vertical_team');
        Schema::dropIfExists('product_vertical');
        Schema::dropIfExists('vertical_teams');
        Schema::dropIfExists('verticals');
    }
};
