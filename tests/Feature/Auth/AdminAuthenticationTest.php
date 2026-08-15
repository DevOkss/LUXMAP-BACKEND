<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

function adminUser(): User
{
    $user = User::factory()->create();

    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'position' => 'System Administrator',
        'assigned_at' => now(),
    ]);

    return $user;
}

function adminRoleUser(UserRole $role): User
{
    $user = User::factory()->create();

    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('admin login screen can be rendered', function () {
    $this->get('/admin/login')->assertStatus(200);
});

test('super admins can authenticate using the admin login screen', function () {
    $user = adminUser();

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('heads can authenticate using the admin login screen', function () {
    $user = adminRoleUser(UserRole::SSC_HEAD);

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('officers can not authenticate using the admin login screen', function () {
    $user = adminRoleUser(UserRole::SRO_OFFICER);

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('plain users can not authenticate using the admin login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('admin users can logout', function () {
    $user = adminUser();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('admin.login'));
});
