<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_unbind_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('previous_device_fingerprint');
            $table->string('reason')->nullable();
            $table->foreignId('unbound_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unbound_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_unbind_audits');
    }
};