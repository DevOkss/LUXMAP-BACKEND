<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Single source of truth for role-based permissions. Maps each role to a set
 * of capabilities and UI modules consumed by both the Inertia admin portal and
 * the API/PWA.
 */
class PermissionRegistry
{
    /** Admin portal module keys. */
    public const ADMIN_MODULES = [
        'dashboard', 'heads', 'users', 'officers', 'institutes',
        'events', 'calendar', 'fees', 'payments', 'penalties',
        'payment_submissions', 'payment_accounts',
        'notifications', 'activity_logs', 'shift_requests',
        'academic_terms',
    ];

    /** Head-of-organization portal module keys. */
    public const HEAD_MODULES = [
        'dashboard', 'officers', 'events', 'calendar', 'fees', 'payments',
        'payment_accounts',
        'penalties', 'notifications', 'activity_logs', 'device_bindings',
    ];

    /** Officer-facing module keys. */
    public const OFFICER_MODULES = [
        'dashboard', 'students', 'events', 'attendance', 'fees', 'payments',
        'payment_submissions',
        'receipts', 'reports', 'notifications',
    ];

    /** Officer modules in the Laravel admin portal (sidebar + navigation). */
    public const ADMIN_OFFICER_MODULES = [
        'dashboard', 'events', 'calendar', 'fees', 'payments',
        'payment_accounts',
        'notifications', 'activity_logs',
    ];

    /** Student-facing module keys. */
    public const STUDENT_MODULES = [
        'dashboard', 'attendance_scanner', 'attendance_queue', 'attendance_history',
        'fees', 'payments', 'receipts', 'notifications', 'profile', 'settings',
    ];

    public const OFFICER_CAPABILITIES = [
        'manage_events',
        'manage_attendance',
        'verify_payments',
        'view_fees',
        'view_penalties',
        'manage_receipts',
        'view_reports',
        'manage_notifications',
    ];

    public const STUDENT_CAPABILITIES = [
        'view_notifications',
    ];

    private const PRECEDENCE = [
        UserRole::SUPER_ADMIN->value => 8,
        UserRole::SSC_HEAD->value => 7,
        UserRole::INSTITUTE_HEAD->value => 6,
        UserRole::SRO_HEAD->value => 5,
        UserRole::SSC_OFFICER->value => 4,
        UserRole::ISC_OFFICER->value => 3,
        UserRole::SRO_OFFICER->value => 2,
        UserRole::STUDENT->value => 1,
    ];

    public function capabilitiesFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::SUPER_ADMIN => [...self::ADMIN_CAPABILITIES, 'can_manage_all'],
            UserRole::SSC_HEAD, UserRole::INSTITUTE_HEAD, UserRole::SRO_HEAD => self::HEAD_CAPABILITIES,
            UserRole::SSC_OFFICER, UserRole::ISC_OFFICER, UserRole::SRO_OFFICER => self::OFFICER_CAPABILITIES,
            UserRole::STUDENT => self::STUDENT_CAPABILITIES,
        };
    }

    public function modulesFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::SUPER_ADMIN => ['dashboard', 'heads', 'users', 'institutes', 'notifications', 'activity_logs', 'shift_requests', 'academic_terms', 'device_bindings'],
            UserRole::SSC_HEAD => [...self::HEAD_MODULES, 'advisers'],
            UserRole::INSTITUTE_HEAD, UserRole::SRO_HEAD => [
                ...array_slice(self::HEAD_MODULES, 0, 2), 'students', ...array_slice(self::HEAD_MODULES, 2),
            ],
            UserRole::SSC_OFFICER, UserRole::ISC_OFFICER, UserRole::SRO_OFFICER => self::ADMIN_OFFICER_MODULES,
            UserRole::STUDENT => self::STUDENT_MODULES,
        };
    }

    /**
     * Resolve the user's effective role for the given organization scope, or
     * the highest-precedence role across all of their organizations.
     */
    public function resolveRoleFor(User $user, ?Organization $organization = null): UserRole
    {
        if ($organization) {
            return $user->roleInOrganization($organization) ?? UserRole::STUDENT;
        }

        $role = $user->organizations()
            ->get()
            ->map(fn (Organization $org) => $org->pivot->role)
            ->filter()
            ->sortByDesc(fn (UserRole $role) => self::PRECEDENCE[$role->value] ?? 0)
            ->first();

        return $role ?? UserRole::STUDENT;
    }

    /**
     * Resolve the full permission payload for a user within an optional scope.
     *
     * @return array{role: string, can_manage_all: bool, capabilities: array, modules: array}
     */
    public function permissionsFor(User $user, ?Organization $organization = null): array
    {
        $role = $this->resolveRoleFor($user, $organization);

        return [
            'role' => $role->value,
            'can_manage_all' => $role === UserRole::SUPER_ADMIN,
            'capabilities' => $this->capabilitiesFor($role),
            'modules' => $this->modulesFor($role),
        ];
    }

    public function hasCapability(UserRole $role, string $capability): bool
    {
        return in_array($capability, $this->capabilitiesFor($role), true);
    }

    /** Monitor + personnel capabilities granted to heads in the portal. */
    public const HEAD_CAPABILITIES = [
        'manage_officers',
        'view_events',
        'view_calendar',
        'view_penalties',
        'view_fees',
        'view_payments',
        'view_notifications',
    ];

    private const ADMIN_CAPABILITIES = [
        'manage_heads',
        'manage_users',
        'manage_institutes',
        'manage_events',
        'manage_attendance',
        'manage_fees',
        'manage_penalties',
        'manage_payments',
        'manage_receipts',
        'view_reports',
        'view_audit_logs',
        'manage_notifications',
        'manage_academic_terms',
    ];
}
