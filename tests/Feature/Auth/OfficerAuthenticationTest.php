<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

function officerUser(): User
{
    $user = User::factory()->create([
        'student_number' => '2026-0001',
    ]);

    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::SRO_OFFICER->value,
        'position' => 'SRO Officer',
        'assigned_at' => now(),
    ]);

    return $user;
}

test('officer login screen can be rendered', function () {
    $this->get('/login')->assertStatus(200);
});

test('officers can authenticate using their student ID number', function () {
    $user = officerUser();

    $response = $this->post('/login', [
        'student_number' => $user->student_number,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('students can not authenticate using the officer login screen', function () {
    $user = User::factory()->create([
        'student_number' => '2026-0002',
    ]);

    $response = $this->post('/login', [
        'student_number' => $user->student_number,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('student_number');
});

test('heads can not authenticate using the officer login screen', function () {
    $user = User::factory()->create([
        'student_number' => '2026-0003',
    ]);

    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::INSTITUTE_HEAD->value,
        'position' => 'Institute Head',
        'assigned_at' => now(),
    ]);

    $response = $this->post('/login', [
        'student_number' => $user->student_number,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('student_number');
});

test('officers can not authenticate with invalid password', function () {
    $user = officerUser();

    $this->post('/login', [
        'student_number' => $user->student_number,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('officers can logout', function () {
    $user = officerUser();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
