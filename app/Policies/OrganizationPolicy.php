<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\AccessScopeService;

class OrganizationPolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasOfficerRole() && $this->accessScopeService->isWithinScope($user, $organization));
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }
}
