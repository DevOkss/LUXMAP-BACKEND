<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\QrConfiguration;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function exportOfficer(string $orgCode, string $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role,
        'position' => $role,
        'assigned_at' => now(),
    ]);

    return $user;
}

function exportStudent(): User
{
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    return User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);
}

function parseExportSheet(\Illuminate\Testing\TestResponse $response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    $tmp = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
    file_put_contents($tmp, $response->streamedContent());

    return IOFactory::load($tmp)->getActiveSheet();
}

test('officer can download the event attendance export', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'Tech Summit']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_out']);
    $officer = exportOfficer('ICS-ISC', 'isc_officer');

    $response = $this->actingAs($officer)->get("/api/events/{$event->uuid}/attendance/export");

    $response->assertStatus(200);
    $response->assertDownload('tech-summit-attendance.xlsx');
});

test('officer of another organization cannot export attendance', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $sscOfficer = exportOfficer('SSC', 'ssc_officer');

    $this->actingAs($sscOfficer)->get("/api/events/{$event->uuid}/attendance/export")->assertStatus(403);
});

test('student cannot export attendance', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id]);
    $student = exportStudent();

    $this->actingAs($student)->get("/api/events/{$event->uuid}/attendance/export")->assertStatus(403);
});

test('export data builds the attendance matrix per qr configuration', function () {
    $event = Event::factory()->create(['title' => 'Matrix Event']);
    $configIn = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    $configOut = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_out']);

    $student = User::factory()->create(['student_number' => '2020-0001', 'name' => 'Jane Doe']);
    $other = User::factory()->create(['student_number' => '2020-0002', 'name' => 'Zoe Ann']);

    Attendance::create([
        'qr_configuration_id' => $configIn->id,
        'user_id' => $student->id,
        'scanned_at' => now()->setTime(8, 45),
    ]);
    Attendance::create([
        'qr_configuration_id' => $configOut->id,
        'user_id' => $student->id,
        'scanned_at' => now()->setTime(17, 10),
    ]);
    Attendance::create([
        'qr_configuration_id' => $configIn->id,
        'user_id' => $other->id,
        'scanned_at' => now()->setTime(8, 50),
    ]);

    $data = app(AttendanceService::class)->getEventExportData($event);

    expect($data['qr_configs'])->toHaveCount(2);
    expect($data['qr_configs'][0]['type'])->toBe('time_in');
    expect($data['qr_configs'][0]['valid_from'])->toBe($configIn->valid_from);
    expect($data['qr_configs'][0]['valid_until'])->toBe($configIn->valid_until);
    expect($data['rows'])->toHaveCount(2);
    expect($data['rows'][0]['name'])->toBe('Jane Doe');
    expect($data['rows'][0]['times'][$configIn->id])->toBe('04:45 PM');
    expect($data['rows'][0]['times'][$configOut->id])->toBe('01:10 AM');
    expect($data['rows'][1]['times'][$configOut->id])->toBeNull();
});

test('export times are converted to manila time', function () {
    $event = Event::factory()->create(['title' => 'TZ Event']);
    $config = QrConfiguration::factory()->create(['event_id' => $event->id, 'type' => 'time_in']);
    $student = User::factory()->create(['student_number' => '2020-0009', 'name' => 'TZ Student']);

    Attendance::create([
        'qr_configuration_id' => $config->id,
        'user_id' => $student->id,
        'scanned_at' => Carbon::parse('2026-08-07 08:25:00', 'UTC'),
    ]);

    $data = app(AttendanceService::class)->getEventExportData($event);

    expect($data['rows'][0]['times'][$config->id])->toBe('04:25 PM');
});

test('export header uses the qr valid window formatted with am/pm', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $event = Event::factory()->create(['organization_id' => $isc->id, 'title' => 'General Assembly']);
    QrConfiguration::factory()->create([
        'event_id' => $event->id,
        'type' => 'time_in',
        'valid_from' => '05:00',
        'valid_until' => '06:30',
    ]);
    $officer = exportOfficer('ICS-ISC', 'isc_officer');

    $response = $this->actingAs($officer)->get("/api/events/{$event->uuid}/attendance/export");

    $sheet = parseExportSheet($response);

    expect($sheet->getCell([1, 1])->getValue())->toBe('EVENT NAME');
    expect($sheet->getCell([2, 1])->getValue())->toBe('General Assembly');
    expect($sheet->getCell([3, 5])->getValue())->toBe('TIME IN (05:00 AM - 06:30 AM)');
});
