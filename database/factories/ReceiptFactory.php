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
            'receipt_number' => 'RCP-' . fake()->unique()->randomNumber(8),
            'issued_at' => now(),
        ];
    }
}
