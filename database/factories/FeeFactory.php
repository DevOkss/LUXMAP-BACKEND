<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true) . ' Fee',
            'amount' => fake()->randomFloat(2, 50, 500),
            'term' => '1st Semester AY 2026-2027',
            'required_years' => ['all'],
            'due_date' => now()->addMonth(),
            'status' => 'draft',
        ];
    }
}
