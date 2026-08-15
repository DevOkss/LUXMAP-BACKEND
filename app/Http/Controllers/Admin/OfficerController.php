<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OfficerController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $scopedOrgIds = $this->accessScopeService->headOrganizations($user)->pluck('id')->all();
        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);

        $officers = User::query()
            ->whereHas('organizations', fn ($q) => $q->whereIn('organization_id', $scopedOrgIds)
                ->whereIn('role', array_column(UserRole::staffRoles(), 'value')))
            ->with('organizations:id,code,name,type')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (User $officer) => [
                'id' => $officer->id,
                'name' => $officer->name,
                'email' => $officer->email,
                'student_number' => $officer->student_number,
                'assignments' => $officer->organizations
                    ->filter(fn (Organization $org) => in_array($org->id, $scopedOrgIds, true))
                    ->map(fn (Organization $org) => [
                        'organization_id' => $org->id,
                        'organization_code' => $org->code,
                        'organization_name' => $org->name,
                        'role' => $org->pivot->role?->value,
                        'position' => $org->pivot->position,
                    ])
                    ->values(),
            ]);

        return Inertia::render('admin/officers/Index', [
            'officers' => $officers,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $target = $this->accessScopeService->headOrganizations($user)->first();

        return Inertia::render('admin/officers/Assign', [
            'target' => $target ? [
                'id' => $target->id,
                'code' => $target->code,
                'name' => $target->name,
                'type' => $target->type->value,
            ] : null,
        ]);
    }

    public function search(Request $request)
    {
        $user = $request->user();
        $query = trim((string) $request->input('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $target = $this->accessScopeService->headOrganizations($user)->first();

        $candidates = User::query()
            ->where('is_enrolled', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('student_number', 'like', "%{$query}%");
            })
            ->whereDoesntHave('organizations', fn ($q) => $q->whereIn('role', array_merge(
                array_column(UserRole::staffRoles(), 'value'),
                array_column(UserRole::adviserRoles(), 'value'),
                array_column(UserRole::headRoles(), 'value'),
                [UserRole::SUPER_ADMIN->value],
            )))
            ->when($target, function ($q) use ($target) {
                if ($target->type === OrganizationType::ISC) {
                    $institute = \App\Models\Institute::where('code', str_replace('-ISC', '', $target->code))->first();
                    if ($institute) {
                        $q->where('institute_id', $institute->id);
                    }
                } elseif ($target->type === OrganizationType::SRO) {
                    $program = \App\Models\Program::where('code', str_replace('-SRO', '', $target->code))->first();
                    if ($program) {
                        $q->where('program_id', $program->id);
                    }
                }

                return $q;
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'student_number'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'student_number' => $u->student_number,
            ]);

        return response()->json(['users' => $candidates]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'string', 'max:255'],
        ]);

        $target = $this->accessScopeService->headOrganizations($user)->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'user_id' => 'You have no organization to assign officers to.',
            ]);
        }

        if (! $this->accessScopeService->canManageOfficersIn($user, $target)) {
            throw ValidationException::withMessages([
                'user_id' => 'You can only assign officers within your own scope.',
            ]);
        }

        $role = $this->roleForType($target->type);

        $target->users()->syncWithoutDetaching([$validated['user_id'] => [
            'role' => $role->value,
            'position' => $validated['position'],
            'assigned_at' => now(),
        ]]);

        return redirect()->route('admin.officers.index')
            ->with('success', 'Officer assigned.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $target = Organization::findOrFail($validated['organization_id']);

        if (! $this->accessScopeService->canManageOfficersIn($user, $target)) {
            throw ValidationException::withMessages([
                'organization_id' => 'You can only revoke officers within your own scope.',
            ]);
        }

        $target->users()->detach($validated['user_id']);

        return back()->with('success', 'Officer assignment revoked.');
    }

    private function roleForType(OrganizationType $type): UserRole
    {
        return match ($type) {
            OrganizationType::SSC => UserRole::SSC_OFFICER,
            OrganizationType::ISC => UserRole::ISC_OFFICER,
            OrganizationType::SRO => UserRole::SRO_OFFICER,
        };
    }
}
