<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionRegistry;

class WorkspaceService
{
    public function __construct(
        private PermissionRegistry $permissionRegistry
    ) {}

    public function getAvailableWorkspaces(User $user): array
    {
        // The PWA is students-only; officers manage their organization from the
        // Laravel admin portal instead of a PWA workspace.
        return [
            [
                'id' => 'student',
                'name' => 'Student Workspace',
                'code' => 'STUDENT',
                'type' => 'student',
                'role' => 'student',
                'organization_id' => null,
            ],
        ];
    }

    public function resolvePermissions(User $user, ?Organization $organization = null): array
    {
        return $this->permissionRegistry->permissionsFor($user, $organization);
    }
}
