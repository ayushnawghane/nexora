<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Legacy rows merged into another record during import (e.g. the same CIN entered twice in
    // Stack). Later imports resolve a merged legacy id to the record it was merged into.
    public function up(): void
    {
        Schema::create('legacy_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('model', 120);
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('target_id');
            $table->string('reason', 255);
            $table->timestamps();
            $table->unique(['model', 'legacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_aliases');
    }
};
