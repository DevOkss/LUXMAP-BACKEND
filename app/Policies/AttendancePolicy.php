<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AccessScopeService;

class AttendancePolicy
{
    public function __construct(private AccessScopeService $accessScopeService) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($attendance->user_id === $user->id) {
            return true;
        }

        return $this->manageable($user, $attendance);
    }

    private function manageable(User $user, Attendance $attendance): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasStaffRole()
                && $attendance->organization_id !== null
                && $this->accessScopeService->isWithinScope($user, $attendance->organization));
    }
}
