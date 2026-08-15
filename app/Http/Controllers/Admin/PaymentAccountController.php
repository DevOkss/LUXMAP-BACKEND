<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Http\Requests\PaymentAccountRequest;
use App\Models\Organization;
use App\Services\AccessScopeService;
use App\Services\PaymentAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PaymentAccountController extends Controller
{
    public function __construct(
        private PaymentAccountService $accounts,
        private AccessScopeService $accessScope
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $accounts = $this->accounts->list($user);

        $organizationIds = $this->accessScope->scopeOrganizationIds($user);
        $organizations = Organization::whereIn('id', $organizationIds)
            ->orWhereIn('id', $accounts->pluck('organization_id'))
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'name' => $org->name,
                'type' => $org->type,
                'has_account' => $accounts->firstWhere('organization_id', $org->id) !== null,
            ])
            ->values();

        return Inertia::render('admin/payment-accounts/Index', [
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'organization' => $account->organization ? ['id' => $account->organization->id, 'name' => $account->organization->name] : null,
                'account_name' => $account->account_name,
                'account_provider' => $account->account_provider,
                'account_number' => $account->account_number,
                'qr_code_image_url' => $account->qr_code_image ? Storage::url($account->qr_code_image) : null,
                'is_active' => (bool) $account->is_active,
            ])->values(),
            'organizations' => $organizations,
            'can_manage' => $user->isSuperAdmin() || $user->hasRole(UserRole::headRoles()),
        ]);
    }

    public function store(PaymentAccountRequest $request)
    {
        $account = $this->accounts->upsert(
            $request->integer('organization_id'),
            $request->validated(),
            $request->user()
        );

        return redirect()->route('admin.payment-accounts.index')->with('success', 'Payment account saved.');
    }

    public function destroy(Request $request, int $id)
    {
        $deleted = $this->accounts->delete($id, $request->user());

        if (!$deleted) {
            return redirect()->route('admin.payment-accounts.index')->with('error', 'Payment account not found.');
        }

        return redirect()->route('admin.payment-accounts.index')->with('success', 'Payment account removed.');
    }
}