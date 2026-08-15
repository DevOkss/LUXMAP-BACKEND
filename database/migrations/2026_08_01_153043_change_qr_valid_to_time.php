<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_configurations', function (Blueprint $table) {
            $table->time('valid_from')->nullable()->change();
            $table->time('valid_until')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('qr_configurations', function (Blueprint $table) {
            $table->dateTime('valid_from')->nullable()->change();
            $table->dateTime('valid_until')->nullable()->change();
        });
    }
};
