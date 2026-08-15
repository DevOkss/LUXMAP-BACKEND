<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentRepository
{
    public function __construct(
        private Payment $model
    ) {}

    public function all(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('created_at', 'desc')->get();
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Paginate payment batches (payments created together in one cash/exempt
     * session share a batch_id). Each page item is the batch_id plus its last
     * created_at, so a batch is never split across pages.
     */
    public function paginateBatches(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->query($filters)
            ->whereNotNull('batch_id')
            ->select('batch_id', DB::raw('MAX(created_at) as last_created_at'))
            ->groupBy('batch_id')
            ->orderByDesc('last_created_at');

        return $query->paginate($perPage, ['batch_id']);
    }

    /**
     * All payments belonging to the given batches, respecting the same filters.
     */
    public function forBatches(array $batchIds, array $filters = []): Collection
    {
        if (empty($batchIds)) {
            return new Collection;
        }

        return $this->query($filters)
            ->whereIn('batch_id', $batchIds)
            ->orderBy('created_at')
            ->get();
    }

    public function findWhere(array $filters = []): Collection
    {
        return $this->query($filters)->orderBy('created_at', 'desc')->get();
    }

    public function sum(array $filters = []): float
    {
        $query = $this->query($filters);

        if (! empty($filters['exclude_exempted'])) {
            $query->where(fn (Builder $q) => $q
                ->where('status', '!=', Payment::STATUS_EXEMPTED)
                ->where('isExempted', false));
        }

        return (float) $query->sum('amount');
    }

    private function query(array $filters): Builder
    {
        $query = $this->model->with(['user', 'organization', 'academicTerm', 'fee', 'receipt', 'event', 'exemptedBy', 'event.organization']);

        if (! empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        if (! empty($filters['organization_ids'])) {
            $query->whereIn('organization_id', $filters['organization_ids']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (! empty($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        if (! empty($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('student_number', 'like', "%{$search}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function find(int $id): ?Payment
    {
        return $this->model->with(['user', 'organization', 'academicTerm', 'fee', 'receipt', 'event', 'exemptedBy', 'event.organization'])->find($id);
    }

    public function findByUuid(string $uuid): ?Payment
    {
        return $this->model->with(['user', 'organization', 'academicTerm', 'fee', 'receipt', 'event', 'exemptedBy', 'processedBy', 'event.organization'])->where('uuid', $uuid)->first();
    }

    public function create(array $data): Payment
    {
        return $this->model->create($data);
    }

    public function update(Payment $payment, array $data): bool
    {
        return $payment->update($data);
    }

    public function delete(Payment $payment): bool
    {
        return $payment->delete();
    }

    public function findByUser(int $userId): Collection
    {
        return $this->model->with(['organization', 'academicTerm', 'fee', 'receipt', 'event'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function countByOrganization(int $orgId): int
    {
        return $this->model->where('organization_id', $orgId)->count();
    }
}
