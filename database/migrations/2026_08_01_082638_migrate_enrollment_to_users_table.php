<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_enrolled')->default(false)->after('is_active');
            $table->foreignId('institute_id')->nullable()->after('is_enrolled')->constrained('institutes')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('institute_id')->constrained('programs')->nullOnDelete();
        });

        Schema::dropIfExists('enrollments');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('institute_id');
            $table->dropColumn('is_enrolled');
        });
    }
};
