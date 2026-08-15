<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionRegistry;

beforeEach(function () {
    $this->registry = app(PermissionRegistry::class);
});

function registryUser(UserRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('super admin permissions include every capability and module', function () {
    $permissions = $this->registry->permissionsFor(registryUser(UserRole::SUPER_ADMIN));

    expect($permissions['can_manage_all'])->toBeTrue();
    expect($permissions['role'])->toBe(UserRole::SUPER_ADMIN->value);
    expect($permissions['capabilities'])->toContain('manage_users');
    expect($permissions['capabilities'])->toContain('manage_institutes');
    expect($permissions['modules'])->toContain('users');
    expect($permissions['modules'])->toContain('institutes');
    expect($permissions['modules'])->not->toContain('fees');
    expect($permissions['modules'])->not->toContain('payments');
    expect($permissions['modules'])->not->toContain('payment_accounts');
    expect($permissions['modules'])->not->toContain('payment_submissions');
});

test('institute head permissions include officer management but not user management', function () {
    $permissions = $this->registry->permissionsFor(registryUser(UserRole::INSTITUTE_HEAD));

    expect($permissions['can_manage_all'])->toBeFalse();
    expect($permissions['capabilities'])->toContain('manage_officers');
    expect($permissions['capabilities'])->toContain('view_events');
    expect($permissions['capabilities'])->not->toContain('manage_users');
    expect($permissions['modules'])->toContain('officers');
    expect($permissions['modules'])->not->toContain('users');
    expect($permissions['modules'])->not->toContain('institutes');
});

test('head permissions include read-only monitor modules across head roles', function () {
    foreach ([UserRole::SSC_HEAD, UserRole::INSTITUTE_HEAD, UserRole::SRO_HEAD] as $role) {
        $permissions = $this->registry->permissionsFor(registryUser($role));

        expect($permissions['modules'])->toContain('events');
        expect($permissions['modules'])->toContain('fees');
        expect($permissions['modules'])->toContain('payments');
        expect($permissions['modules'])->toContain('penalties');
        expect($permissions['modules'])->toContain('calendar');
        expect($permissions['modules'])->not->toContain('users');
        expect($permissions['modules'])->not->toContain('institutes');
        expect($permissions['modules'])->not->toContain('payment_submissions');
        expect($permissions['capabilities'])->not->toContain('manage_events');
    }
});

test('officer permissions expose officer modules without management capabilities', function () {
    foreach ([UserRole::SSC_OFFICER, UserRole::ISC_OFFICER, UserRole::SRO_OFFICER] as $role) {
        $permissions = $this->registry->permissionsFor(registryUser($role));

        expect($permissions['can_manage_all'])->toBeFalse();
        expect($permissions['capabilities'])->toContain('manage_events');
        expect($permissions['capabilities'])->not->toContain('manage_officers');
        expect($permissions['capabilities'])->toContain('view_fees');
        expect($permissions['capabilities'])->not->toContain('manage_fees');
        expect($permissions['capabilities'])->not->toContain('manage_payment_accounts');
        expect($permissions['modules'])->toContain('events');
        expect($permissions['modules'])->toContain('fees');
        expect($permissions['modules'])->toContain('payments');
        expect($permissions['modules'])->toContain('payment_accounts');
        expect($permissions['modules'])->not->toContain('payment_submissions');
        expect($permissions['modules'])->not->toContain('users');
    }
});

test('student permissions expose student modules only', function () {
    $permissions = $this->registry->permissionsFor(User::factory()->create());

    expect($permissions['role'])->toBe(UserRole::STUDENT->value);
    expect($permissions['can_manage_all'])->toBeFalse();
    expect($permissions['capabilities'])->not->toContain('manage_events');
    expect($permissions['modules'])->toContain('attendance_scanner');
    expect($permissions['modules'])->toContain('fees');
    expect($permissions['modules'])->not->toContain('events');
});

test('resolve role honors the highest precedence when assigned multiple roles', function () {
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::SRO_OFFICER->value,
        'assigned_at' => now(),
    ]);
    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::SSC_HEAD->value,
        'assigned_at' => now(),
    ]);

    expect($this->registry->resolveRoleFor($user))->toBe(UserRole::SSC_HEAD);
});
