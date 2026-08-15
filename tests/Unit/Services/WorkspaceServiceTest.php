<?php

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\WorkspaceService;

beforeEach(function () {
    $this->seed([
        \Database\Seeders\OrganizationSeeder::class,
    ]);
    $this->service = app(WorkspaceService::class);
});

test('returns only the student workspace for an officer user (students-only PWA)', function () {
    $user = User::factory()->create();
    $ssc = Organization::where('code', 'SSC')->first();
    $user->organizations()->attach($ssc->id, [
        'role' => UserRole::SSC_OFFICER->value,
        'assigned_at' => now(),
    ]);

    $workspaces = $this->service->getAvailableWorkspaces($user);

    expect($workspaces)->toHaveCount(1);
    expect($workspaces[0]['id'])->toBe('student');
    expect($workspaces[0]['organization_id'])->toBeNull();
});

test('returns the student workspace for a student user', function () {
    $user = User::factory()->create();

    $workspaces = $this->service->getAvailableWorkspaces($user);

    expect($workspaces)->not->toBeEmpty();
    expect($workspaces[0]['id'])->toBe('student');
});
