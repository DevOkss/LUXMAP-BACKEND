<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeeRequest;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\PenaltyFee;
use App\Services\AccessScopeService;
use App\Services\FeeService;
use App\Services\NotificationService;
use App\Services\PenaltyFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeeController extends Controller
{
    public function __construct(
        private FeeService $feeService,
        private PenaltyFeeService $penaltyFeeService,
        private AccessScopeService $accessScopeService,
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): Response
    {
        $orgIds = $this->accessScopeService->scopeOrganizationIds($request->user());

        $fees = Fee::with('organization')
            ->whereIn('organization_id', $orgIds)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Fee $fee) => $this->feePayload($fee));

        return Inertia::render('admin/fees/Index', [
            'fees' => $fees,
            'can_manage_fees' => $this->canManageFees($request->user()),
            'penalties' => $this->penaltyPayloads($orgIds),
            'can_manage_penalties' => $this->canManageFees($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/fees/Create', [
            'organizations' => $this->scopeOrganizations($request->user()),
            'academic_terms' => \App\Models\AcademicTerm::query()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(['id', 'academic_year', 'semester', 'is_active'])
                ->map(fn (\App\Models\AcademicTerm $t) => [
                    'id' => $t->id,
                    'name' => $t->displayName(),
                    'is_active' => $t->is_active,
                ]),
        ]);
    }

    public function store(FeeRequest $request): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), Organization::findOrFail($request->input('organization_id')))) {
            abort(403);
        }

        $fee = $this->feeService->create($request->validated());

        return redirect()->route('admin.fees.show', $fee)->with('success', 'Fee draft created.');
    }

    public function show(Fee $fee, Request $request): Response
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $fee->organization)) {
            abort(403, 'This fee is outside your scope.');
        }

        return Inertia::render('admin/fees/Show', [
            'fee' => $this->feePayload($fee),
            'can_manage_fees' => $this->canManageFees($request->user()),
        ]);
    }

    public function edit(Fee $fee, Request $request): Response
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $fee->organization)) {
            abort(403, 'This fee is outside your scope.');
        }

        return Inertia::render('admin/fees/Edit', [
            'fee' => $this->feePayload($fee),
            'organizations' => $this->scopeOrganizations($request->user()),
            'academic_terms' => \App\Models\AcademicTerm::query()
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get(['id', 'academic_year', 'semester', 'is_active'])
                ->map(fn (\App\Models\AcademicTerm $t) => [
                    'id' => $t->id,
                    'name' => $t->displayName(),
                    'is_active' => $t->is_active,
                ]),
        ]);
    }

    public function update(FeeRequest $request, Fee $fee): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $fee->organization)) {
            abort(403);
        }

        $this->feeService->update($fee->id, $request->validated());

        return redirect()->route('admin.fees.show', $fee)->with('success', 'Fee updated.');
    }

    public function destroy(Fee $fee): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope(request()->user(), $fee->organization)) {
            abort(403);
        }

        $this->feeService->delete($fee->id);

        return redirect()->route('admin.fees.index')->with('success', 'Fee deleted.');
    }

    public function publish(Fee $fee): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope(request()->user(), $fee->organization)) {
            abort(403);
        }

        $this->feeService->publish($fee);
        $this->notificationService->notifyFeePosted($fee->refresh());

        return redirect()->route('admin.fees.show', $fee)->with('success', 'Fee posted — eligible students notified.');
    }

    public function storePenalty(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'exists:organizations,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $org = Organization::findOrFail($data['organization_id']);
        if (! $this->accessScopeService->isWithinScope($request->user(), $org)) {
            abort(403);
        }

        $this->penaltyFeeService->set($org->id, (float) $data['amount'], $request->user()->id);

        return redirect()->route('admin.fees.index')->with('success', "Penalty amount updated for {$org->name}.");
    }

    public function unpublish(Fee $fee): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope(request()->user(), $fee->organization)) {
            abort(403);
        }

        $this->feeService->unpublish($fee);

        return redirect()->route('admin.fees.show', $fee)->with('success', 'Fee unposted — no longer shown to students.');
    }

    private function scopeOrganizations($user): array
    {
        return $this->accessScopeService->scopeOrganizations($user)
            ->map(fn ($org) => [
                'id' => $org->id,
                'code' => $org->code,
                'name' => $org->name,
                'type' => $org->type->value,
            ])
            ->values()
            ->all();
    }

    private function penaltyPayloads(array $orgIds): array
    {
        return Organization::whereIn('id', $orgIds)
            ->orderBy('code')
            ->get()
            ->map(function (Organization $org) {
                $current = $this->penaltyFeeService->current($org->id);

                return [
                    'id' => $org->id,
                    'code' => $org->code,
                    'name' => $org->name,
                    'type' => $org->type->value,
                    'current_amount' => $current ? (float) $current->amount : null,
                    'effective_at' => $current?->effective_at?->format('Y-m-d H:i'),
                ];
            })
            ->values()
            ->all();
    }

    private function feePayload(Fee $fee): array
    {
        return [
            'id' => $fee->id,
            'name' => $fee->name,
            'description' => $fee->description,
            'amount' => (float) $fee->amount,
            'term' => $fee->term,
            'academic_term_id' => $fee->academic_term_id,
            'academic_term' => $fee->academicTerm?->displayName(),
            'required_years' => $fee->required_years ?? ['all'],
            'due_date' => $fee->due_date?->format('Y-m-d'),
            'status' => $fee->status,
            'users_count' => $this->feeService->eligibleCount($fee),
            'organization' => $fee->organization ? ['id' => $fee->organization->id, 'name' => $fee->organization->name] : null,
        ];
    }

    private function canManageFees($user): bool
    {
        return $user->hasRole(UserRole::headRoles());
    }
}
