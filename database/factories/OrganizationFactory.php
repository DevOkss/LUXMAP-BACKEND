<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->bothify('??-###')),
            'type' => fake()->randomElement(OrganizationType::cases())->value,
            'description' => fake()->sentence(),
            'config' => json_encode(['penalty_amount' => 50]),
            'is_active' => true,
        ];
    }
}
