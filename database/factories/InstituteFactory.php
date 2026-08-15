<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstituteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('I??')),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }
}
