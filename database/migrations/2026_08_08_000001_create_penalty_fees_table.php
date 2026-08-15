<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('effective_at')->useCurrent();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'effective_at']);
        });

        // Seed: carry over each org's existing config penalty_amount into penalty_fees,
        // plus a global default so generation never comes up empty.
        $orgs = DB::table('organizations')->whereNull('deleted_at')->get(['id', 'config']);
        foreach ($orgs as $org) {
            $amount = $org->config ? (json_decode($org->config, true)['penalty_amount'] ?? 50) : 50;
            DB::table('penalty_fees')->insert([
                'organization_id' => $org->id,
                'amount' => $amount,
                'effective_at' => now(),
                'set_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('penalty_fees')->insert([
            'organization_id' => null,
            'amount' => 50,
            'effective_at' => now(),
            'set_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organizations')->whereNotNull('config')->update(['config' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_fees');
    }
};
