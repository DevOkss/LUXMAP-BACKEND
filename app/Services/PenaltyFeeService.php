<?php

namespace App\Services;

use App\Models\PenaltyFee;
use App\Repositories\PenaltyFeeRepository;

class PenaltyFeeService
{
    public function __construct(
        private PenaltyFeeRepository $repository
    ) {}

    public function current(?int $organizationId): ?PenaltyFee
    {
        if ($organizationId) {
            $row = PenaltyFee::where('organization_id', $organizationId)
                ->orderByDesc('effective_at')
                ->orderByDesc('id')
                ->first();

            if ($row) {
                return $row;
            }
        }

        return PenaltyFee::whereNull('organization_id')
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function currentAmount(?int $organizationId): int|float
    {
        return PenaltyFee::currentAmountFor($organizationId);
    }

    public function history(?int $organizationId)
    {
        return $this->repository->history($organizationId);
    }

    public function set(?int $organizationId, int|float $amount, ?int $setBy = null): PenaltyFee
    {
        return $this->repository->create([
            'organization_id' => $organizationId,
            'amount' => $amount,
            'effective_at' => now(),
            'set_by' => $setBy,
        ]);
    }
}