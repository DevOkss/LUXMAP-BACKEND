<?php

use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Models\User;

function dashboardAdmin(): User
{
    $user = User::factory()->create();

    $user->organizations()->attach(Organization::factory()->create()->id, [
        'role' => UserRole::SUPER_ADMIN->value,
        'position' => 'System Administrator',
        'assigned_at' => now(),
    ]);

    return $user;
}

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('admin portal users can visit the dashboard', function () {
    $user = dashboardAdmin();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('non-admin users are forbidden from the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertForbidden();
});

test('dashboard exposes academic terms with the active term selected by default', function () {
    $past = AcademicTerm::factory()->create([
        'academic_year' => '2024-2025',
        'is_active' => false,
    ]);
    $current = AcademicTerm::factory()->create([
        'academic_year' => '2025-2026',
        'is_active' => true,
    ]);

    $admin = dashboardAdmin();

    $this->actingAs($admin)->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard/Index')
            ->has('terms', 2)
            ->where('terms.0.id', $current->id)
            ->where('selected_term', $current->id)
            ->where('current_term.id', $current->id)
            ->where('current_term.name', $current->displayName())
            ->has('stats')
            ->has('income_chart')
            ->has('income_breakdown')
            ->has('org_breakdown')
            ->has('scope_orgs'));
});

test('income is scoped to the selected term', function () {
    $current = AcademicTerm::factory()->create(['is_active' => true]);
    $past = AcademicTerm::factory()->create([
        'academic_year' => '2024-2025',
        'is_active' => false,
    ]);

    $admin = dashboardAdmin();
    $student = User::factory()->create();
    $organizationId = $admin->organizations()->first()->id;

    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $organizationId,
        'academic_term_id' => $current->id,
        'amount' => 500,
    ]);
    Payment::factory()->create([
        'user_id' => $student->id,
        'organization_id' => $organizationId,
        'academic_term_id' => $past->id,
        'amount' => 250,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('stats.total_income', 500));

    $this->actingAs($admin)->get('/dashboard?academic_term_id='.$past->id)
        ->assertInertia(fn ($page) => $page
            ->where('selected_term', $past->id)
            ->where('stats.total_income', 250));
});

test('income breakdown reports fees and penalties separately', function () {
    $term = AcademicTerm::factory()->create(['is_active' => true]);

    $admin = dashboardAdmin();
    $student = User::factory()->create();
    $organizationId = $admin->organizations()->first()->id;

    Payment::factory()->fee()->create([
        'user_id' => $student->id,
        'organization_id' => $organizationId,
        'academic_term_id' => $term->id,
        'amount' => 1000,
    ]);
    Payment::factory()->penalty()->create([
        'user_id' => $student->id,
        'organization_id' => $organizationId,
        'academic_term_id' => $term->id,
        'amount' => 200,
    ]);

    $this->actingAs($admin)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stats.total_income', 1200)
            ->where('income_breakdown.fees', 1000)
            ->where('income_breakdown.penalties', 200));
});

test('org breakdown counts students and officers per organization', function () {
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $org = Organization::factory()->create(['type' => 'ssc']);

    $head = User::factory()->create();
    $head->organizations()->attach($org->id, [
        'role' => UserRole::SSC_HEAD->value,
        'position' => 'SSC Head',
        'assigned_at' => now(),
    ]);

    $student = User::factory()->create();
    StudentEnrollment::factory()->create([
        'user_id' => $student->id,
        'academic_term_id' => $term->id,
        'is_enrolled' => true,
    ]);

    $officer = User::factory()->create();
    $officer->organizations()->attach($org->id, [
        'role' => UserRole::SSC_OFFICER->value,
        'position' => 'SSC Staff',
        'assigned_at' => now(),
    ]);

    $this->actingAs($head)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('org_breakdown', 1)
            ->where('org_breakdown.0.organization.id', $org->id)
            ->where('org_breakdown.0.students', 1)
            ->where('org_breakdown.0.officers', 1)
            ->where('stats.total_students', 1)
            ->where('stats.total_officers', 1));
});

test('officers see the unified dashboard scoped to their organization', function () {
    $term = AcademicTerm::factory()->create(['is_active' => true]);
    $org = Organization::factory()->create(['type' => 'ssc']);

    $officer = User::factory()->create();
    $officer->organizations()->attach($org->id, [
        'role' => UserRole::SSC_OFFICER->value,
        'position' => 'SSC Staff',
        'assigned_at' => now(),
    ]);

    $this->actingAs($officer)->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard/Index')
            ->has('stats')
            ->has('income_chart')
            ->has('org_breakdown', 1)
            ->where('scope_orgs.0.id', $org->id)
            ->where('org_breakdown.0.organization.id', $org->id)
            ->where('stats.total_income', 0));
});

test('dashboard renders without any academic term', function () {
    $admin = dashboardAdmin();

    $this->actingAs($admin)->get('/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('selected_term', null)
            ->where('current_term', null)
            ->has('income_chart'));
});