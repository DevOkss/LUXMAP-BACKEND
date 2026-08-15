<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fee_user');
        Schema::dropIfExists('penalties');
    }

    public function down(): void
    {
        // Intentionally not recreating; schema managed by their original migrations.
    }
};