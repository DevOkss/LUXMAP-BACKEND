<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        \App\Models\Event::eachById(function ($event) {
            $event->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
