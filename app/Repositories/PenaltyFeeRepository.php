<?php

namespace App\Repositories;

use App\Models\PenaltyFee;
use Illuminate\Database\Eloquent\Collection;

class PenaltyFeeRepository
{
    public function __construct(
        private PenaltyFee $model
    ) {}

    public function create(array $data): PenaltyFee
    {
        return $this->model->create($data);
    }

    public function history(?int $organizationId): Collection
    {
        $query = $this->model->with('setBy');

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        } else {
            $query->whereNull('organization_id');
        }

        return $query->orderByDesc('effective_at')->orderByDesc('id')->get();
    }
}