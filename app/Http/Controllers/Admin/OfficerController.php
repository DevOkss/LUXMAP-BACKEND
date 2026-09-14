<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Organization;
use App\Models\User;
use App\Services\AcademicTermService;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OfficerController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService,
        private AcademicTermService $terms
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $term = $this->resolveTerm((int) $request->input('academic_term_id', 0)) ?? $this->terms->current();
        $scopedOrgIds = $this->accessScopeService->headOrganizations($user)->pluck('id')->all();
        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);

        // When a term is selected, show only officers assigned for that term (plus legacy null for backward compat)
        $officersQuery = User::query()
            ->whereHas('organizations', function ($q) use ($scopedOrgIds, $term) {
                $q->whereIn('organization_id', $scopedOrgIds)
                  ->whereIn('role', array_column(UserRole::staffRoles(), 'value'));
                if ($term) {
                    $q->where(function ($qq) use ($term) {
                        $qq->where('organization_user.academic_term_id', $term->id)
                           ->orWhereNull('organization_user.academic_term_id');
                    });
                }
            })
            ->with(['organizations' => function ($q) use ($scopedOrgIds, $term) {
                $q->whereIn('organization_id', $scopedOrgIds)
                  ->whereIn('role', array_column(UserRole::staffRoles(), 'value'));
                if ($term) {
                    $q->where(function ($qq) use ($term) {
                        $qq->where('organization_user.academic_term_id', $term->id)
                           ->orWhereNull('organization_user.academic_term_id');
                    });
                }
            }])
            ->orderBy('name');

        $officers = $officersQuery
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
                        'academic_term_id' => $org->pivot->academic_term_id,
                        'academic_term' => $org->pivot->academic_term_id ? AcademicTerm::find($org->pivot->academic_term_id)?->displayName() : null,
                    ])
                    ->values(),
            ]);

        return Inertia::render('admin/officers/Index', [
            'officers' => $officers,
            'academic_terms' => $this->academicTermsForPicker(),
            'selected_term' => $term?->id ?? null,
            'filters' => ['academic_term_id' => $term?->id ?? null],
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $target = $this->accessScopeService->headOrganizations($user)->first();
        $terms = $this->academicTermsForPicker();
        $selectedTerm = $this->resolveTerm((int) $request->input('academic_term_id', 0)) ?? $this->terms->current();

        return Inertia::render('admin/officers/Assign', [
            'target' => $target ? [
                'id' => $target->id,
                'code' => $target->code,
                'name' => $target->name,
                'type' => $target->type->value,
            ] : null,
            'academic_terms' => $terms,
            'selected_term' => $selectedTerm?->id ?? null,
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
            'academic_term_id' => ['sometimes', 'integer', 'exists:academic_terms,id'],
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

        $term = null;
        if (!empty($validated['academic_term_id'])) {
            $term = AcademicTerm::findOrFail($validated['academic_term_id']);
        } else {
            $term = $this->terms->current();
        }

        $role = $this->roleForType($target->type);

        // Prevent duplicate for same officer/head/org/term
        $termIdForCheck = $term?->id;
        $existsQuery = \Illuminate\Support\Facades\DB::table('organization_user')
            ->where('user_id', $validated['user_id'])
            ->where('organization_id', $target->id)
            ->where('role', $role->value);
        if ($termIdForCheck) {
            $existsQuery->where('academic_term_id', $termIdForCheck);
        } else {
            $existsQuery->whereNull('academic_term_id');
        }
        if ($existsQuery->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'This officer is already assigned to this organization for the selected academic term.',
            ]);
        }

        // Allow same officer to have assignments across different terms
        $target->users()->attach($validated['user_id'], [
            'role' => $role->value,
            'position' => $validated['position'],
            'assigned_at' => now(),
            'academic_term_id' => $termIdForCheck,
        ]);

        $redirectTerm = $termIdForCheck ? ['academic_term_id' => $termIdForCheck] : [];
        $termLabel = $term ? $term->displayName() : 'selected term';
        return redirect()->route('admin.officers.index', $redirectTerm)
            ->with('success', 'Officer assigned for '.$termLabel.'.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'academic_term_id' => ['sometimes', 'integer', 'exists:academic_terms,id'],
        ]);

        $target = Organization::findOrFail($validated['organization_id']);

        if (! $this->accessScopeService->canManageOfficersIn($user, $target)) {
            throw ValidationException::withMessages([
                'organization_id' => 'You can only revoke officers within your own scope.',
            ]);
        }

        if (!empty($validated['academic_term_id'])) {
            \Illuminate\Support\Facades\DB::table('organization_user')
                ->where('user_id', $validated['user_id'])
                ->where('organization_id', $target->id)
                ->where('academic_term_id', $validated['academic_term_id'])
                ->delete();
        } else {
            // Legacy: detach all terms for this user/org (or null)
            $target->users()->detach($validated['user_id']);
        }

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

    private function resolveTerm(int $academicTermId): ?AcademicTerm
    {
        if ($academicTermId <= 0) {
            return null;
        }
        return AcademicTerm::find($academicTermId);
    }

    private function academicTermsForPicker(): array
    {
        return AcademicTerm::query()
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get(['id', 'academic_year', 'semester', 'is_active'])
            ->map(fn (AcademicTerm $term) => [
                'id' => $term->id,
                'name' => $term->displayName(),
                'is_active' => $term->is_active,
            ])
            ->values()
            ->all();
    }
}
