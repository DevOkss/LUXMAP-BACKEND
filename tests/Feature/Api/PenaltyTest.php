<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PenaltyFee;
use App\Models\QrConfiguration;
use App\Models\User;
use App\Services\PenaltyService;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('outstanding penalty is computed per missing required QR configuration', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    $qrA = QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);

    Attendance::factory()->create([
        'user_id' => $student->id,
        'qr_configuration_id' => $qrA->id,
    ]);

    $penalties = app(PenaltyService::class)->studentOutstanding($student);

    expect($penalties)->toHaveCount(1);
    $penalty = $penalties->first();
    expect($penalty['absences'])->toBe(2);
    expect($penalty['amount'])->toBe(100.0);
    expect($penalty['event']['id'])->toBe($event->id);
    expect($penalty['missing_qr_configurations'])->toHaveCount(2);
});

test('fully attended required event produces no penalty', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    $qrA = QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);
    $qrB = QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);

    Attendance::factory()->create(['user_id' => $student->id, 'qr_configuration_id' => $qrA->id]);
    Attendance::factory()->create(['user_id' => $student->id, 'qr_configuration_id' => $qrB->id]);

    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});

test('penalty uses the latest per-organization penalty amount', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    PenaltyFee::create(['organization_id' => $org->id, 'amount' => 25, 'effective_at' => now()]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['all']]);

    $penalty = app(PenaltyService::class)->studentOutstanding($student)->first();

    expect($penalty['absences'])->toBe(1);
    expect($penalty['amount'])->toBe(25.0);
});

test('settled penalty (paid payment) is excluded from outstanding', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);

    Payment::factory()->penalty()->create([
        'user_id' => $student->id,
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'status' => 'paid',
    ]);

    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});

test('exempted penalty is excluded from outstanding', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);

    Payment::factory()->penalty()->create([
        'user_id' => $student->id,
        'organization_id' => $org->id,
        'event_id' => $event->id,
        'status' => 'pending',
        'isExempted' => true,
    ]);

    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});

test('penalty computation never persists to the payments table', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['1']]);

    app(PenaltyService::class)->studentOutstanding($student);
    app(PenaltyService::class)->studentOutstanding($student);

    expect(Payment::isPenalty()->where('user_id', $student->id)->count())->toBe(0);
});

test('only ended published events count toward outstanding penalties', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'published',
        'event_date' => now()->subDay()->toDateString(),
        'time_to' => now()->subDay()->subHour()->format('H:i'),
    ]);
    QrConfiguration::factory()->create([
        'event_id' => Event::where('status', 'published')->firstOrFail()->id,
        'required_years' => ['all'],
    ]);

    $penalties = app(PenaltyService::class)->studentOutstanding($student);

    expect($penalties)->toHaveCount(1);
    expect($penalties->first()['event']['id'])->toBe(
        Event::where('status', 'published')->firstOrFail()->id
    );
});

test('draft and future events do not count toward outstanding penalties', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'draft',
        'event_date' => now()->addDay()->toDateString(),
    ]);
    QrConfiguration::factory()->create([
        'event_id' => Event::where('status', 'draft')->firstOrFail()->id,
        'required_years' => ['all'],
    ]);

    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});

test('ongoing events do not count toward outstanding penalties', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true, 'year_level' => 1]);

    Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'ongoing',
        'event_date' => now()->subDay()->toDateString(),
        'time_to' => now()->subDay()->subHour()->format('H:i'),
    ]);
    QrConfiguration::factory()->create([
        'event_id' => Event::where('status', 'ongoing')->firstOrFail()->id,
        'required_years' => ['all'],
    ]);

    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});

test('exempting a penalty obligation marks it exempted', function () {
    $org = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create();
    $head = User::factory()->create();

    \App\Models\AcademicTerm::factory()->create(['is_active' => true]);

    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);
    QrConfiguration::factory()->create(['event_id' => $event->id, 'required_years' => ['all']]);

    app(\App\Services\PaymentService::class)->exemptObligations(
        $head,
        $student,
        [
            'organization_id' => $org->id,
            'items' => [
                ['type' => Payment::TYPE_PENALTY, 'id' => $event->id, 'amount' => 100.0],
            ],
        ],
        'Waived by organization'
    );

    $payment = Payment::where('user_id', $student->id)->isPenalty()->firstOrFail();
    expect($payment->isExempted)->toBeTrue();
    expect($payment->exempted_by)->toBe($head->id);
    expect($payment->payment_method)->toBe('exemption');
    expect(app(PenaltyService::class)->studentOutstanding($student))->toHaveCount(0);
});
