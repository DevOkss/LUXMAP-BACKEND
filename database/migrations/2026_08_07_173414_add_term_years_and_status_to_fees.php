<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->string('term')->nullable()->after('description');
            $table->json('required_years')->nullable()->after('amount');
            $table->dropColumn(['type', 'frequency']);
        });

        DB::table('fees')->where('status', 'active')->update(['status' => 'posted']);
        DB::table('fees')->where('status', 'inactive')->update(['status' => 'draft']);

        Schema::table('fees', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->dropColumn(['term', 'required_years']);
            $table->string('type')->default('mandatory')->after('amount');
            $table->string('frequency')->default('one-time')->after('type');
        });

        DB::table('fees')->where('status', 'posted')->update(['status' => 'active']);
        DB::table('fees')->where('status', 'draft')->update(['status' => 'inactive']);

        Schema::table('fees', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }
};
