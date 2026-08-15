<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

test('student can register', function () {
    $sro = Organization::where('code', 'BSCS-SRO')->first();

    $response = $this->postJson('/api/register', [
        'name' => 'Test Student',
        'email' => 'student@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'student_number' => '2026-00001',
        'institute' => 'ICS',
        'program' => 'BSCS',
        'year_level' => 1,
    ]);

    $response->assertStatus(201);
    expect(User::where('email', 'student@test.com')->exists())->toBeTrue();
});

test('user can login', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::STUDENT->value,
        'assigned_at' => now(),
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user', 'workspaces']);
});

test('login fails with invalid credentials', function () {
    $response = $this->postJson('/api/login', [
        'student_number' => '2026-99999',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});

test('authenticated user can fetch profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJsonPath('user.id', $user->id);
});

test('unauthenticated user cannot fetch profile', function () {
    $response = $this->getJson('/api/user');

    $response->assertStatus(401);
});

test('user can list workspaces', function () {
    $user = User::factory()->create();
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::STUDENT->value,
        'assigned_at' => now(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/workspaces');

    $response->assertStatus(200);
});

test('user can switch workspace', function () {
    $user = User::factory()->create();
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::STUDENT->value,
        'assigned_at' => now(),
    ]);

    $response = $this->actingAs($user)->putJson("/api/workspace/{$ssc->id}");

    $response->assertStatus(200);
});

test('user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $response->assertStatus(200);
});
