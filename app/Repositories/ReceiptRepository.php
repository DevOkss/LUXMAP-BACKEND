<?php

namespace App\Repositories;

use App\Models\Receipt;
use Illuminate\Database\Eloquent\Collection;

class ReceiptRepository
{
    public function __construct(
        private Receipt $model
    ) {}

    public function all(): Collection
    {
        return $this->model->with(['payment.user', 'payment.organization', 'payment.processedBy', 'payment.exemptedBy', 'payment.submission.verifiedBy', 'issuedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function allForUser(int $userId): Collection
    {
        return $this->model->with(['payment.user', 'payment.organization', 'payment.processedBy', 'payment.exemptedBy', 'payment.submission.verifiedBy', 'issuedBy'])
            ->whereHas('payment', fn ($query) => $query->where('user_id', $userId))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function find(int $id): ?Receipt
    {
        return $this->model->with(['payment.user', 'payment.organization', 'payment.processedBy', 'payment.exemptedBy', 'payment.submission.verifiedBy', 'issuedBy'])->find($id);
    }

    public function findByPayment(int $paymentId): ?Receipt
    {
        return $this->model->where('payment_id', $paymentId)->first();
    }

    public function findByBatchId(string $batchId): ?Receipt
    {
        return $this->model->where('batch_id', $batchId)->first();
    }

    public function findByBatch(string $batchId): ?Receipt
    {
        return $this->findByBatchId($batchId);
    }

    public function create(array $data): Receipt
    {
        return $this->model->create($data);
    }

    public function generateReceiptNumber(): string
    {
        $prefix = 'SOMS-';
        $date = now()->format('Ymd');
        $last = $this->model->whereDate('created_at', today())->count();
        return $prefix . $date . '-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
