<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('fee_type')->default('fee')->after('organization_id'); // fee, penalty
            $table->foreignId('event_id')->nullable()->after('fee_id')->constrained()->nullOnDelete();
            $table->boolean('isExempted')->default(false)->after('status');
            $table->foreignId('exempted_by')->nullable()->after('isExempted')->constrained('users')->nullOnDelete();
            $table->timestamp('exempted_at')->nullable()->after('exempted_by');

            $table->string('payment_method')->nullable()->change();

            $table->dropForeign(['penalty_id']);
            $table->dropColumn('penalty_id');

            $table->unique(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'event_id']);
            $table->foreignId('penalty_id')->nullable()->after('fee_id')->constrained()->nullOnDelete();
            $table->dropColumn(['fee_type', 'event_id', 'isExempted', 'exempted_by', 'exempted_at']);
        });
    }
};