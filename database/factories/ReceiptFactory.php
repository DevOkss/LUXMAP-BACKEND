<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'batch_id' => null,
            'receipt_number' => 'RCP-' . fake()->unique()->randomNumber(8),
            'issued_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Receipt $receipt) {
            if (empty($receipt->batch_id) && $receipt->payment) {
                $receipt->updateQuietly(['batch_id' => $receipt->payment->batch_id]);
            }
        });
    }
}
