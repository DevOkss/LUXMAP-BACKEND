<?php

namespace App\Repositories;

use App\Models\Fee;
use Illuminate\Database\Eloquent\Collection;

class FeeRepository
{
    public function __construct(
        private Fee $model
    ) {}

    public function all(array $filters = []): Collection
    {
        $query = $this->model->with('organization');

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        if (!empty($filters['organization_ids'])) {
            $query->whereIn('organization_id', $filters['organization_ids']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function find(int $id): ?Fee
    {
        return $this->model->with('organization')->find($id);
    }

    public function create(array $data): Fee
    {
        return $this->model->create($data);
    }

    public function update(Fee $fee, array $data): bool
    {
        return $fee->update($data);
    }

    public function delete(Fee $fee): bool
    {
        return $fee->delete();
    }

    public function findByOrganization(int $orgId): Collection
    {
        return $this->model->where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
