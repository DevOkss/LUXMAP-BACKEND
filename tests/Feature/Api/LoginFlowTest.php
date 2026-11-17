<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\InstituteSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([
        InstituteSeeder::class,
        OrganizationSeeder::class,
    ]);
});

test('student can login with student number and password', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);

    $response = $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('user.student_number', '2026-00001')
        ->assertJsonPath('user.must_change_password', false)
        ->assertJsonStructure(['token', 'user', 'workspaces']);
});

test('student cannot login with an email address', function () {
    User::factory()->create([
        'student_number' => '2026-00001',
        'email' => 'student@test.com',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);

    $this->postJson('/api/login', [
        'student_number' => 'student@test.com',
        'password' => 'password123',
    ])->assertStatus(422);
});

test('student cannot login with a wrong password', function () {
    User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'The provided credentials are incorrect.');
});

test('unknown student number cannot login', function () {
    $this->postJson('/api/login', [
        'student_number' => '2026-99999',
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'The provided credentials are incorrect.');
});

test('super admin cannot login on the student domain', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'assigned_at' => now(),
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'Super admins must sign in via the Admin Portal.');
});

test('officer can login on the student domain', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => true,
    ]);
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::SSC_OFFICER->value,
        'assigned_at' => now(),
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ])->assertStatus(200);
});

test('not enrolled user cannot login', function () {
    User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('password123'),
        'is_enrolled' => false,
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'password123',
    ])->assertStatus(422)
        ->assertJsonPath('errors.student_number.0', 'This account is not enrolled.');
});

test('temporary password login requires a password change before other endpoints', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('student'),
        'is_enrolled' => true,
        'must_change_password' => true,
    ]);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'student',
    ])->assertStatus(200)
        ->assertJsonPath('user.must_change_password', true);

    Sanctum::actingAs($user);

    $this->getJson('/api/workspaces')
        ->assertStatus(403)
        ->assertJsonPath('code', 'password_change_required');

    $this->getJson('/api/user')->assertStatus(200);
});

test('student can change their temporary password', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('student'),
        'is_enrolled' => true,
        'must_change_password' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/change-password', [
        'current_password' => 'student',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(200)
        ->assertJsonPath('user.must_change_password', false);

    expect($user->fresh()->must_change_password)->toBeFalse();
    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();

    $this->getJson('/api/workspaces')->assertStatus(200);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'student',
    ])->assertStatus(422);

    $this->postJson('/api/login', [
        'student_number' => '2026-00001',
        'password' => 'new-password-123',
    ])->assertStatus(200);
});

test('password change rejects a wrong current password', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('student'),
        'is_enrolled' => true,
        'must_change_password' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/change-password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertStatus(422)
        ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('password change requires confirmation and a minimum length', function () {
    $user = User::factory()->create([
        'student_number' => '2026-00001',
        'password' => bcrypt('student'),
        'is_enrolled' => true,
        'must_change_password' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/change-password', [
        'current_password' => 'student',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertStatus(422);

    $this->postJson('/api/change-password', [
        'current_password' => 'student',
        'password' => 'new-password-123',
        'password_confirmation' => 'different-password',
    ])->assertStatus(422);
});
