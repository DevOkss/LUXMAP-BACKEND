<?php

use App\Enums\UserRole;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function instituteGrant(User $user, UserRole $role): User
{
    $code = match ($role) {
        UserRole::SSC_HEAD => 'SSC',
        UserRole::INSTITUTE_HEAD => 'ICS-ISC',
        default => 'SSC',
    };

    $user->organizations()->attach(Organization::where('code', $code)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('super admins can access the institutes index', function () {
    Institute::factory()->count(3)->create();

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->get('/admin/institutes')->assertOk();
});

test('heads and students are forbidden from the institutes index', function () {
    $this->actingAs(instituteGrant(User::factory()->create(), UserRole::INSTITUTE_HEAD))
        ->get('/admin/institutes')->assertForbidden();

    $this->actingAs(User::factory()->create())->get('/admin/institutes')->assertForbidden();
});

test('super admin can create an institute with a logo', function () {
    Storage::fake('public');

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->post('/admin/institutes', [
        'code' => 'ICS',
        'name' => 'Institute of Computer Studies',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        'is_active' => true,
    ])->assertRedirect(route('admin.institutes.index'));

    $institute = Institute::where('code', 'ICS')->firstOrFail();
    expect($institute->name)->toBe('Institute of Computer Studies');
    expect($institute->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($institute->logo_path);
});

test('institute code must be unique', function () {
    Institute::factory()->create(['code' => 'ICS']);

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->post('/admin/institutes', [
        'code' => 'ICS',
        'name' => 'Duplicate',
        'is_active' => true,
    ])->assertSessionHasErrors('code');
});

test('super admin can add a program under an institute', function () {
    $institute = Institute::factory()->create();

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->post("/admin/institutes/{$institute->id}/programs", [
        'code' => 'BSCS',
        'name' => 'Bachelor of Science in Computer Science',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect($institute->programs()->where('code', 'BSCS')->exists())->toBeTrue();
});

test('program code must be unique within the institute', function () {
    $institute = Institute::factory()->create();
    Program::factory()->create(['institute_id' => $institute->id, 'code' => 'BSCS']);

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->post("/admin/institutes/{$institute->id}/programs", [
        'code' => 'BSCS',
        'name' => 'Duplicate program',
        'is_active' => true,
    ])->assertSessionHasErrors('code');
});

test('super admin can update an institute', function () {
    $institute = Institute::factory()->create();

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->put("/admin/institutes/{$institute->id}", [
        'code' => $institute->code,
        'name' => 'Renamed Institute',
        'is_active' => true,
    ])->assertRedirect(route('admin.institutes.show', $institute));

    expect($institute->fresh()->name)->toBe('Renamed Institute');
});

test('super admin can delete an institute and its programs', function () {
    Storage::fake('public');

    $institute = Institute::factory()->create();
    $institute->programs()->create(['code' => 'BSCS', 'name' => 'Computer Science']);

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->delete("/admin/institutes/{$institute->id}")
        ->assertRedirect(route('admin.institutes.index'));

    expect(Institute::find($institute->id))->toBeNull();
    expect(Program::where('institute_id', $institute->id)->exists())->toBeFalse();
});

test('super admin can delete a program', function () {
    $institute = Institute::factory()->create();
    $program = Program::factory()->create(['institute_id' => $institute->id]);

    $admin = instituteGrant(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->delete("/admin/programs/{$program->id}")
        ->assertSessionHasNoErrors();

    expect(Program::find($program->id))->toBeNull();
});
