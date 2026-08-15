<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', 'role:super_admin,ssc_head'])->get('/role-test', fn () => response('ok'));

    $this->user = User::factory()->create();
});

function roleTestGrant(User $user, UserRole $role, ?Organization $org = null): User
{
    $user->organizations()->attach(($org ?? Organization::factory()->create())->id, [
        'role' => $role->value,
        'position' => $role->value,
        'assigned_at' => now(),
    ]);

    return $user;
}

test('guests are redirected away from role-protected routes', function () {
    $this->get('/role-test')->assertRedirect(route('login'));
});

test('users without a matching role are forbidden', function () {
    $this->actingAs($this->user)->get('/role-test')->assertForbidden();
});

test('users with any listed role pass the middleware', function () {
    roleTestGrant($this->user, UserRole::SSC_HEAD);

    $this->actingAs($this->user)->get('/role-test')->assertOk();
});

test('super admins pass the middleware', function () {
    roleTestGrant($this->user, UserRole::SUPER_ADMIN);

    $this->actingAs($this->user)->get('/role-test')->assertOk();
});

test('student role does not satisfy admin role middleware', function () {
    roleTestGrant($this->user, UserRole::STUDENT);

    $this->actingAs($this->user)->get('/role-test')->assertForbidden();
});

test('officer roles pass the role middleware when listed', function () {
    Route::middleware(['auth', 'role:isc_officer,sro_officer'])->get('/officer-role-test', fn () => response('ok'));

    roleTestGrant($this->user, UserRole::ISC_OFFICER);

    $this->actingAs($this->user)->get('/officer-role-test')->assertOk();
});
