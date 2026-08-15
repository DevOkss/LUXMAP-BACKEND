<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AccessScopeService;

class UserPolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    /**
     * Super admins manage all users; heads and officers may browse users
     * within their org scope (used by the officer assignment pages).
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasOfficerRole();
    }

    public function view(User $user, User $subject): bool
    {
        return $user->isSuperAdmin() || $user->hasOfficerRole();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function activate(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    public function deactivate(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Heads may assign officer roles within their own org scope.
     */
    public function assignRole(User $user, User $subject, ?\App\Models\Organization $organization = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $role = $user->hasRole(UserRole::headRoles());

        if (! $role) {
            return false;
        }

        return $organization === null || $this->accessScopeService->isWithinScope($user, $organization);
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isSuperAdmin();
    }
}
