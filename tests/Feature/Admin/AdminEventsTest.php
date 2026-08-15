<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventLog;
use App\Models\Organization;
use App\Models\QrConfiguration;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function adminEventsUser(string $orgCode, UserRole $role): User
{
    $user = User::factory()->create(['student_number' => '2026-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT)]);
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('admin events index exposes uuid and is scoped to the officer organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    Event::factory()->create(['organization_id' => $isc->id, 'title' => 'ISC Event']);
    Event::factory()->create(['organization_id' => $ssc->id, 'title' => 'SSC Event']);

    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $response = $this->actingAs($officer)->get('/admin/events');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/events/Index')
            ->has('events.data', 1)
            ->where('events.data.0.title', 'ISC Event')
            ->where('events.data.0.uuid', Event::where('title', 'ISC Event')->first()->uuid));
});

test('calendar exposes event uuid for officers', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'Calendar Event']);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->get('/admin/calendar')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/calendar/Index')
            ->where('events.0.uuid', $event->uuid));
});

test('heads can view events but cannot manage them', function () {
    $head = adminEventsUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    $this->actingAs($head)->get('/admin/events')->assertOk();
    $this->actingAs($head)->get('/admin/events/create')->assertForbidden();
});

test('officer can create a draft event in their own organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $response = $this->actingAs($officer)->post('/admin/events', [
        'organization_id' => $isc->id,
        'title' => 'New Draft Activity',
        'event_date' => now()->format('Y-m-d'),
    ]);

    $response->assertRedirect();
    expect(Event::where('title', 'New Draft Activity')->where('organization_id', $isc->id)->exists())->toBeTrue();
});

test('officer cannot create an event in another organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post('/admin/events', [
        'organization_id' => $ssc->id,
        'title' => 'Cross Org',
        'event_date' => now()->format('Y-m-d'),
    ])->assertForbidden();
});

test('officer can manage events of their own organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'draft']);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/publish")->assertRedirect();
    expect($event->fresh()->status)->toBe('published');

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/unpublish")->assertRedirect();
    expect($event->fresh()->status)->toBe('draft');

    $this->actingAs($officer)->delete("/admin/events/{$event->uuid}")->assertRedirect();
    expect(Event::find($event->id))->toBeNull();
});

test('officer cannot manage events of another organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/publish")->assertForbidden();
    $this->actingAs($officer)->delete("/admin/events/{$event->uuid}")->assertForbidden();
});

test('officer cannot access head-only or admin-only modules', function () {
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    foreach (['/admin/heads', '/admin/users', '/admin/institutes', '/admin/students', '/admin/officers'] as $path) {
        $response = $this->actingAs($officer)->get($path);
        $response->assertForbidden("Expected 403 for {$path}");
    }
});

test('qr configuration reuse copies geofence and required years from the last session', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $previous = QrConfiguration::factory()->create([
        'event_id' => $event->id,
        'type' => 'time_in',
        'latitude' => 8.065254,
        'longitude' => 123.756733,
        'geofence_radius' => 150,
        'required_years' => ['1', '2'],
    ]);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/qr-configurations", [
        'type' => 'time_out',
        'valid_from' => '08:00',
        'valid_until' => '17:00',
        'reuse_from' => $previous->id,
    ])->assertRedirect();

    $config = QrConfiguration::where('event_id', $event->id)->where('type', 'time_out')->first();
    expect($config)->not->toBeNull();
    expect((float) $config->latitude)->toBe(8.065254);
    expect((float) $config->longitude)->toBe(123.756733);
    expect($config->geofence_radius)->toBe(150);
    expect($config->required_years)->toBe(['1', '2']);
});

test('officer can download the qr configuration as a pdf', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'PDF Event']);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in', 'is_generated' => true]);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $response = $this->actingAs($officer)->get("/admin/events/{$event->uuid}/qr-configurations/{$config->id}/download");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('heads cannot download the qr pdf (view-only)', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in', 'is_generated' => true]);
    $head = adminEventsUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    $this->actingAs($head)->get("/admin/events/{$event->uuid}/qr-configurations/{$config->id}/download")->assertForbidden();
});

test('removing a qr configuration deletes its recorded attendances', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    $student = User::factory()->create();

    Attendance::create([
        'qr_configuration_id' => $config->id,
        'user_id' => $student->id,
        'scanned_at' => now(),
    ]);

    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->delete("/admin/events/{$event->uuid}/qr-configurations/{$config->id}")->assertRedirect();

    expect(QrConfiguration::find($config->id))->toBeNull();
    expect(Attendance::where('qr_configuration_id', $config->id)->count())->toBe(0);
});

test('officer event actions are logged with the officer identity', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post('/admin/events', [
        'organization_id' => $isc->id,
        'title' => 'Logged Event',
        'event_date' => now()->format('Y-m-d'),
    ])->assertRedirect();

    $event = Event::where('title', 'Logged Event')->firstOrFail();

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/publish")->assertRedirect();

    $actions = EventLog::where('event_id', $event->id)->where('user_id', $officer->id)->pluck('action')->all();

    expect($actions)->toContain('created', 'published');
});

test('qr configuration actions are logged', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/qr-configurations", [
        'type' => 'time_in',
        'valid_from' => '08:00',
        'valid_until' => '17:00',
    ])->assertRedirect();

    $config = QrConfiguration::where('event_id', $event->id)->firstOrFail();

    $log = EventLog::where('event_id', $event->id)->where('user_id', $officer->id)->where('action', 'qr_created')->first();
    expect($log)->not->toBeNull();
    expect($log->details['config_id'])->toBe($config->id);
});

test('activity log is scoped to the head organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $iscOfficer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);
    $sscOfficer = adminEventsUser('SSC', UserRole::SSC_OFFICER);

    $iscEvent = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'ISC Logged']);
    $sscEvent = Event::factory()->create(['organization_id' => $ssc->id, 'title' => 'SSC Logged']);
    EventLog::create(['event_id' => $iscEvent->id, 'user_id' => $iscOfficer->id, 'action' => 'created']);
    EventLog::create(['event_id' => $sscEvent->id, 'user_id' => $sscOfficer->id, 'action' => 'created']);

    $head = adminEventsUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    $this->actingAs($head)->get('/admin/activity-logs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-logs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.event.title', 'ISC Logged')
            ->where('logs.data.0.user.name', $iscOfficer->name));
});

test('activity log supports date range filtering', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $officer = adminEventsUser('ICS-ISC', UserRole::ISC_OFFICER);
    $event = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'Filtered Event']);

    $oldLog = EventLog::create(['event_id' => $event->id, 'user_id' => $officer->id, 'action' => 'created']);
    $oldLog->forceFill(['created_at' => now()->subDays(5)])->save();
    EventLog::create(['event_id' => $event->id, 'user_id' => $officer->id, 'action' => 'updated']);

    $head = adminEventsUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    $this->actingAs($head)->get('/admin/activity-logs?from=' . now()->subDay()->format('Y-m-d'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/activity-logs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'updated'));
});

test('super admins cannot manage events (view-only)', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'status' => 'draft']);
    $user = adminEventsUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($user)->get('/admin/events')->assertOk();

    $this->actingAs($user)->post('/admin/events', [
        'organization_id' => $isc->id,
        'title' => 'Super Admin Event',
        'event_date' => now()->format('Y-m-d'),
    ])->assertForbidden();

    $this->actingAs($user)->post("/admin/events/{$event->uuid}/publish")->assertForbidden();
});
