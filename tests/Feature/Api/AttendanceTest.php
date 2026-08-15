<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\QrConfiguration;
use App\Models\User;
use App\Services\AttendanceService;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('can scan attendance for ongoing event', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();
    $user->organizations()->attach($ssc->id, ['role' => 'ssc_officer', 'assigned_at' => now()]);
    $event = Event::factory()->create(['status' => 'ongoing', 'organization_id' => $ssc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    $response = $this->actingAs($user)->postJson('/api/attendance/scan', [
        'qr_configuration_id' => $config->id,
        'scanned_at' => now()->toISOString(),
    ]);

    $response->assertStatus(201);
    expect(Attendance::where('qr_configuration_id', $config->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('prevents duplicate attendance', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();
    $user->organizations()->attach($ssc->id, ['role' => 'ssc_officer', 'assigned_at' => now()]);
    $event = Event::factory()->create(['organization_id' => $ssc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    Attendance::create([
        'qr_configuration_id' => $config->id,
        'user_id' => $user->id,
        'scanned_at' => now(),
    ]);

    $response = $this->actingAs($user)->postJson('/api/attendance/scan', [
        'qr_configuration_id' => $config->id,
    ]);

    $response->assertStatus(422);
});

test('rejects attendance for non-existent qr config', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/attendance/scan', [
        'qr_configuration_id' => 99999,
    ]);

    $response->assertStatus(422);
});

test('can sync offline attendance', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();
    $user->organizations()->attach($ssc->id, ['role' => 'ssc_officer', 'assigned_at' => now()]);
    $event = Event::factory()->create(['organization_id' => $ssc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    $response = $this->actingAs($user)->postJson('/api/attendance/sync', [
        'records' => [
            [
                'qr_configuration_id' => $config->id,
                'user_id' => $user->id,
                'scanned_at' => now()->toISOString(),
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('processed', 1)
        ->assertJsonPath('saved', 1);

    expect(Attendance::where('qr_configuration_id', $config->id)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('sync skips duplicate offline records', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();
    $user->organizations()->attach($ssc->id, ['role' => 'ssc_officer', 'assigned_at' => now()]);
    $event = Event::factory()->create(['organization_id' => $ssc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    $records = [
        [
            'qr_configuration_id' => $config->id,
            'user_id' => $user->id,
            'scanned_at' => now()->toISOString(),
        ],
        [
            'qr_configuration_id' => $config->id,
            'user_id' => $user->id,
            'scanned_at' => now()->toISOString(),
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/attendance/sync', ['records' => $records]);

    $response->assertStatus(200)
        ->assertJsonPath('processed', 2)
        ->assertJsonPath('saved', 1);

    expect(Attendance::where('qr_configuration_id', $config->id)->where('user_id', $user->id)->count())->toBe(1);
});

test('can view per-event attendance grouped by organization', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();
    $event = Event::factory()->create(['organization_id' => $ssc->id]);
    $configIn = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_out']);

    Attendance::create([
        'qr_configuration_id' => $configIn->id,
        'user_id' => $user->id,
        'scanned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/attendance/events?organization_id=' . $ssc->id);

    $response->assertStatus(200)
        ->assertJsonPath('organization.id', $ssc->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attended_count', 1)
        ->assertJsonPath('data.0.total_qr_configs', 2)
        ->assertJsonPath('data.0.complete', false)
        ->assertJsonPath('data.0.attendances.0.type', 'time_in')
        ->assertJsonPath('data.0.attendances.0.qr_configuration_id', $configIn->id);
});

test('returns empty data when student attended nothing in the organization', function () {
    $ssc = Organization::where('code', 'SSC')->first();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/attendance/events?organization_id=' . $ssc->id);

    $response->assertStatus(200)
        ->assertJsonPath('data', []);
});

test('can view attendance history', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create();
    $config = QrConfiguration::factory()->create(['event_id' => $event->id]);

    Attendance::create([
        'qr_configuration_id' => $config->id,
        'user_id' => $user->id,
        'scanned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/attendance/history');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});
