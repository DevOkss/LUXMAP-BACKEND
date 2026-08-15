<?php

namespace Database\Factories;

use App\Models\Institute;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_institute_id' => Institute::factory(),
            'current_program_id' => Program::factory(),
            'requested_institute_id' => Institute::factory(),
            'requested_program_id' => Program::factory(),
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }
}