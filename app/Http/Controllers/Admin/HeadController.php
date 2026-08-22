<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HeadController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);

        $headRoles = array_map(fn (UserRole $r) => $r->value, UserRole::headRoles());
        $roleValues = match ($role) {
            'ssc_head' => [UserRole::SSC_HEAD->value],
            'institute_head' => [UserRole::INSTITUTE_HEAD->value],
            'sro_head' => [UserRole::SRO_HEAD->value],
            default => $headRoles,
        };

        $heads = User::query()
            ->whereHas('organizations', fn ($q) => $q->whereIn('role', $roleValues))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('organizations:id,code,name,type')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_enrolled' => $user->is_enrolled,
                'assignments' => $user->organizations
                    ->filter(fn (Organization $org) => $org->pivot->role !== null
                        && in_array($org->pivot->role->value, array_map(
                            fn (UserRole $role) => $role->value,
                            UserRole::headRoles()
                        ), true))
                    ->map(fn (Organization $org) => [
                        'organization_id' => $org->id,
                        'organization_code' => $org->code,
                        'organization_name' => $org->name,
                        'role' => $org->pivot->role?->value,
                    ])
                    ->values(),
            ]);

        return Inertia::render('admin/heads/Index', [
            'heads' => $heads,
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/heads/Create', [
            'institutes' => $this->instituteOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateHead($request);

        $institute = $this->resolveInstitute($validated);
        $program = $this->resolveProgram($validated);
        $role = UserRole::from($validated['role']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'institute_id' => $institute?->id,
            'program_id' => $program?->id,
        ]);

        $organization = $this->resolveOrganization($role, $institute, $program);

        $user->organizations()->attach($organization->id, [
            'role' => $role->value,
            'position' => $role->value,
            'assigned_at' => now(),
        ]);

        return redirect()->route('admin.heads.show', $user)
            ->with('success', 'Head account created.');
    }

    public function show(int $id): Response
    {
        $user = User::with('organizations:id,code,name,type')->findOrFail($id);

        return Inertia::render('admin/heads/Show', [
            'head' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_enrolled' => $user->is_enrolled,
                'assignments' => $user->organizations
                    ->filter(fn (Organization $org) => $org->pivot->role !== null
                        && in_array($org->pivot->role->value, array_map(
                            fn (UserRole $role) => $role->value,
                            UserRole::headRoles()
                        ), true))
                    ->map(fn (Organization $org) => [
                        'organization_id' => $org->id,
                        'organization_code' => $org->code,
                        'organization_name' => $org->name,
                        'role' => $org->pivot->role?->value,
                        'position' => $org->pivot->position,
                        'assigned_at' => $org->pivot->assigned_at
                            ? Carbon::parse($org->pivot->assigned_at)
                                ->setTimezone('Asia/Manila')
                                ->format('M d, Y h:i A')
                            : null,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function edit(int $id): Response
    {
        $user = User::with('organizations:id,code,name,type')->findOrFail($id);

        $assignment = $user->organizations
            ->filter(fn (Organization $org) => $org->pivot->role !== null
                && in_array($org->pivot->role->value, array_map(
                    fn (UserRole $role) => $role->value,
                    UserRole::headRoles()
                ), true))
            ->first();

        [$instituteId, $programId] = $assignment
            ? $this->mapOrganizationToSelection($assignment)
            : [null, null];

        return Inertia::render('admin/heads/Edit', [
            'head' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $assignment?->pivot->role?->value ?? null,
                'institute_id' => $instituteId,
                'program_id' => $programId,
            ],
            'institutes' => $this->instituteOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validated = $this->validateHead($request, $user);

        $institute = $this->resolveInstitute($validated);
        $program = $this->resolveProgram($validated);
        $role = UserRole::from($validated['role']);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'institute_id' => $institute?->id,
            'program_id' => $program?->id,
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => $validated['password']]);
        }

        $organization = $this->resolveOrganization($role, $institute, $program);

        $user->organizations()
            ->wherePivotIn('role', array_map(
                fn (UserRole $headRole) => $headRole->value,
                UserRole::headRoles()
            ))
            ->detach();

        $user->organizations()->attach($organization->id, [
            'role' => $role->value,
            'position' => $role->value,
            'assigned_at' => now(),
        ]);

        return redirect()->route('admin.heads.show', $user)
            ->with('success', 'Head account updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $user->delete();

        return redirect()->route('admin.heads.index')
            ->with('success', 'Head account deleted.');
    }

    private function validateHead(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;

        $roleRules = array_map(fn (UserRole $role) => $role->value, UserRole::headRoles());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [Rule::requiredIf($user === null), 'nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in($roleRules)],
            'institute_id' => ['nullable', 'integer', 'exists:institutes,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
        ]);

        $role = $data['role'];

        if ($role === UserRole::INSTITUTE_HEAD->value || $role === UserRole::SRO_HEAD->value) {
            if (empty($data['institute_id'])) {
                throw ValidationException::withMessages([
                    'institute_id' => 'Please choose an institute.',
                ]);
            }
        }

        if ($role === UserRole::SRO_HEAD->value) {
            if (empty($data['program_id'])) {
                throw ValidationException::withMessages([
                    'program_id' => 'Please choose a program.',
                ]);
            }

            $program = Program::find($data['program_id']);
            if ($program && (int) $program->institute_id !== (int) $data['institute_id']) {
                throw ValidationException::withMessages([
                    'program_id' => 'The program does not belong to the selected institute.',
                ]);
            }
        }

        return $data;
    }

    private function resolveInstitute(array $validated): ?Institute
    {
        $instituteId = $validated['institute_id'] ?? null;

        return $instituteId ? Institute::findOrFail($instituteId) : null;
    }

    private function resolveProgram(array $validated): ?Program
    {
        $programId = $validated['program_id'] ?? null;

        return $programId ? Program::findOrFail($programId) : null;
    }

    private function resolveOrganization(UserRole $role, ?Institute $institute, ?Program $program): Organization
    {
        return match ($role) {
            UserRole::SSC_HEAD => $this->findOrCreateOrganization(
                'SSC',
                'Supreme Student Council',
                OrganizationType::SSC,
                null
            ),
            UserRole::INSTITUTE_HEAD => $this->findOrCreateOrganization(
                "{$institute->code}-ISC",
                $institute->name,
                OrganizationType::ISC,
                Organization::where('type', OrganizationType::SSC)->first()?->id
            ),
            UserRole::SRO_HEAD => $this->findOrCreateOrganization(
                "{$program->code}-SRO",
                $program->name,
                OrganizationType::SRO,
                $this->findOrCreateOrganization(
                    "{$institute->code}-ISC",
                    $institute->name,
                    OrganizationType::ISC,
                    Organization::where('type', OrganizationType::SSC)->first()?->id
                )->id
            ),
            default => throw ValidationException::withMessages([
                'role' => 'Invalid head role.',
            ]),
        };
    }

    private function findOrCreateOrganization(
        string $code,
        string $name,
        OrganizationType $type,
        ?int $parentId
    ): Organization {
        return Organization::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
                'description' => null,
                'config' => json_encode(['penalty_amount' => 50]),
                'is_active' => true,
            ]
        );
    }

    private function mapOrganizationToSelection(Organization $organization): array
    {
        if ($organization->type === OrganizationType::SRO) {
            $programCode = str_replace('-SRO', '', $organization->code);
            $program = Program::where('code', $programCode)->first();

            return [$program?->institute_id ?? null, $program?->id ?? null];
        }

        if ($organization->type === OrganizationType::ISC) {
            $instituteCode = str_replace('-ISC', '', $organization->code);
            $institute = Institute::where('code', $instituteCode)->first();

            return [$institute?->id ?? null, null];
        }

        return [null, null];
    }

    private function instituteOptions(): array
    {
        return Institute::with('programs:id,institute_id,code,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Institute $institute) => [
                'id' => $institute->id,
                'code' => $institute->code,
                'name' => $institute->name,
                'programs' => $institute->programs->map(fn (Program $program) => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                ])->values(),
            ])
            ->all();
    }

    private function roleOptions(): array
    {
        return [
            ['value' => UserRole::SSC_HEAD->value, 'label' => 'SSC Head'],
            ['value' => UserRole::INSTITUTE_HEAD->value, 'label' => 'ISC Adviser'],
            ['value' => UserRole::SRO_HEAD->value, 'label' => 'SRO Adviser'],
        ];
    }
}
