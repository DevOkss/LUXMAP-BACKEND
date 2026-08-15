<?php

use App\Enums\UserRole;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function officerGrant(User $user, UserRole $role, Organization $org): User
{
    $user->organizations()->attach($org->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function officerAdminUser(UserRole $role = UserRole::INSTITUTE_HEAD): User
{
    $code = match ($role) {
        UserRole::SSC_HEAD => 'SSC',
        UserRole::INSTITUTE_HEAD => 'ICS-ISC',
        UserRole::SRO_HEAD => 'BSCS-SRO',
        UserRole::SSC_OFFICER, UserRole::ISC_OFFICER, UserRole::SRO_OFFICER => 'BSCS-SRO',
        default => 'SSC',
    };

    return officerGrant(
        User::factory()->create(),
        $role,
        Organization::where('code', $code)->firstOrFail()
    );
}

test('heads can access the officers index', function () {
    $this->actingAs(officerAdminUser(UserRole::INSTITUTE_HEAD))->get('/admin/officers')->assertOk();
    $this->actingAs(officerAdminUser(UserRole::SSC_HEAD))->get('/admin/officers')->assertOk();
    $this->actingAs(officerAdminUser(UserRole::SRO_HEAD))->get('/admin/officers')->assertOk();
});

test('super admins, officers and students are forbidden from the officers index', function () {
    $this->actingAs(officerAdminUser(UserRole::SUPER_ADMIN))->get('/admin/officers')->assertForbidden();
    $this->actingAs(officerAdminUser(UserRole::SRO_OFFICER))->get('/admin/officers')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/officers')->assertForbidden();
});

test('advisers do not appear in the officers list', function () {
    $head = officerAdminUser(UserRole::SSC_HEAD);
    $adviser = officerGrant(User::factory()->create(), UserRole::INSTITUTE_HEAD, Organization::where('code', 'ICS-ISC')->firstOrFail());
    $officer = officerGrant(User::factory()->create(), UserRole::SSC_OFFICER, Organization::where('code', 'SSC')->firstOrFail());

    $this->actingAs($head)->get('/admin/officers')
        ->assertInertia(fn ($page) => $page
            ->component('admin/officers/Index')
            ->has('officers.data', 1)
            ->where('officers.data.0.id', $officer->id));
});

test('institute head can assign an officer to the institute they head directly', function () {
    $head = officerAdminUser(UserRole::INSTITUTE_HEAD);
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $candidate = User::factory()->create();

    $this->actingAs($head)->post('/admin/officers/assign', [
        'user_id' => $candidate->id,
        'position' => 'President',
    ])->assertRedirect(route('admin.officers.index'));

    expect($candidate->fresh()->roleInOrganization($isc))->toBe(UserRole::ISC_OFFICER);

    $pivot = $candidate->fresh()->organizations()->where('organization_id', $isc->id)->first()->pivot;
    expect($pivot->position)->toBe('President');
});

test('sro head can assign an officer to their own sro only', function () {
    $head = officerAdminUser(UserRole::SRO_HEAD);
    $sro = Organization::where('code', 'BSCS-SRO')->firstOrFail();
    $candidate = User::factory()->create();

    $this->actingAs($head)->post('/admin/officers/assign', [
        'user_id' => $candidate->id,
        'position' => 'Secretary',
    ])->assertRedirect(route('admin.officers.index'));

    expect($candidate->fresh()->roleInOrganization($sro))->toBe(UserRole::SRO_OFFICER);

    $pivot = $candidate->fresh()->organizations()->where('organization_id', $sro->id)->first()->pivot;
    expect($pivot->position)->toBe('Secretary');
});

test('ssc head assigns officers only to the ssc organization', function () {
    $head = officerAdminUser(UserRole::SSC_HEAD);
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $candidate = User::factory()->create();

    $this->actingAs($head)->post('/admin/officers/assign', [
        'user_id' => $candidate->id,
        'position' => 'Treasurer',
    ])->assertRedirect(route('admin.officers.index'));

    expect($candidate->fresh()->roleInOrganization($ssc))->toBe(UserRole::SSC_OFFICER);
});

test('position is required when assigning an officer', function () {
    $head = officerAdminUser(UserRole::INSTITUTE_HEAD);
    $candidate = User::factory()->create();

    $this->actingAs($head)->post('/admin/officers/assign', [
        'user_id' => $candidate->id,
        'position' => '',
    ])->assertSessionHasErrors('position');
});

test('officer search returns matching users by name or student number', function () {
    $head = officerAdminUser(UserRole::SSC_HEAD);
    $match = User::factory()->create(['name' => 'Juan Dela Cruz', 'student_number' => '2024-0001', 'is_enrolled' => true]);
    $other = User::factory()->create(['name' => 'Maria Santos', 'student_number' => '2024-0002', 'is_enrolled' => true]);

    $this->actingAs($head)->getJson('/admin/officers/search?q=Juan')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $match->id);

    $this->actingAs($head)->getJson('/admin/officers/search?q=2024-0002')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $other->id);
});

test('officer search excludes existing officers and advisers', function () {
    $head = officerAdminUser(UserRole::SSC_HEAD);
    $officer = officerGrant(User::factory()->create(['name' => 'Ramon Roster', 'student_number' => '2024-0010']), UserRole::SSC_OFFICER, Organization::where('code', 'SSC')->firstOrFail());
    $adviser = officerGrant(User::factory()->create(['name' => 'Rhea Roster', 'student_number' => '2024-0011']), UserRole::INSTITUTE_HEAD, Organization::where('code', 'ICS-ISC')->firstOrFail());

    $this->actingAs($head)->getJson('/admin/officers/search?q=Roster')
        ->assertOk()
        ->assertJsonCount(0, 'users');
});

test('institute head search only returns students belonging to their institute', function () {
    $this->seed(\Database\Seeders\InstituteSeeder::class);
    $head = officerAdminUser(UserRole::INSTITUTE_HEAD);
    $icsInstitute = Institute::where('code', 'ICS')->firstOrFail();
    $iasInstitute = Institute::where('code', 'IAS')->firstOrFail();

    $inScope = User::factory()->create(['name' => 'Annie ICS', 'student_number' => '2024-0101', 'institute_id' => $icsInstitute->id, 'is_enrolled' => true]);
    $outOfScope = User::factory()->create(['name' => 'Benny IAS', 'student_number' => '2024-0102', 'institute_id' => $iasInstitute->id, 'is_enrolled' => true]);

    $this->actingAs($head)->getJson('/admin/officers/search?q=2024-010')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $inScope->id);
});

test('sro head search only returns students enrolled in their program', function () {
    $this->seed(\Database\Seeders\InstituteSeeder::class);
    $head = officerAdminUser(UserRole::SRO_HEAD);
    $bscsProgram = Program::where('code', 'BSCS')->firstOrFail();
    $abEnglishProgram = Program::where('code', 'AB English')->firstOrFail();

    $inScope = User::factory()->create(['name' => 'Carl SRO', 'student_number' => '2024-0201', 'program_id' => $bscsProgram->id, 'is_enrolled' => true]);
    $outOfScope = User::factory()->create(['name' => 'Dina Other', 'student_number' => '2024-0202', 'program_id' => $abEnglishProgram->id, 'is_enrolled' => true]);

    $this->actingAs($head)->getJson('/admin/officers/search?q=2024-020')
        ->assertOk()
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.id', $inScope->id);
});

test('ssc head search returns students across all institutes', function () {
    $this->seed(\Database\Seeders\InstituteSeeder::class);
    $head = officerAdminUser(UserRole::SSC_HEAD);
    $icsInstitute = Institute::where('code', 'ICS')->firstOrFail();
    $iasInstitute = Institute::where('code', 'IAS')->firstOrFail();

    $icsStudent = User::factory()->create(['name' => 'Erin ICS', 'student_number' => '2024-0301', 'institute_id' => $icsInstitute->id, 'is_enrolled' => true]);
    $iasStudent = User::factory()->create(['name' => 'Finn IAS', 'student_number' => '2024-0302', 'institute_id' => $iasInstitute->id, 'is_enrolled' => true]);

    $this->actingAs($head)->getJson('/admin/officers/search?q=2024-030')
        ->assertOk()
        ->assertJsonCount(2, 'users');
});

test('heads can revoke an officer assignment within their direct org', function () {
    $head = officerAdminUser(UserRole::INSTITUTE_HEAD);
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $officer = officerGrant(User::factory()->create(), UserRole::ISC_OFFICER, $isc);

    $this->actingAs($head)->delete('/admin/officers', [
        'user_id' => $officer->id,
        'organization_id' => $isc->id,
    ])->assertSessionHasNoErrors();

    expect($officer->fresh()->roleInOrganization($isc))->toBeNull();
});
