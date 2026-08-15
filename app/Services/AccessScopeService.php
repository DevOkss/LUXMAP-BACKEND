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
     */
    public function scopeOrganizations(User $user): Collection
    {
        $pivots = $user->organizations()->get();

        if ($user->isSuperAdmin()) {
            return Organization::query()->get();
        }

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
    public function scopeOrganizationIds(User $user): array
    {
        return $this->scopeOrganizations($user)->pluck('id')->all();
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
     */
    public function viewableOrganizationIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Organization::query()->pluck('id')->all();
        }

        $ids = array_merge(
            $this->scopeOrganizations($user)->pluck('id')->all(),
            $this->studentOrganizationIds($user)
        );

        return array_values(array_unique($ids));
    }

    /**
     * Whether the given organization falls within the user's manage scope.
     */
    public function isWithinScope(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($organization->id, $this->scopeOrganizationIds($user), true);
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
