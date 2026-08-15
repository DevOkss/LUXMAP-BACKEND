<?php

use App\Enums\UserRole;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function feeHead(): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => UserRole::SSC_HEAD->value,
        'position' => 'SSC Head',
        'assigned_at' => now(),
    ]);

    return $user;
}

test('student lists only posted fees in their scope', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true]);
    $posted = Fee::factory()->create(['organization_id' => $ssc->id, 'name' => 'Assigned Fee', 'status' => 'posted']);
    Fee::factory()->create(['organization_id' => $ssc->id, 'name' => 'Draft Fee', 'status' => 'draft']);

    $response = $this->actingAs($student)->getJson('/api/fees/my');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Assigned Fee')
        ->assertJsonPath('data.0.obligation_status', 'due');
    expect($posted->id)->toBeInt();
});

test('students never see draft fees', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create(['is_enrolled' => true]);
    Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);

    $this->actingAs($student)->getJson('/api/fees/my')
        ->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

test('head can create a fee', function () {
    $head = feeHead();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    $response = $this->actingAs($head)->postJson('/api/fees', [
        'organization_id' => $ssc->id,
        'name' => 'Membership Fee',
        'amount' => 100,
        'term' => '1st Semester',
        'required_years' => ['1', '2'],
        'due_date' => now()->addMonth()->format('Y-m-d'),
    ]);

    $response->assertStatus(201);
    $fee = Fee::where('name', 'Membership Fee')->first();
    expect($fee)->not->toBeNull();
    expect($fee->status)->toBe('draft');
    expect($fee->required_years)->toBe(['1', '2']);
});

test('students cannot create a fee', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $student = User::factory()->create();

    $this->actingAs($student)->postJson('/api/fees', [
        'organization_id' => $ssc->id,
        'name' => 'Sneaky',
        'amount' => 10,
    ])->assertForbidden();
});

test('super admins cannot create a fee', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $superAdmin = User::factory()->create();
    $superAdmin->organizations()->attach($ssc->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'assigned_at' => now(),
    ]);

    $this->actingAs($superAdmin)->postJson('/api/fees', [
        'organization_id' => $ssc->id,
        'name' => 'SA Fee',
        'amount' => 10,
    ])->assertForbidden();
});

test('head can update and delete a fee in their scope', function () {
    $head = feeHead();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $fee = Fee::factory()->create(['organization_id' => $ssc->id]);

    $this->actingAs($head)->putJson("/api/fees/{$fee->id}", [
        'name' => 'Updated',
        'amount' => 150,
        'organization_id' => $ssc->id,
    ])->assertStatus(200);
    expect($fee->fresh()->name)->toBe('Updated');

    $this->actingAs($head)->deleteJson("/api/fees/{$fee->id}")->assertStatus(200);
    expect(Fee::find($fee->id))->toBeNull();
});

test('head cannot update a fee outside their scope', function () {
    $head = feeHead();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $fee = Fee::factory()->create(['organization_id' => $isc->id]);

    $this->actingAs($head)->putJson("/api/fees/{$fee->id}", [
        'name' => 'Cross',
        'amount' => 150,
        'organization_id' => $isc->id,
    ])->assertForbidden();
});

test('validates required fields on create', function () {
    $head = feeHead();

    $this->actingAs($head)->postJson('/api/fees', [])->assertStatus(422);
});
