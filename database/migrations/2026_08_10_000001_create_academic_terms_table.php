<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year'); // e.g. 2026-2027
            $table->string('semester'); // e.g. 1st, 2nd, Summer
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['academic_year', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};