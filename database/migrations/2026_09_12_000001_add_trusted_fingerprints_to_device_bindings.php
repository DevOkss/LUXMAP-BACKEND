<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_bindings', function (Blueprint $table) {
            $table->json('trusted_fingerprints')->nullable()->after('device_meta');
        });
    }

    public function down(): void
    {
        Schema::table('device_bindings', function (Blueprint $table) {
            $table->dropColumn('trusted_fingerprints');
        });
    }
};