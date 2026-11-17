<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\InstituteSeeder;
use Database\Seeders\OrganizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        InstituteSeeder::class,
        OrganizationSeeder::class,
    ]);
});

function academicTermAdminUser(string $orgCode, UserRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('super admin can view the academic terms page', function () {
    AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'semester' => '1st', 'is_active' => true]);

    $superAdmin = academicTermAdminUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->get('/admin/academic-terms')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/academic-terms/Index')
            ->has('terms', 1));
});

test('super admin creates a term that becomes active when none is active', function () {
    $superAdmin = academicTermAdminUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->post('/admin/academic-terms', [
        'academic_year' => '2027-2028',
        'semester' => '1st',
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfYear()->format('Y-m-d'),
    ])->assertRedirect();

    $term = AcademicTerm::where('academic_year', '2027-2028')->first();
    expect($term)->not->toBeNull();
    expect($term->is_active)->toBeTrue();
});

test('super admin can activate a term, deactivating others', function () {
    $active = AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'semester' => '1st', 'is_active' => true]);
    $nextTerm = AcademicTerm::factory()->create(['academic_year' => '2027-2028', 'semester' => '1st', 'is_active' => false]);

    $superAdmin = academicTermAdminUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->post("/admin/academic-terms/{$nextTerm->id}/activate")
        ->assertRedirect();

    expect($active->fresh()->is_active)->toBeFalse();
    expect($nextTerm->fresh()->is_active)->toBeTrue();
});

test('super admin cannot create a duplicate term', function () {
    AcademicTerm::factory()->create(['academic_year' => '2026-2027', 'semester' => '1st', 'is_active' => false]);
    $superAdmin = academicTermAdminUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->post('/admin/academic-terms', [
        'academic_year' => '2026-2027',
        'semester' => '1st',
    ])->assertSessionHas('error');

    expect(AcademicTerm::count())->toBe(1);
});

test('heads cannot access academic terms', function () {
    $head = academicTermAdminUser('SSC', UserRole::SSC_HEAD);

    $this->actingAs($head)->get('/admin/academic-terms')->assertForbidden();
    $this->actingAs($head)->post('/admin/academic-terms', [
        'academic_year' => '2026-2027',
        'semester' => '1st',
    ])->assertForbidden();
});
