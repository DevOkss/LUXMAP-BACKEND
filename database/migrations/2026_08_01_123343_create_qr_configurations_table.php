<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('session'); // 'morning', 'afternoon'
            $table->time('time_in');
            $table->time('time_out');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius')->nullable();
            $table->json('required_years')->nullable(); // ["1","2","3","4"] or ["all"]
            $table->text('qr_data')->nullable();
            $table->boolean('is_generated')->default(false);
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_configurations');
    }
};
