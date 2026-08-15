<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use Illuminate\Database\Eloquent\Collection;

class AuditLogService
{
    public function __construct(
        private AuditLogRepository $repository
    ) {}

    public function list(array $filters = []): Collection
    {
        return $this->repository->all($filters);
    }

    public function show(int $id): ?AuditLog
    {
        return $this->repository->find($id);
    }

    public function log(
        ?User $user,
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return $this->repository->create([
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
