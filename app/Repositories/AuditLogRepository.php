<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Collection;

class AuditLogRepository
{
    public function __construct(
        private AuditLog $model
    ) {}

    public function all(array $filters = []): Collection
    {
        $query = $this->model->with('user');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')->take(500)->get();
    }

    public function find(int $id): ?AuditLog
    {
        return $this->model->with('user')->find($id);
    }

    public function create(array $data): AuditLog
    {
        return $this->model->create($data);
    }
}
