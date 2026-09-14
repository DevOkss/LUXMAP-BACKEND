<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the set of organizations a user may manage based on their role.
 *
 *   super_admin       -> every organization
 *   ssc_head/officer  -> the SSC only
 *   institute_head/isc_officer -> the ISC only
 *   sro_head/officer  -> the single SRO
 */
class AccessScopeService
{
    /**
     * Organizations the user is allowed to manage.
     * When $term is provided, officer assignments are filtered to that term;
     * heads (whose pivot has no term) remain visible for any term so they can
     * manage officers across terms. Super admin sees every org regardless.
     */
    public function scopeOrganizations(User $user, ?\App\Models\AcademicTerm $term = null): Collection
    {
        if ($user->isSuperAdmin()) {
            return Organization::query()->get();
        }

        $query = $user->organizations();

        // Term-aware filtering for officer assignments
        if ($term) {
            $query->where(function ($q) use ($term) {
                $q->where('organization_user.academic_term_id', $term->id)
                  ->orWhereNull('organization_user.academic_term_id');
            });
        }

        $pivots = $query->get();

        $scope = collect();

        foreach ($pivots as $org) {
            $role = $org->pivot->role;

            if ($role === null || $role === UserRole::STUDENT) {
                continue;
            }

            if ($role === UserRole::SSC_HEAD || $role === UserRole::SSC_OFFICER) {
                $scope = $scope->merge([$org->id]);
                continue;
            }

            if ($role === UserRole::INSTITUTE_HEAD || $role === UserRole::ISC_OFFICER) {
                $scope = $scope->merge([$org->id]);
                continue;
            }

            if ($role === UserRole::SRO_HEAD || $role === UserRole::SRO_OFFICER) {
                $scope = $scope->merge([$org->id]);
            }
        }

        return Organization::query()
            ->whereIn('id', $scope->unique()->all())
            ->get();
    }

    /**
     * IDs of all organizations the user may manage.
     *
     * Only orgs where the user holds an officer/head role count as manageable —
     * a student's enrollment orgs grant read access (see viewableOrganizationIds)
     * but never write access.
     */
    public function scopeOrganizationIds(User $user, ?\App\Models\AcademicTerm $term = null): array
    {
        return $this->scopeOrganizations($user, $term)->pluck('id')->all();
    }

    public function studentOrganizationIds(User $user): array
    {
        $ids = [];

        $ssc = Organization::ssc()->active()->first();
        if ($ssc) {
            $ids[] = $ssc->id;
        }

        $instituteId = $user->institute_id;
        $programId = $user->program_id;

        $enrollment = $user->currentEnrollment();
        if ($enrollment) {
            $instituteId = $enrollment->institute_id ?? $instituteId;
            $programId = $enrollment->program_id ?? $programId;
        }

        if ($instituteId) {
            $isc = Organization::isc()->active()->where('institute_id', $instituteId)->first();
            if ($isc) {
                $ids[] = $isc->id;
            }
        }

        if ($programId) {
            $sro = Organization::sro()->active()->where('program_id', $programId)->first();
            if ($sro) {
                $ids[] = $sro->id;
            }
        }

        return $ids;
    }

    /**
     * IDs of all organizations the user may view, merging the manage scope
     * with the student scope (SSC + their ISC by institute + their SRO by program).
     * A user who is both an officer and a student can therefore see both.
     * When $term is provided the manage scope is term-aware.
     */
    public function viewableOrganizationIds(User $user, ?\App\Models\AcademicTerm $term = null): array
    {
        if ($user->isSuperAdmin()) {
            return Organization::query()->pluck('id')->all();
        }

        $ids = array_merge(
            $this->scopeOrganizations($user, $term)->pluck('id')->all(),
            $this->studentOrganizationIds($user)
        );

        return array_values(array_unique($ids));
    }

    /**
     * Whether the given organization falls within the user's manage scope.
     * When $term is provided the check is term-aware (officer must be
     * assigned for that term).
     */
    public function isWithinScope(User $user, Organization $organization, ?\App\Models\AcademicTerm $term = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($organization->id, $this->scopeOrganizationIds($user, $term), true);
    }

    /**
     * Whether the user may assign officers to the given target organization.
     * Heads may only manage officers in the organizations they head directly.
     */
    public function canManageOfficersIn(User $user, Organization $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->headOrganizations($user)->pluck('id')->contains($target->id);
    }

    /**
     * Organizations the user heads directly (where officer assignment is allowed).
     */
    public function headOrganizations(User $user): Collection
    {
        return $user->organizations()
            ->wherePivotIn('role', array_map(
                fn (UserRole $role) => $role->value,
                UserRole::headRoles()
            ))
            ->get();
    }

    private function withDescendants(Organization $org): Collection
    {
        $ids = [$org->id];
        $cursor = collect([$org]);

        while ($cursor->isNotEmpty()) {
            $children = Organization::query()
                ->whereIn('parent_id', $cursor->pluck('id'))
                ->get();

            $ids = array_merge($ids, $children->pluck('id')->all());
            $cursor = $children;
        }

        return Organization::query()->whereIn('id', array_unique($ids))->get();
    }
}
