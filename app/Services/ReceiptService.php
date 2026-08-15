<?php

namespace App\Services;

use App\Models\Receipt;
use App\Repositories\ReceiptRepository;
use Illuminate\Database\Eloquent\Collection;

class ReceiptService
{
    public function __construct(
        private ReceiptRepository $repository
    ) {}

    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function listForUser(int $userId): Collection
    {
        return $this->repository->allForUser($userId);
    }

    public function show(int $id): ?Receipt
    {
        return $this->repository->find($id);
    }

    public function showForUser(int $id, int $userId): ?Receipt
    {
        $receipt = $this->repository->find($id);

        if (!$receipt || !$receipt->payment || $receipt->payment->user_id !== $userId) {
            return null;
        }

        return $receipt;
    }

    public function findByPayment(int $paymentId): ?Receipt
    {
        return $this->repository->findByPayment($paymentId);
    }
}
