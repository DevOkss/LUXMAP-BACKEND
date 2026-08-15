<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function adviserGrant(User $user, UserRole $role, Organization $org): User
{
    $user->organizations()->attach($org->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function adviserAdminUser(UserRole $role = UserRole::SSC_HEAD): User
{
    $code = match ($role) {
        UserRole::SSC_HEAD => 'SSC',
        UserRole::INSTITUTE_HEAD => 'ICS-ISC',
        UserRole::SRO_HEAD => 'BSCS-SRO',
        default => 'SSC',
    };

    return adviserGrant(
        User::factory()->create(),
        $role,
        Organization::where('code', $code)->firstOrFail()
    );
}

test('only the ssc head can access the advisers index', function () {
    $this->actingAs(adviserAdminUser(UserRole::SSC_HEAD))->get('/admin/advisers')->assertOk();
    $this->actingAs(adviserAdminUser(UserRole::INSTITUTE_HEAD))->get('/admin/advisers')->assertForbidden();
    $this->actingAs(adviserAdminUser(UserRole::SRO_HEAD))->get('/admin/advisers')->assertForbidden();
});

test('super admins, officers and students are forbidden from the advisers index', function () {
    $this->actingAs(User::factory()->create())->get('/admin/advisers')->assertForbidden();

    $sro = Organization::where('code', 'BSCS-SRO')->firstOrFail();
    $this->actingAs(adviserGrant(User::factory()->create(), UserRole::SRO_OFFICER, $sro))->get('/admin/advisers')->assertForbidden();
});

test('ssc head sees only advisers in their own scope', function () {
    $head = adviserAdminUser(UserRole::SSC_HEAD);

    adviserGrant(User::factory()->create(['name' => 'ISC Adviser One']), UserRole::INSTITUTE_HEAD, Organization::where('code', 'ICS-ISC')->firstOrFail());
    adviserGrant(User::factory()->create(['name' => 'SRO Adviser One']), UserRole::SRO_HEAD, Organization::where('code', 'BSCS-SRO')->firstOrFail());

    $this->actingAs($head)->get('/admin/advisers')
        ->assertInertia(fn ($page) => $page
            ->component('admin/advisers/Index')
            ->has('advisers', 0));
});

test('advisers index does not include ssc heads or junior officers', function () {
    $head = adviserAdminUser(UserRole::SSC_HEAD);

    adviserGrant(User::factory()->create(['name' => 'Another SSC Head']), UserRole::SSC_HEAD, Organization::where('code', 'SSC')->firstOrFail());
    adviserGrant(User::factory()->create(['name' => 'Junior Officer']), UserRole::SSC_OFFICER, Organization::where('code', 'SSC')->firstOrFail());

    $this->actingAs($head)->get('/admin/advisers')
        ->assertInertia(fn ($page) => $page
            ->component('admin/advisers/Index')
            ->has('advisers', 0));
});
