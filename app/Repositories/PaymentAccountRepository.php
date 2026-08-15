<?php

namespace App\Repositories;

use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Collection;

class PaymentAccountRepository
{
    public function __construct(
        private PaymentAccount $model
    ) {}

    public function all(array $organizationIds = []): Collection
    {
        $query = $this->model->with('organization')->orderBy('created_at', 'desc');

        if ($organizationIds) {
            $query->whereIn('organization_id', $organizationIds);
        }

        return $query->get();
    }

    public function find(int $id): ?PaymentAccount
    {
        return $this->model->with('organization')->find($id);
    }

    public function forOrganization(int $organizationId): ?PaymentAccount
    {
        return $this->model->forOrganization($organizationId)->active()->first();
    }

    public function create(array $data): PaymentAccount
    {
        return $this->model->create($data);
    }

    public function update(PaymentAccount $account, array $data): bool
    {
        return $account->update($data);
    }

    public function delete(PaymentAccount $account): bool
    {
        return $account->delete();
    }
}