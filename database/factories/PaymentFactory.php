<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'batch_id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'academic_term_id' => null,
            'fee_type' => 'fee',
            'fee_id' => null,
            'event_id' => null,
            'amount' => fake()->randomFloat(2, 50, 500),
            'payment_method' => fake()->randomElement(['cash', 'cashless', 'exemption']),
            'reference_number' => 'REF-'.fake()->unique()->randomNumber(8),
            'status' => 'paid',
            'isExempted' => false,
            'exempted_by' => null,
            'exempted_at' => null,
            'paid_at' => now(),
        ];
    }

    public function fee(?Fee $fee = null): static
    {
        return $this->state(fn () => [
            'fee_type' => 'fee',
            'fee_id' => $fee?->id,
            'event_id' => null,
        ]);
    }

    public function penalty(?Event $event = null): static
    {
        return $this->state(fn () => [
            'fee_type' => 'penalty',
            'fee_id' => null,
            'event_id' => $event?->id,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'isExempted' => false,
            'paid_at' => now(),
            'payment_method' => fake()->randomElement(['cash', 'cashless']),
        ]);
    }

    public function cashless(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'payment_method' => 'cashless',
            'paid_at' => now(),
        ]);
    }

    public function exempted(): static
    {
        return $this->state(fn () => [
            'status' => 'exempted',
            'isExempted' => true,
            'exempted_by' => User::factory(),
            'exempted_at' => now(),
            'payment_method' => 'exemption',
            'paid_at' => null,
        ]);
    }
}
