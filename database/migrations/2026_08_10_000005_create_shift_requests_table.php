<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_institute_id')->nullable()->constrained('institutes')->nullOnDelete();
            $table->foreignId('current_program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('requested_institute_id')->constrained('institutes')->cascadeOnDelete();
            $table->foreignId('requested_program_id')->constrained('programs')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_requests');
    }
};