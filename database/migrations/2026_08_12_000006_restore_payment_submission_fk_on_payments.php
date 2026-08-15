<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the payments.payment_submission_id FK that was dropped by the
     * reverted PayMongo migration rollback.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_submission_id')) {
                $table->foreignId('payment_submission_id')
                    ->nullable()
                    ->after('reference_number')
                    ->constrained('payment_submissions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'payment_submission_id')) {
                $table->dropConstrainedForeignId('payment_submission_id');
            }
        });
    }
};
