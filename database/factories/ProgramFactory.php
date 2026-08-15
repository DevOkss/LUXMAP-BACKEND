<?php

namespace Database\Factories;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institute_id' => Institute::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-###')),
            'name' => fake()->catchPhrase(),
            'is_active' => true,
        ];
    }
}
