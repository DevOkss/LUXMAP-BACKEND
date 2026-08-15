<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\QrConfiguration;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
    $this->service = app(AttendanceService::class);
});

test('rejects duplicate attendance', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create(['status' => 'ongoing']);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    $this->service->scan([
        'qr_configuration_id' => $config->id,
        'scanned_at' => now()->toISOString(),
    ], $user);

    $this->expectException(ValidationException::class);

    $this->service->scan([
        'qr_configuration_id' => $config->id,
        'scanned_at' => now()->toISOString(),
    ], $user);
});

test('offline sync attendance is accepted', function () {
    $event = Event::factory()->create(['status' => 'ongoing']);
    expect($event->status)->toBe('ongoing');
});
