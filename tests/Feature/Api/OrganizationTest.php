<?php

use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('can list organizations', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/organizations');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('can show organization with parent and children', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();

    $response = $this->actingAs($user)->getJson("/api/organizations/{$org->id}");

    $response->assertStatus(200);
});

test('can create organization', function () {
    $user = User::factory()->create();
    $parent = Organization::where('code', 'SSC')->first();

    $response = $this->actingAs($user)->postJson('/api/organizations', [
        'parent_id' => $parent->id,
        'name' => 'Test Org',
        'code' => 'TEST-SRO',
        'type' => 'sro',
        'description' => 'Test description',
    ]);

    $response->assertStatus(201);
    expect(Organization::where('code', 'TEST-SRO')->exists())->toBeTrue();
});

test('can update organization', function () {
    $user = User::factory()->create();
    $org = Organization::where('code', 'SSC')->first();

    $response = $this->actingAs($user)->putJson("/api/organizations/{$org->id}", [
        'name' => 'Updated SSC',
        'code' => $org->code,
        'type' => $org->type,
    ]);

    $response->assertStatus(200);
    expect($org->fresh()->name)->toBe('Updated SSC');
});

test('can delete organization', function () {
    $user = User::factory()->create();
    $org = Organization::firstWhere('code', 'BSCS-SRO');

    $response = $this->actingAs($user)->deleteJson("/api/organizations/{$org->id}");

    $response->assertStatus(204);
    expect(Organization::find($org->id))->toBeNull();
});

test('validates required fields on create', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/organizations', []);

    $response->assertStatus(422);
});
