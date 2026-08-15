<?php

namespace App\Repositories;

use App\Models\PaymentSubmission;
use Illuminate\Database\Eloquent\Collection;

class PaymentSubmissionRepository
{
    public function __construct(
        private PaymentSubmission $model
    ) {}

    public function create(array $data): PaymentSubmission
    {
        return $this->model->create($data);
    }

    public function find(int $id): ?PaymentSubmission
    {
        return $this->model->with(['user', 'organization', 'academicTerm', 'fee', 'event', 'verifiedBy'])
            ->find($id);
    }

    public function pendingByGroup(string $groupKey): Collection
    {
        return $this->model
            ->with(['user', 'organization', 'academicTerm', 'fee', 'event', 'verifiedBy'])
            ->byGroup($groupKey)
            ->pending()
            ->lockForUpdate()
            ->get();
    }

    public function groupRows(string $groupKey): Collection
    {
        return $this->model
            ->with(['user', 'organization', 'academicTerm', 'fee', 'event', 'verifiedBy'])
            ->byGroup($groupKey)
            ->orderBy('id')
            ->get();
    }

    /**
     * Pending submission groups (grouped by group_key) for the reviewer's
     * authorized organizations.
     */
    public function pendingGroups(array $organizationIds, array $filters = []): Collection
    {
        $query = $this->model
            ->with(['user', 'organization', 'academicTerm', 'fee', 'event', 'verifiedBy'])
            ->whereIn('organization_id', $organizationIds)
            ->pending();

        foreach (['fee_type', 'payment_channel'] as $key) {
            if (!empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (!empty($filters['academic_term_id'])) {
            $query->where('academic_term_id', $filters['academic_term_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }

        return $query->orderBy('created_at', 'desc')->get()->groupBy('group_key');
    }

    public function userGroups(int $userId): Collection
    {
        return $this->model
            ->with(['organization', 'academicTerm', 'fee', 'event', 'verifiedBy'])
            ->forUser($userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('group_key');
    }
}