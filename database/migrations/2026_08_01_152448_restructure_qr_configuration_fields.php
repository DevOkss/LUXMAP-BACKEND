<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_configurations', function (Blueprint $table) {
            $table->renameColumn('session', 'type');
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
        });

        Schema::table('qr_configurations', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_configurations', function (Blueprint $table) {
            $table->dropColumn(['valid_from', 'valid_until']);
            $table->string('session')->after('event_id');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
        });
    }
};
