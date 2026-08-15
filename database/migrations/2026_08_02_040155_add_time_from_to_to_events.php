<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['attendance_start', 'attendance_end']);
            $table->time('time_from')->nullable()->after('venue');
            $table->time('time_to')->nullable()->after('time_from');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['time_from', 'time_to']);
            $table->dateTime('attendance_start')->nullable()->after('venue');
            $table->dateTime('attendance_end')->nullable()->after('attendance_start');
        });
    }
};
