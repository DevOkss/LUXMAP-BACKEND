<?php

use App\Enums\UserRole;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Services\AccessScopeService;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
    $this->service = app(AccessScopeService::class);
});

function scopeUser(string $code, UserRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $code)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('super admin scope includes every organization', function () {
    $user = scopeUser('SSC', UserRole::SUPER_ADMIN);

    expect($this->service->scopeOrganizationIds($user))
        ->toHaveCount(Organization::count())
        ->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
});

test('ssc head scope includes the SSC only', function () {
    $user = scopeUser('SSC', UserRole::SSC_HEAD);

    $ids = $this->service->scopeOrganizationIds($user);

    expect($ids)->toContain(Organization::where('code', 'SSC')->first()->id);
    expect($ids)->not->toContain(Organization::where('code', 'ICS-ISC')->first()->id);
    expect($ids)->not->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
});

test('institute head scope is limited to their institute only', function () {
    $user = scopeUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    $ids = $this->service->scopeOrganizationIds($user);

    expect($ids)->toContain(Organization::where('code', 'ICS-ISC')->first()->id);
    expect($ids)->not->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
    expect($ids)->not->toContain(Organization::where('code', 'IAS-ISC')->first()->id);
    expect($ids)->not->toContain(Organization::where('code', 'SSC')->first()->id);
});

test('sro head scope is limited to their own organization', function () {
    $user = scopeUser('BSCS-SRO', UserRole::SRO_HEAD);

    $ids = $this->service->scopeOrganizationIds($user);

    expect($ids)->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
    expect($ids)->toHaveCount(1);
});

test('sro officer scope is limited to their own organization', function () {
    $user = scopeUser('BSCS-SRO', UserRole::SRO_OFFICER);

    $ids = $this->service->scopeOrganizationIds($user);

    expect($ids)->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
    expect($ids)->toHaveCount(1);
});

test('is within scope rejects organizations outside the user scope', function () {
    $user = scopeUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    expect($this->service->isWithinScope($user, Organization::where('code', 'ICS-ISC')->first()))->toBeTrue();
    expect($this->service->isWithinScope($user, Organization::where('code', 'BSCS-SRO')->first()))->toBeFalse();
    expect($this->service->isWithinScope($user, Organization::where('code', 'IAS-ISC')->first()))->toBeFalse();
});

test('institute head can manage officers in the institute they head directly', function () {
    $head = scopeUser('ICS-ISC', UserRole::INSTITUTE_HEAD);

    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'ICS-ISC')->first()))->toBeTrue();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'BSCS-SRO')->first()))->toBeFalse();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'AB English-SRO')->first()))->toBeFalse();
});

test('ssc head can manage officers only in the SSC', function () {
    $head = scopeUser('SSC', UserRole::SSC_HEAD);

    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'SSC')->first()))->toBeTrue();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'AB English-SRO')->first()))->toBeFalse();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'BSBA MM-SRO')->first()))->toBeFalse();
});

test('sro head can manage officers only in their own sro', function () {
    $head = scopeUser('BSCS-SRO', UserRole::SRO_HEAD);

    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'BSCS-SRO')->first()))->toBeTrue();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'BSBA MM-SRO')->first()))->toBeFalse();
    expect($this->service->canManageOfficersIn($head, Organization::where('code', 'SSC')->first()))->toBeFalse();
});

test('officers cannot manage other officers', function () {
    $officer = scopeUser('BSCS-SRO', UserRole::SRO_OFFICER);

    expect($this->service->canManageOfficersIn($officer, Organization::where('code', 'BSCS-SRO')->first()))->toBeFalse();
});

test('pure student has no manageable organizations', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    $student = User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);

    expect($this->service->scopeOrganizationIds($student))->toBeEmpty();
    expect($this->service->isWithinScope($student, Organization::where('code', 'ICS-ISC')->first()))->toBeFalse();
    expect($this->service->isWithinScope($student, Organization::where('code', 'BSCS-SRO')->first()))->toBeFalse();
    expect($this->service->isWithinScope($student, Organization::where('code', 'SSC')->first()))->toBeFalse();
});

test('pure student can view SSC, their ISC, and their SRO', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    $student = User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);

    $ids = $this->service->viewableOrganizationIds($student);

    expect($ids)->toContain(Organization::where('code', 'SSC')->first()->id);
    expect($ids)->toContain(Organization::where('code', 'ICS-ISC')->first()->id);
    expect($ids)->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
});

test('student who is also an officer views the union of both scopes', function () {
    $ics = Institute::where('code', 'ICS')->firstOrFail();
    $bscs = Program::where('code', 'BSCS')->firstOrFail();

    $student = User::factory()->create([
        'institute_id' => $ics->id,
        'program_id' => $bscs->id,
    ]);

    $student->organizations()->attach(Organization::where('code', 'ICS-ISC')->firstOrFail()->id, [
        'role' => UserRole::ISC_OFFICER->value,
        'position' => UserRole::ISC_OFFICER->value,
        'assigned_at' => now(),
    ]);

    $ids = $this->service->viewableOrganizationIds($student);

    // Officer scope (ISC) plus student scope (SSC, ISC, SRO)
    expect($ids)->toContain(Organization::where('code', 'SSC')->first()->id);
    expect($ids)->toContain(Organization::where('code', 'ICS-ISC')->first()->id);
    expect($ids)->toContain(Organization::where('code', 'BSCS-SRO')->first()->id);
    expect($ids)->toHaveCount(3);
});
