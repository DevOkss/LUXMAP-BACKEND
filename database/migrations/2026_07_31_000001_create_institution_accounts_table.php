<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('stud_id', 20)->unique();
            $table->string('password');
            $table->string('stud_cnum', 20)->nullable();
            $table->string('stud_fname');
            $table->string('stud_lname');
            $table->string('stud_mname')->nullable();
            $table->string('stud_sex', 10)->nullable();
            $table->unsignedTinyInteger('stud_year')->nullable();
            $table->boolean('is_graduated')->default(false);
            $table->boolean('is_enrolled')->default(true);
            $table->timestamps();

            $table->index('is_graduated');
            $table->index('is_enrolled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_accounts');
    }
};
