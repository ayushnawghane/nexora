<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
