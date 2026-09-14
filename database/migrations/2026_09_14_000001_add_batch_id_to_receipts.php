<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('batch_id', 36)->nullable()->after('payment_id')->index();
        });

        // Backfill existing receipts: infer batch_id from their payment's batch_id
        DB::table('receipts')->join('payments', 'payments.id', '=', 'receipts.payment_id')
            ->select('receipts.id as rid', 'payments.batch_id as pbid')
            ->orderBy('receipts.id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('receipts')->where('id', $row->rid)->update(['batch_id' => $row->pbid]);
                }
            }, 'receipts.id', 'rid');
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
    }
};
