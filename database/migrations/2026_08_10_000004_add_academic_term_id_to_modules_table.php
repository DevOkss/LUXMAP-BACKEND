<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['fees', 'events', 'attendances', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('academic_term_id')->nullable()
                    ->after('id')
                    ->constrained('academic_terms')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['fees', 'events', 'attendances', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('academic_term_id');
            });
        }
    }
};