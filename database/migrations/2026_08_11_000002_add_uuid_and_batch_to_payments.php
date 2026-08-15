<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->after('id');
            $table->string('batch_id', 36)->nullable()->after('uuid')->index();
        });

        DB::table('payments')->select('id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $uuid = (string) Str::uuid();
                DB::table('payments')->where('id', $row->id)->update([
                    'uuid' => $uuid,
                    'batch_id' => $uuid,
                ]);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['payments_batch_id_index']);
            $table->dropColumn(['uuid', 'batch_id']);
        });
    }
};
