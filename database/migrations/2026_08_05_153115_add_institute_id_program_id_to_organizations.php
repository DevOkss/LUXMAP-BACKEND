<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('institute_id')->nullable()->after('parent_id')
                ->constrained('institutes')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('institute_id')
                ->constrained('programs')->nullOnDelete();
            $table->unique(['institute_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['institute_id']);
            $table->dropForeign(['program_id']);
            $table->dropUnique(['institute_id', 'program_id']);
            $table->dropColumn(['institute_id', 'program_id']);
        });
    }
};
