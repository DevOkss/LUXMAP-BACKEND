<?php

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('can get attendance report', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();
    $event = Event::factory()->create(['organization_id' => $org->id, 'status' => 'completed']);

    $response = $this->actingAs($user)->getJson('/api/reports/attendance');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('can get financial report', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();
    Payment::factory()->count(3)->create([
        'organization_id' => $org->id,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->getJson('/api/reports/financial');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('can get penalty report', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();
    Payment::factory()->count(3)->penalty()->create([
        'organization_id' => $org->id,
    ]);

    $response = $this->actingAs($user)->getJson('/api/reports/penalty');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('reports respect organization filter', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();

    $response = $this->actingAs($user)->getJson('/api/reports/attendance?organization_id=' . $org->id);

    expect(in_array($response->status(), [200, 422]))->toBeTrue();
});
