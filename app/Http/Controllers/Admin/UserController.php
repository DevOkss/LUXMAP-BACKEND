<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(
        private PermissionRegistry $permissionRegistry
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);

        $headRoles = array_map(fn (UserRole $r) => $r->value, UserRole::headRoles());
        $staffRoles = array_map(fn (UserRole $r) => $r->value, UserRole::staffRoles());

        $users = User::query()
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            }))
            ->when($role === 'heads', fn ($q) => $q->whereHas('organizations', fn ($q) => $q->whereIn('role', $headRoles)))
            ->when($role === 'officers', fn ($q) => $q->whereHas('organizations', fn ($q) => $q->whereIn('role', $staffRoles)))
            ->when(! $role, fn ($q) => $q->whereDoesntHave('organizations', fn ($q) => $q->whereIn('role', $headRoles)))
            ->with('organizations:id,code,name,type')
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
                'is_enrolled' => $user->is_enrolled,
                'email_verified_at' => $user->email_verified_at,
                'primary_role' => $this->permissionRegistry->resolveRoleFor($user)->value,
                'organizations' => $user->organizations->map(fn (Organization $org) => [
                    'id' => $org->id,
                    'code' => $org->code,
                    'name' => $org->name,
                    'role' => $org->pivot->role?->value,
                ]),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function show(int $id)
    {
        $user = User::with('organizations:id,code,name,type')->findOrFail($id);

        return Inertia::render('admin/users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'student_number' => $user->student_number,
                'is_enrolled' => $user->is_enrolled,
                'created_at' => $user->created_at,
                'email_verified_at' => $user->email_verified_at,
                'organizations' => $user->organizations->map(fn (Organization $org) => [
                    'id' => $org->id,
                    'code' => $org->code,
                    'name' => $org->name,
                    'role' => $org->pivot->role?->value,
                ]),
            ],
        ]);
    }
}
