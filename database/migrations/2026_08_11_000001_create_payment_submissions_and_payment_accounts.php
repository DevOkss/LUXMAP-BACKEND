<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_type')->default('fee'); // fee, penalty
            $table->foreignId('fee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('cashless');
            $table->string('payment_channel')->nullable(); // gcash, maya, bank_transfer, ...
            $table->string('reference_number')->nullable();
            $table->string('receipt_image')->nullable();
            $table->string('group_key');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            // Set to a unique value while the item is unresolved; nulled on
            // approve/reject so a new submission attempt is allowed.
            $table->string('lock_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('group_key');
            $table->index('user_id');
        });

        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_name');
            $table->string('account_provider')->nullable(); // GCash, Maya, Bank name
            $table->string('account_number');
            $table->string('qr_code_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('processed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->foreignId('payment_submission_id')->nullable()->after('reference_number')->constrained('payment_submissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('processed_by');
            $table->dropConstrainedForeignId('payment_submission_id');
        });

        Schema::dropIfExists('payment_accounts');
        Schema::dropIfExists('payment_submissions');
    }
};
