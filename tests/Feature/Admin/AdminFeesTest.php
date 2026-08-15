<?php

use App\Enums\UserRole;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\InstituteSeeder::class,
        \Database\Seeders\OrganizationSeeder::class,
    ]);
});

function adminFeesUser(string $orgCode, UserRole $role): User
{
    $user = User::factory()->create();
    $user->organizations()->attach(Organization::where('code', $orgCode)->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('head can create a fee draft for their organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);

    $response = $this->actingAs($head)->post('/admin/fees', [
        'organization_id' => $ssc->id,
        'name' => 'Org Fee',
        'amount' => 100,
        'term' => '1st Semester',
        'required_years' => ['all'],
        'due_date' => now()->addMonth()->format('Y-m-d'),
    ]);

    $response->assertRedirect();
    $fee = Fee::where('name', 'Org Fee')->first();
    expect($fee)->not->toBeNull();
    expect($fee->status)->toBe('draft');
});

test('head can post a fee and students become eligible', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);
    User::factory()->create(['is_enrolled' => true]);
    User::factory()->create(['is_enrolled' => false]);

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);

    $this->actingAs($head)->post("/admin/fees/{$fee->id}/publish")->assertRedirect();

    expect($fee->fresh()->status)->toBe('posted');
    expect(app(\App\Services\FeeService::class)->eligibleCount($fee->fresh()))->toBe(1);
});

test('head creation stores the fee for the selected academic term and derives the term label', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);
    $term = \App\Models\AcademicTerm::factory()->create([
        'academic_year' => '2026-2027',
        'semester' => '2nd',
        'is_active' => true,
    ]);

    $this->actingAs($head)->get('/admin/fees/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fees/Create')
            ->has('academic_terms')
            ->where('academic_terms.0.id', $term->id)
            ->where('academic_terms.0.is_active', true));

    $this->actingAs($head)->post('/admin/fees', [
        'organization_id' => $ssc->id,
        'name' => 'Term Fee',
        'amount' => 100,
        'academic_term_id' => $term->id,
        'required_years' => ['all'],
        'due_date' => now()->addMonth()->format('Y-m-d'),
    ])->assertRedirect();

    $fee = Fee::where('name', 'Term Fee')->first();
    expect($fee)->not->toBeNull();
    expect($fee->academic_term_id)->toBe($term->id);
    expect($fee->term)->toBe($term->displayName());
});

test('head cannot create a fee for another organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);

    $this->actingAs($head)->post('/admin/fees', [
        'organization_id' => $isc->id,
        'name' => 'Cross',
        'amount' => 100,
    ])->assertForbidden();
});

test('super admin cannot access fees (module removed)', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $superAdmin = adminFeesUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->get('/admin/fees')->assertForbidden();
});

test('officer can view fees and penalty amounts but cannot manage them', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $officer = adminFeesUser('SSC', UserRole::SSC_OFFICER);
    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);

    $this->actingAs($officer)->get('/admin/fees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fees/Index')
            ->where('can_manage_fees', false)
            ->where('can_manage_penalties', false));

    $this->actingAs($officer)->post('/admin/fees', [
        'organization_id' => $ssc->id,
        'name' => 'Officer Fee',
        'amount' => 100,
    ])->assertForbidden();

    $this->actingAs($officer)->post("/admin/fees/{$fee->id}/publish")->assertForbidden();

    $this->actingAs($officer)->post('/admin/fees/penalty', [
        'organization_id' => $ssc->id,
        'amount' => 50,
    ])->assertForbidden();
});

test('head can unpost a fee and delete it without deleting payments', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);
    $student = User::factory()->create(['is_enrolled' => true]);

    $fee = Fee::factory()->create(['organization_id' => $ssc->id, 'status' => 'draft']);
    $this->actingAs($head)->post("/admin/fees/{$fee->id}/publish")->assertRedirect();

    Payment::create([
        'user_id' => $student->id,
        'organization_id' => $ssc->id,
        'fee_id' => $fee->id,
        'amount' => $fee->amount,
        'payment_method' => 'cash',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $this->actingAs($head)->post("/admin/fees/{$fee->id}/unpublish")->assertRedirect();
    expect($fee->fresh()->status)->toBe('draft');

    $this->actingAs($head)->delete("/admin/fees/{$fee->id}")->assertRedirect();
    expect(Fee::find($fee->id))->toBeNull();
    // Payment records are preserved (fee_id is nulled via nullOnDelete).
    expect(Payment::where('user_id', $student->id)->count())->toBe(1);
});

test('fees index exposes term, required years and assigned count', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);
    $fee = Fee::factory()->create([
        'organization_id' => $ssc->id,
        'term' => '1st Semester',
        'required_years' => ['1', '2'],
        'status' => 'draft',
    ]);

    $this->actingAs($head)->get('/admin/fees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fees/Index')
            ->where('can_manage_fees', true)
            ->has('fees', 1)
            ->where('fees.0.term', '1st Semester')
            ->where('fees.0.required_years', ['1', '2']));
});

test('head can set the penalty amount for their own organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);

    $this->actingAs($head)->post('/admin/fees/penalty', [
        'organization_id' => $ssc->id,
        'amount' => 100,
    ])->assertRedirect();

    $record = \App\Models\PenaltyFee::where('organization_id', $ssc->id)->first();
    expect($record)->not->toBeNull();
    expect((float) $record->amount)->toBe(100.0);
    expect($record->set_by)->toBe($head->id);
});

test('head cannot set penalty amount for another organization', function () {
    $isc = Organization::where('code', 'ICS-ISC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);

    $this->actingAs($head)->post('/admin/fees/penalty', [
        'organization_id' => $isc->id,
        'amount' => 50,
    ])->assertForbidden();

    expect(\App\Models\PenaltyFee::where('organization_id', $isc->id)->exists())->toBeFalse();
});

test('super admin cannot set penalty amount (view-only)', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $superAdmin = adminFeesUser('SSC', UserRole::SUPER_ADMIN);

    $this->actingAs($superAdmin)->post('/admin/fees/penalty', [
        'organization_id' => $ssc->id,
        'amount' => 50,
    ])->assertForbidden();
});

test('fees index exposes the current penalty amount per organization', function () {
    $ssc = Organization::where('code', 'SSC')->firstOrFail();
    $head = adminFeesUser('SSC', UserRole::SSC_HEAD);
    \App\Models\PenaltyFee::create([
        'organization_id' => $ssc->id,
        'amount' => 75,
        'effective_at' => now(),
        'set_by' => $head->id,
    ]);

    $this->actingAs($head)->get('/admin/fees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fees/Index')
            ->where('can_manage_penalties', true)
            ->has('penalties')
            ->where('penalties.0.current_amount', 75));
});
