<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class QrConfigurationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => 'time',
            'valid_from' => '08:00',
            'valid_until' => '17:00',
            'latitude' => null,
            'longitude' => null,
            'geofence_radius' => null,
            'is_generated' => true,
            'qr_data' => null,
        ];
    }
}
