<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'institute_id' => null,
            'program_id' => null,
            'year_level' => 1,
            'is_enrolled' => true,
        ];
    }
}