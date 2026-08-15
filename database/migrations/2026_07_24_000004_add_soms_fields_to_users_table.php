<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_number', 20)->unique()->nullable()->after('id');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('institute', 100)->nullable()->after('phone');
            $table->string('program', 100)->nullable()->after('institute');
            $table->unsignedTinyInteger('year_level')->nullable()->after('program');
            $table->string('profile_photo')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('profile_photo');
            $table->softDeletes();

            $table->index('student_number');
            $table->index('institute');
            $table->index('program');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'student_number',
                'phone',
                'institute',
                'program',
                'year_level',
                'profile_photo',
                'is_active',
            ]);
        });
    }
};
