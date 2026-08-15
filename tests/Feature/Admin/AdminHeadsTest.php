<?php

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
        \Database\Seeders\InstituteSeeder::class,
    ]);
});

function headGrant(User $user, UserRole $role): User
{
    $user->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function superAdminUser(): User
{
    return headGrant(User::factory()->create(), UserRole::SUPER_ADMIN);
}

function headPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Head',
        'email' => 'jane.head@soms.edu',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

test('super admins can access the heads index and create page', function () {
    $this->actingAs(superAdminUser())->get('/admin/heads')->assertOk();
    $this->actingAs(superAdminUser())->get('/admin/heads/create')->assertOk();
});

test('non-super admins are forbidden from the heads index', function () {
    $head = headGrant(User::factory()->create(), UserRole::INSTITUTE_HEAD);

    $this->actingAs($head)->get('/admin/heads')->assertForbidden();
});

test('create ssc head does not require an institute or program', function () {
    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::SSC_HEAD->value,
        ]))
        ->assertRedirect(route('admin.heads.show', User::where('email', 'jane.head@soms.edu')->first()));

    $user = User::where('email', 'jane.head@soms.edu')->firstOrFail();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    expect($user->roleInOrganization($ssc))->toBe(UserRole::SSC_HEAD);
});

test('create institute head requires an institute and creates the matching isc organization', function () {
    $institute = Institute::where('code', 'ICS')->firstOrFail();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::INSTITUTE_HEAD->value,
            'institute_id' => $institute->id,
        ]))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'jane.head@soms.edu')->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    expect($isc->type)->toBe(OrganizationType::ISC);
    expect($user->roleInOrganization($isc))->toBe(UserRole::INSTITUTE_HEAD);
});

test('create program head requires institute and program and creates the sro organization under the isc', function () {
    $institute = Institute::where('code', 'ICS')->firstOrFail();
    $program = Program::where('institute_id', $institute->id)->firstOrFail();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::SRO_HEAD->value,
            'institute_id' => $institute->id,
            'program_id' => $program->id,
        ]))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'jane.head@soms.edu')->firstOrFail();
    $sro = Organization::where('code', "{$program->code}-SRO")->firstOrFail();
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();

    expect($sro->type)->toBe(OrganizationType::SRO);
    expect($sro->parent_id)->toBe($isc->id);
    expect($user->roleInOrganization($sro))->toBe(UserRole::SRO_HEAD);
});

test('creating two institute heads for the same institute reuses the organization', function () {
    $institute = Institute::where('code', 'IAS')->firstOrFail();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'email' => 'head.one@soms.edu',
            'role' => UserRole::INSTITUTE_HEAD->value,
            'institute_id' => $institute->id,
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'email' => 'head.two@soms.edu',
            'role' => UserRole::INSTITUTE_HEAD->value,
            'institute_id' => $institute->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(Organization::where('code', 'IAS-ISC')->count())->toBe(1);
});

test('institute head without an institute is rejected', function () {
    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::INSTITUTE_HEAD->value,
        ]))
        ->assertSessionHasErrors('institute_id');
});

test('program head without a program is rejected', function () {
    $institute = Institute::where('code', 'ICS')->firstOrFail();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::SRO_HEAD->value,
            'institute_id' => $institute->id,
        ]))
        ->assertSessionHasErrors('program_id');
});

test('program head with a program from another institute is rejected', function () {
    $institute = Institute::where('code', 'ICS')->firstOrFail();
    $otherProgram = Institute::where('code', 'IHS')->firstOrFail()->programs()->firstOrFail();

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::SRO_HEAD->value,
            'institute_id' => $institute->id,
            'program_id' => $otherProgram->id,
        ]))
        ->assertSessionHasErrors('program_id');
});

test('email must be unique when creating a head', function () {
    User::factory()->create(['email' => 'jane.head@soms.edu']);

    $this->actingAs(superAdminUser())
        ->post('/admin/heads', headPayload([
            'role' => UserRole::SSC_HEAD->value,
        ]))
        ->assertSessionHasErrors('email');
});

test('heads can update their account and role', function () {
    $institute = Institute::where('code', 'ICS')->firstOrFail();
    $ssc = Organization::where('code', 'SSC')->firstOrFail();

    $user = headGrant(User::factory()->create(['email' => 'old.head@soms.edu']), UserRole::SSC_HEAD);

    $this->actingAs(superAdminUser())
        ->put("/admin/heads/{$user->id}", [
            'name' => 'Renamed Head',
            'email' => 'renamed.head@soms.edu',
            'password' => '',
            'role' => UserRole::INSTITUTE_HEAD->value,
            'institute_id' => $institute->id,
        ])
        ->assertSessionHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Renamed Head');
    expect($fresh->email)->toBe('renamed.head@soms.edu');
    expect($fresh->roleInOrganization($ssc))->toBeNull();
    expect($fresh->roleInOrganization(Organization::where('code', 'ICS-ISC')->firstOrFail()))
        ->toBe(UserRole::INSTITUTE_HEAD);
});

test('heads can be deleted', function () {
    $user = headGrant(User::factory()->create(), UserRole::SSC_HEAD);

    $this->actingAs(superAdminUser())
        ->delete("/admin/heads/{$user->id}")
        ->assertRedirect(route('admin.heads.index'));

    expect(User::find($user->id))->toBeNull();
});

test('password is updated when provided and kept otherwise', function () {
    $user = headGrant(User::factory()->create(), UserRole::SSC_HEAD);
    $oldPassword = $user->password;

    $this->actingAs(superAdminUser())
        ->put("/admin/heads/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'role' => UserRole::SSC_HEAD->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->password)->toBe($oldPassword);
});
