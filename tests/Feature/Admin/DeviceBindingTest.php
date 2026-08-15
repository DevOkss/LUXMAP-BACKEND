<?php

use App\Enums\UserRole;
use App\Models\DeviceBinding;
use App\Models\DeviceUnbindAudit;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
        \Database\Seeders\InstituteSeeder::class,
    ]);
});

function grantBindingUser(User $user, UserRole $role): User
{
    $user->organizations()->attach(Organization::where('code', 'SSC')->firstOrFail()->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

function makeBinding(?User $boundUser = null): DeviceBinding
{
    $boundUser ??= User::factory()->create(['is_enrolled' => true]);

    return DeviceBinding::create([
        'user_id' => $boundUser->id,
        'device_fingerprint' => 'fp-' . str()->random(40),
        'device_meta' => ['platform' => 'Android'],
        'bound_at' => now(),
    ]);
}

test('super admin can view device bindings', function () {
    makeBinding();
    $admin = grantBindingUser(User::factory()->create(), UserRole::SUPER_ADMIN);

    $this->actingAs($admin)->get('/admin/device-bindings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/device-bindings/Index'))
        ->assertInertia(fn ($page) => $page->where('bindings.total', fn ($total) => $total >= 1));
});

test('heads can view device bindings scoped to their organizations', function () {
    $head = grantBindingUser(User::factory()->create(), UserRole::SSC_HEAD);
    $student = User::factory()->create(['is_enrolled' => true]);
    makeBinding($student);

    $this->actingAs($head)->get('/admin/device-bindings')->assertOk();
});

test('officers and students are forbidden from device bindings', function () {
    $officer = grantBindingUser(User::factory()->create(), UserRole::SSC_OFFICER);
    $student = User::factory()->create();

    $this->actingAs($officer)->get('/admin/device-bindings')->assertForbidden();
    $this->actingAs($student)->get('/admin/device-bindings')->assertForbidden();
});

test('bindings can be searched by student number', function () {
    $admin = grantBindingUser(User::factory()->create(), UserRole::SUPER_ADMIN);
    $student = User::factory()->create(['is_enrolled' => true, 'student_number' => '21-5555']);
    makeBinding($student);

    $this->actingAs($admin)->get('/admin/device-bindings?q=21-5555')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('bindings.total', 1));
});

test('unbind removes the binding and records an audit trail', function () {
    $admin = grantBindingUser(User::factory()->create(), UserRole::SUPER_ADMIN);
    $binding = makeBinding();

    $this->actingAs($admin)
        ->delete("/admin/device-bindings/{$binding->id}", ['reason' => 'Device reported lost'])
        ->assertRedirect(route('admin.device-bindings.index'));

    expect(DeviceBinding::find($binding->id))->toBeNull();
    expect(DeviceUnbindAudit::where('user_id', $binding->user_id)->exists())->toBeTrue()
        ->and(DeviceUnbindAudit::where('user_id', $binding->user_id)->first()->reason)->toBe('Device reported lost');
});

test('a reason is required to unbind', function () {
    $admin = grantBindingUser(User::factory()->create(), UserRole::SUPER_ADMIN);
    $binding = makeBinding();

    $this->actingAs($admin)
        ->delete("/admin/device-bindings/{$binding->id}", ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect(DeviceBinding::find($binding->id))->not->toBeNull();
});

test('unbind keeps the face profile so the student can re-bind without re-enrolling', function () {
    $admin = grantBindingUser(User::factory()->create(), UserRole::SUPER_ADMIN);
    $user = User::factory()->create(['is_enrolled' => true]);
    \App\Models\FaceEnrollment::create(['user_id' => $user->id, 'descriptors' => [array_fill(0, 128, 0.1)]]);
    $binding = DeviceBinding::create([
        'user_id' => $user->id,
        'device_fingerprint' => 'fp-' . str()->random(40),
        'bound_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete("/admin/device-bindings/{$binding->id}", ['reason' => 'Manual reset']);

    expect($user->fresh()->faceEnrollment)->not->toBeNull();
});