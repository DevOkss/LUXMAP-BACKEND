<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\AccessScopeService;

class SomEventPolicy
{
    public function __construct(
        private AccessScopeService $accessScopeService
    ) {}

    public function view(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) return true;

        $ids = $user->hasOfficerRole()
            ? $this->accessScopeService->scopeOrganizationIds($user)
            : $this->accessScopeService->viewableOrganizationIds($user);

        return in_array($event->organization_id, $ids, true);
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) return true;
        return $this->accessScopeService->isWithinScope($user, $event->organization);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) return true;
        return $this->accessScopeService->isWithinScope($user, $event->organization);
    }
}
