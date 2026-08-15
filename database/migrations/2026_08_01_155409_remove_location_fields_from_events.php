<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius', 'max_participants']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('venue');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius')->nullable()->after('longitude');
            $table->unsignedInteger('max_participants')->nullable()->after('event_date');
        });
    }
};
