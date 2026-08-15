<?php

use App\Console\Commands\FeeDueDateReminder;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationService;

function seedUserRecipients(string $orgCode): array
{
    $org = Organization::where('code', $orgCode)->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true]);
    $officer = User::factory()->create();
    $officer->organizations()->attach($org->id, [
        'role' => \App\Enums\UserRole::SSC_OFFICER->value,
        'position' => 'officer',
        'assigned_at' => now(),
    ]);

    return [$org, $student, $officer];
}

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('fee due reminder notifies student and officer recipients', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');

    $fee = Fee::factory()->create([
        'organization_id' => $org->id,
        'status' => 'posted',
        'due_date' => now()->addDays(2),
    ]);

    app(NotificationService::class)->notifyFeeDue($fee);

    expect($student->fresh()->notifications()->count())->toBe(1);
    expect($officer->fresh()->notifications()->count())->toBe(1);
    expect($student->fresh()->notifications()->first()->data['data']['type'])->toBe('fee_due');
});

test('fee posted notification is triggered on admin publish', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');
    $head = User::factory()->create();
    $head->organizations()->attach($org->id, [
        'role' => \App\Enums\UserRole::SSC_HEAD->value,
        'position' => 'head',
        'assigned_at' => now(),
    ]);

    $fee = Fee::factory()->create(['organization_id' => $org->id, 'status' => 'draft']);

    $this->actingAs($head)->post("/admin/fees/{$fee->id}/publish")->assertRedirect();

    expect($student->fresh()->notifications()->count())->toBe(1);
    expect($officer->fresh()->notifications()->count())->toBe(1);
    expect($student->fresh()->notifications()->first()->data['data']['type'])->toBe('fee_posted');
});

test('fee_due_reminder command only picks fees due within three days', function () {
    app(NotificationService::class);
    [$org, $student, $officer] = seedUserRecipients('SSC');

    Fee::factory()->create([
        'organization_id' => $org->id,
        'status' => 'posted',
        'due_date' => now()->addDays(2),
    ]);

    $this->artisan('notifications:fees-due')
        ->expectsOutputToContain('1 fee(s)')
        ->assertSuccessful();

    expect($student->fresh()->notifications()->count())->toBe(1);
});

test('fee outside the 3-day window is not reminded', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');

    Fee::factory()->create([
        'organization_id' => $org->id,
        'status' => 'posted',
        'due_date' => now()->addDays(6),
    ]);

    $this->artisan('notifications:fees-due')->assertSuccessful();

    expect($student->fresh()->notifications()->count())->toBe(0);
});

test('--days override widens the reminder window', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');

    Fee::factory()->create([
        'organization_id' => $org->id,
        'status' => 'posted',
        'due_date' => now()->addDays(6),
    ]);

    $this->artisan('notifications:fees-due', ['--days' => 7])
        ->expectsOutputToContain('1 fee(s)')
        ->assertSuccessful();

    expect($student->fresh()->notifications()->count())->toBe(1);
});

test('--days=0 only matches fees due today', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');

    Fee::factory()->create([
        'organization_id' => $org->id,
        'status' => 'posted',
        'due_date' => now()->addDays(2),
    ]);

    $this->artisan('notifications:fees-due', ['--days' => 0])->assertSuccessful();

    expect($student->fresh()->notifications()->count())->toBe(0);
});

test('event posted notification notifies student and officer recipients', function () {
    [$org, $student, $officer] = seedUserRecipients('SSC');

    $event = \App\Models\Event::factory()->create([
        'organization_id' => $org->id,
        'status' => 'draft',
    ]);

    $this->actingAs($officer)->post("/admin/events/{$event->uuid}/publish")->assertRedirect();

    expect($student->fresh()->notifications()->count())->toBe(1);
    expect($officer->fresh()->notifications()->count())->toBe(1);
    expect($student->fresh()->notifications()->first()->data['data']['type'])->toBe('event_posted');
});
