<?php

use App\Models\Event;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\QrConfiguration;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function qrOfficer(string $orgCode, string $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role,
        'position' => $role,
        'assigned_at' => now(),
    ]);

    return $user;
}

function qrStudent(): User
{
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    return User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);
}

test('student cannot create a qr configuration for an event in their organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'published']);
    $student = qrStudent();

    $response = $this->actingAs($student)->postJson("/api/events/{$event->uuid}/qr-configurations", [
        'type' => 'time_in',
        'valid_from' => '08:00',
        'valid_until' => '17:00',
    ]);

    $response->assertStatus(403);
    expect($event->qrConfigurations()->count())->toBe(0);
});

test('officer cannot create or modify qr configurations of another organization event', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'published']);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    $sscOfficer = qrOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->postJson("/api/events/{$event->uuid}/qr-configurations", [
        'type' => 'time_out',
        'valid_from' => '08:00',
        'valid_until' => '17:00',
    ])->assertStatus(403);

    $this->actingAs($sscOfficer)->putJson("/api/events/{$event->uuid}/qr-configurations/{$config->id}", [
        'valid_from' => '09:00',
        'valid_until' => '18:00',
    ])->assertStatus(403);

    $this->actingAs($sscOfficer)->postJson("/api/events/{$event->uuid}/qr-configurations/{$config->id}/generate")
        ->assertStatus(403);

    $this->actingAs($sscOfficer)->deleteJson("/api/events/{$event->uuid}/qr-configurations/{$config->id}")
        ->assertStatus(403);
});

test('officer of the creating organization can manage qr configurations', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'published']);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    $iscOfficer = qrOfficer('ICS-ISC', 'isc_officer');

    $this->actingAs($iscOfficer)->putJson("/api/events/{$event->uuid}/qr-configurations/{$config->id}", [
        'valid_from' => '09:00',
        'valid_until' => '18:00',
    ])->assertStatus(200);

    expect($config->fresh()->valid_from)->toBe('09:00');

    $this->actingAs($iscOfficer)->deleteJson("/api/events/{$event->uuid}/qr-configurations/{$config->id}")
        ->assertStatus(204);
});

test('cannot manipulate a qr configuration belonging to a different event', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    $sscEvent = Event::factory()->create(['organization_id' => $ssc->id, 'status' => 'published']);
    $iscEvent = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'published']);
    $iscConfig = QrConfiguration::factory()->create(['event_id' => $iscEvent->id, 'type' => 'time_in']);

    $sscOfficer = qrOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->putJson("/api/events/{$sscEvent->uuid}/qr-configurations/{$iscConfig->id}", [
        'valid_from' => '09:00',
        'valid_until' => '18:00',
    ])->assertStatus(404);

    expect($iscConfig->fresh()->valid_from)->not->toBe('09:00');
});

test('reuse_from must reference a configuration of the same event', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    $sscEvent = Event::factory()->create(['organization_id' => $ssc->id, 'status' => 'published']);
    $iscEvent = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'published']);
    $iscConfig = QrConfiguration::factory()->create(['event_id' => $iscEvent->id, 'type' => 'time_in']);

    $sscOfficer = qrOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->postJson("/api/events/{$sscEvent->uuid}/qr-configurations", [
        'type' => 'time_in',
        'valid_from' => '08:00',
        'valid_until' => '17:00',
        'reuse_from' => $iscConfig->id,
    ])->assertStatus(422);
});
