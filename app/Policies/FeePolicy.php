<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Fee;
use App\Models\User;
use App\Services\AccessScopeService;

class FeePolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Fee $fee): bool
    {
        return true;
    }

    /**
     * Only heads create fees (SSC / institute / SRO). Super admins and
     * officers do not control fees.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::headRoles());
    }

    public function update(User $user, Fee $fee): bool
    {
        return $user->hasRole(UserRole::headRoles())
            && $this->accessScopeService->isWithinScope($user, $fee->organization);
    }

    public function delete(User $user, Fee $fee): bool
    {
        return $this->update($user, $fee);
    }
}
