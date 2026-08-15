<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['institute']);
            $table->dropIndex(['program']);
            $table->dropColumn(['institute', 'program']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('institute', 100)->nullable()->after('phone');
            $table->string('program', 100)->nullable()->after('institute');
            $table->index('institute');
            $table->index('program');
        });
    }
};
