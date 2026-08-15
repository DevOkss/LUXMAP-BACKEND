<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->text('qr_data')->nullable();
            $table->dateTime('scanned_at');
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy', 10, 2)->nullable();
            $table->text('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_offline')->default(false);
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index('scanned_at');
            $table->index(['event_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
