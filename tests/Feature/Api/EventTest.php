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

function createUser(?Organization $org = null): User
{
    $user = User::factory()->create();
    $org ??= Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($org->id, ['role' => 'super_admin', 'assigned_at' => now()]);
    return $user;
}

function createOfficer(string $orgCode, string $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role,
        'position' => $role,
        'assigned_at' => now(),
    ]);
    return $user;
}

function createStudent(): User
{
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    return User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);
}

test('can list events', function () {
    $user = createUser();
    Event::factory()->count(3)->create();

    $response = $this->actingAs($user)->getJson('/api/events');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('can create event', function () {
    $user = createUser();
    $org = Organization::where('code', 'SSC')->first();

    $response = $this->actingAs($user)->postJson('/api/events', [
        'organization_id' => $org->id,
        'title' => 'Test Event',
        'description' => 'Event description',
        'venue' => 'Auditorium',
        'time_from' => '08:00',
        'time_to' => '17:00',
        'event_date' => now()->format('Y-m-d'),
        'status' => 'draft',
    ]);

    $response->assertStatus(201);
    expect(Event::where('title', 'Test Event')->exists())->toBeTrue();
});

test('can show event', function () {
    $user = createUser();
    $event = Event::factory()->create();

    $response = $this->actingAs($user)->getJson("/api/events/{$event->uuid}");

    $response->assertStatus(200);
});

test('event exposes aggregated required years from its qr configurations', function () {
    $user = createUser();
    $event = Event::factory()->create();
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in', 'required_years' => ['1', '3']]);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_out', 'required_years' => ['3', '4']]);

    $response = $this->actingAs($user)->getJson("/api/events/{$event->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('data.required_years', ['1', '3', '4']);
});

test('event with an all-years qr configuration reports all required', function () {
    $user = createUser();
    $event = Event::factory()->create();
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in', 'required_years' => ['all']]);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_out', 'required_years' => ['2']]);

    $response = $this->actingAs($user)->getJson("/api/events/{$event->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('data.required_years', ['all']);
});

test('can update event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'draft']);

    $response = $this->actingAs($user)->putJson("/api/events/{$event->uuid}", [
        'title' => 'Updated Title',
        'organization_id' => $event->organization_id,
        'event_date' => $event->event_date,
    ]);

    $response->assertStatus(200);
    expect($event->fresh()->title)->toBe('Updated Title');
});

test('can delete draft event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'draft']);

    $response = $this->actingAs($user)->deleteJson("/api/events/{$event->uuid}");

    $response->assertStatus(204);
    expect(Event::find($event->id))->toBeNull();
});

test('can publish event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'draft']);

    $response = $this->actingAs($user)->postJson("/api/events/{$event->uuid}/publish");

    $response->assertStatus(200);
    expect($event->fresh()->status)->toBe('published');
});

test('can unpublish event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'published']);

    $response = $this->actingAs($user)->postJson("/api/events/{$event->uuid}/unpublish");

    $response->assertStatus(200);
    expect($event->fresh()->status)->toBe('draft');
});

test('can complete event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'ongoing']);

    $response = $this->actingAs($user)->postJson("/api/events/{$event->uuid}/complete");

    $response->assertStatus(200);
    expect($event->fresh()->status)->toBe('completed');
});

test('can complete a published event', function () {
    $user = createUser();
    $event = Event::factory()->create(['status' => 'published']);

    $response = $this->actingAs($user)->postJson("/api/events/{$event->uuid}/complete");

    $response->assertStatus(200);
    expect($event->fresh()->status)->toBe('completed');
});

test('validates required fields on create', function () {
    $user = createUser();

    $response = $this->actingAs($user)->postJson('/api/events', []);

    $response->assertStatus(422);
});

test('student cannot create an event in their organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $student = createStudent();

    $response = $this->actingAs($student)->postJson('/api/events', [
        'organization_id' => $isc->id,
        'title' => 'Sneaky Event',
        'event_date' => now()->format('Y-m-d'),
    ]);

    $response->assertStatus(403);
    expect(Event::where('title', 'Sneaky Event')->doesntExist())->toBeTrue();
});

test('student cannot update or delete an event in their organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'draft']);
    $student = createStudent();

    $this->actingAs($student)->putJson("/api/events/{$event->uuid}", [
        'title' => 'Hacked Title',
        'event_date' => $event->event_date,
    ])->assertStatus(403);

    $this->actingAs($student)->deleteJson("/api/events/{$event->uuid}")->assertStatus(403);

    expect($event->fresh()->title)->not->toBe('Hacked Title');
    expect(Event::find($event->id))->not->toBeNull();
});

test('officer cannot modify events of another organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'draft']);
    $sscOfficer = createOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->putJson("/api/events/{$event->uuid}", [
        'title' => 'Cross Org',
        'event_date' => $event->event_date,
    ])->assertStatus(403);

    $this->actingAs($sscOfficer)->deleteJson("/api/events/{$event->uuid}")->assertStatus(403);
});

test('officer can modify events created by their own organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'draft']);
    $iscOfficer = createOfficer('ICS-ISC', 'isc_officer');

    $this->actingAs($iscOfficer)->putJson("/api/events/{$event->uuid}", [
        'title' => 'Legit Update',
        'event_date' => $event->event_date,
    ])->assertStatus(200);

    expect($event->fresh()->title)->toBe('Legit Update');
});

test('officer lists only events of their own organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    Event::factory()->create(['organization_id' => $ssc->id, 'title' => 'Own Event']);
    Event::factory()->create(['organization_id' => $isc->id, 'title' => 'Other Org Event']);

    $sscOfficer = createOfficer('SSC', 'ssc_officer');

    $response = $this->actingAs($sscOfficer)->getJson('/api/events');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Own Event');
});

test('officer cannot list events of another organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    Event::factory()->create(['organization_id' => $isc->id]);
    $sscOfficer = createOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->getJson('/api/events?organization_id=' . $isc->id)->assertStatus(403);
});

test('officer cannot view an event of another organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $sscOfficer = createOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->getJson("/api/events/{$event->uuid}")->assertStatus(403);
});

test('officer can view and list their own organization events', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $ssc->id]);
    $sscOfficer = createOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->getJson("/api/events/{$event->uuid}")->assertStatus(200);
    $this->actingAs($sscOfficer)->getJson('/api/events?organization_id=' . $ssc->id)->assertStatus(200);
});

test('pure student can list and view events of their own organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $student = createStudent();

    $this->actingAs($student)->getJson('/api/events?organization_id=' . $isc->id)->assertStatus(200);

    $this->actingAs($student)->getJson("/api/events/{$event->uuid}")->assertStatus(200);
});

test('upcoming scope excludes events whose date and time_to have passed', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    Event::factory()->create([
        'organization_id' => $ssc->id,
        'status' => 'published',
        'event_date' => now()->toDateString(),
        'time_from' => now()->subHours(3)->format('H:i'),
        'time_to' => now()->subHour()->format('H:i'),
    ]);

    Event::factory()->create([
        'organization_id' => $ssc->id,
        'status' => 'published',
        'event_date' => now()->toDateString(),
        'time_from' => now()->addHour()->format('H:i'),
        'time_to' => now()->addHours(2)->format('H:i'),
    ]);

    Event::factory()->create([
        'organization_id' => $ssc->id,
        'status' => 'published',
        'event_date' => now()->addDay()->toDateString(),
        'time_to' => '10:00',
    ]);

    $events = app(\App\Repositories\EventRepository::class)->getUpcoming($ssc->id);

    expect($events->count())->toBe(2);
    expect($events->pluck('status')->all())->toBe(['published', 'published']);
});

test('upcoming scope returns no results when the only event has ended', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    Event::factory()->create([
        'organization_id' => $ssc->id,
        'status' => 'published',
        'event_date' => now()->toDateString(),
        'time_to' => now()->subHour()->format('H:i'),
    ]);

    $events = app(\App\Repositories\EventRepository::class)->getUpcoming($ssc->id);

    expect($events->count())->toBe(0);
});
