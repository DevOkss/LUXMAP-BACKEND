<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'venue' => fake()->streetAddress(),
            'event_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'qr_secret' => fake()->sha256(),
        ];
    }
}
