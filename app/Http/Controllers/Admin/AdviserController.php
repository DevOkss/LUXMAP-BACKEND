<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdviserController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $scopedOrgIds = $this->accessScopeService->scopeOrganizationIds($user);

        $advisers = User::query()
            ->whereHas('organizations', fn ($q) => $q->whereIn('organization_id', $scopedOrgIds)
                ->whereIn('role', array_column(UserRole::adviserRoles(), 'value')))
            ->with('organizations:id,code,name,type')
            ->orderBy('name')
            ->get()
            ->map(fn (User $adviser) => [
                'id' => $adviser->id,
                'name' => $adviser->name,
                'email' => $adviser->email,
                'assignments' => $adviser->organizations
                    ->filter(fn (Organization $org) => in_array($org->id, $scopedOrgIds, true)
                        && in_array($org->pivot->role?->value, array_column(UserRole::adviserRoles(), 'value'), true))
                    ->map(fn (Organization $org) => [
                        'organization_id' => $org->id,
                        'organization_code' => $org->code,
                        'organization_name' => $org->name,
                        'role' => $org->pivot->role?->value,
                        'assigned_at' => $org->pivot->assigned_at
                            ? Carbon::parse($org->pivot->assigned_at)
                                ->setTimezone('Asia/Manila')
                                ->format('M d, Y')
                            : null,
                    ])
                    ->values(),
            ]);

        return Inertia::render('admin/advisers/Index', [
            'advisers' => $advisers,
        ]);
    }
}
