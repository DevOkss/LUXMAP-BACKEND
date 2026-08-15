<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Repositories\PaymentAccountRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Organization official payment accounts (the destination students pay to).
 * Managed by the organization's own heads/officers within their scope;
 * superadmin can manage any organization's account.
 */
class PaymentAccountService
{
    public function __construct(
        private PaymentAccountRepository $repository,
        private AccessScopeService $accessScope
    ) {}

    public function list(User $actor): Collection
    {
        $orgIds = $this->accessScope->scopeOrganizationIds($actor);

        return $this->repository->all($actor->isSuperAdmin() ? [] : $orgIds);
    }

    public function show(int $id, User $actor): ?PaymentAccount
    {
        $account = $this->repository->find($id);
        if ($account) {
            $this->authorize($actor, $account->organization);
        }

        return $account;
    }

    public function forOrganization(int $organizationId, ?User $actor = null): ?PaymentAccount
    {
        if ($actor) {
            $this->authorize($actor, Organization::find($organizationId));
        }

        return $this->repository->forOrganization($organizationId);
    }

    public function upsert(int $organizationId, array $data, ?User $actor = null): PaymentAccount
    {
        $organization = Organization::find($organizationId);
        if (!$organization) {
            throw ValidationException::withMessages(['organization_id' => 'Organization not found.']);
        }

        if ($actor) {
            $this->authorize($actor, $organization);
        }

        $account = $this->repository->forOrganization($organizationId);

        if (isset($data['qr_code_image']) && $data['qr_code_image'] instanceof UploadedFile) {
            $data['qr_code_image'] = $data['qr_code_image']->store('payment-accounts', 'public');
        } elseif (isset($data['qr_code_image']) && $data['qr_code_image'] === null) {
            $data['qr_code_image'] = $account?->qr_code_image;
        }

        $payload = [
            'account_name' => $data['account_name'] ?? null,
            'account_provider' => $data['account_provider'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'qr_code_image' => $data['qr_code_image'] ?? ($account?->qr_code_image ?? null),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($account) {
            // One account per organization: mutate in place, never create a second.
            $this->repository->update($account, $payload);

            return $account->fresh();
        }

        return $this->repository->create([
            ...$payload,
            'organization_id' => $organizationId,
            'created_by' => $actor?->id,
        ]);
    }

    public function delete(int $id, User $actor): bool
    {
        $account = $this->repository->find($id);
        if (!$account) {
            return false;
        }

        $this->authorize($actor, $account->organization);

        return $this->repository->delete($account);
    }

    private function authorize(User $actor, ?Organization $organization): void
    {
        if (!$organization) {
            abort(404, 'Organization not found.');
        }

        if (!$this->accessScope->isWithinScope($actor, $organization)) {
            abort(403, 'This payment account is outside your scope.');
        }
    }
}