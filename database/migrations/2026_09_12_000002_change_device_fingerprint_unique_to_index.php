<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_bindings', function (Blueprint $table) {
            // Drop global unique constraint on device_fingerprint to allow same hardware model
            // to have same deterministic hash for different users. Per-user uniqueness still
            // enforced in application logic, and per-user one-binding invariant kept via user_id unique.
            // Keep index for lookup performance.
            try {
                $table->dropUnique(['device_fingerprint']);
            } catch (\Throwable $e) {
                // Unique may not exist if already dropped or named differently
            }
            $table->index('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('device_bindings', function (Blueprint $table) {
            $table->dropIndex(['device_fingerprint']);
            $table->unique('device_fingerprint');
        });
    }
};