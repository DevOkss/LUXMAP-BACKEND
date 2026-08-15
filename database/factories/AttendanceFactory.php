<?php

namespace Database\Factories;

use App\Models\QrConfiguration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'qr_configuration_id' => QrConfiguration::factory(),
            'user_id' => User::factory(),
            'scanned_at' => now(),
            'synced_at' => now(),
        ];
    }
}
