<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function grantRole(User $user, UserRole $role, Organization $org): User
{
    $user->organizations()->attach($org->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function superAdmin(): User
{
    return grantRole(
        User::factory()->create(),
        UserRole::SUPER_ADMIN,
        Organization::where('code', 'SSC')->firstOrFail()
    );
}

function headUser(UserRole $role = UserRole::INSTITUTE_HEAD): User
{
    $code = $role === UserRole::SSC_HEAD ? 'SSC' : 'ICS-ISC';

    return grantRole(
        User::factory()->create(),
        $role,
        Organization::where('code', $code)->firstOrFail()
    );
}

test('super admins can access the users index', function () {
    $this->actingAs(superAdmin())->get('/admin/users')->assertOk();
});

test('heads and students are forbidden from the users index', function () {
    $this->actingAs(headUser())->get('/admin/users')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
});

test('super admins can view a single user', function () {
    $target = User::factory()->create();

    $this->actingAs(superAdmin())->get("/admin/users/{$target->id}")->assertOk();
});

test('users page is view-only for super admins (no create or role mutations)', function () {
    $ics = Organization::where('code', 'ICS-ISC')->firstOrFail();

    $this->actingAs(superAdmin())->post('/admin/users', [
        'name' => 'Alice',
        'email' => 'alice.head@tcgc.edu.ph',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::INSTITUTE_HEAD->value,
        'organization_id' => $ics->id,
    ])->assertStatus(405);

    $this->actingAs(superAdmin())->post("/admin/users/{User::factory()->create()->id}/assign-role", [
        'role' => UserRole::INSTITUTE_HEAD->value,
        'organization_id' => $ics->id,
    ])->assertStatus(404);
});
