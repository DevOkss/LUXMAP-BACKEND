<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Models\AcademicTerm;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Determines which students an organization scope covers (SSC = all,
 * ISC = institute, SRO = program), optionally narrowed by required year levels.
 *
 * Membership is always resolved through the student's per-term enrollment
 * snapshot for the relevant academic term (the organization's fee/event term,
 * or the currently active term). When no academic term exists yet — i.e.
 * before the term system is seeded — it falls back to the legacy users
 * columns so existing records keep working.
 */
class EligibilityService
{
    public function __construct(
        private AcademicTermService $terms
    ) {}

    public function studentIds(Organization $organization, array $requiredYears = [], ?AcademicTerm $term = null): Collection
    {
        $term = $term ?? $this->terms->current();

        if ($term) {
            return $this->enrollmentStudentIds($organization, $requiredYears, $term);
        }

        return $this->legacyStudentIds($organization, $requiredYears);
    }

    public function studentIdsForEvent(Event $event): Collection
    {
        return $this->studentIds(
            $event->organization,
            $event->requiredYears(),
            $event->academicTerm ?? $this->terms->current()
        );
    }

    /**
     * The organizations a student belongs to for the current academic term:
     * SSC (all), their ISC institute, and their SRO program. Only active
     * organizations.
     */
    public function userOrganizations(User $user): Collection
    {
        return $this->userOrganizationsForTerm($user, $this->terms->current());
    }

    /**
     * The organizations a student belonged to for a specific academic term.
     * Membership is resolved from the student's per-term enrollment snapshot
     * (never their current institute/program/year), so historical obligations
     * stay anchored to the term they were incurred in.
     */
    public function userOrganizationsForTerm(User $user, ?AcademicTerm $term = null): Collection
    {
        $orgs = new Collection();

        $ssc = Organization::ssc()->active()->first();
        if ($ssc) {
            $orgs->push($ssc);
        }

        $instituteId = $user->institute_id;
        $programId = $user->program_id;

        $enrollment = $term ? $user->enrollmentForTerm($term) : $user->currentEnrollment();
        if ($enrollment) {
            $instituteId = $enrollment->institute_id ?? $instituteId;
            $programId = $enrollment->program_id ?? $programId;
        }

        if ($instituteId) {
            $isc = Organization::isc()->active()->where('institute_id', $instituteId)->first();
            if ($isc) {
                $orgs->push($isc);
            }
        }

        if ($programId) {
            $sro = Organization::sro()->active()->where('program_id', $programId)->first();
            if ($sro) {
                $orgs->push($sro);
            }
        }

        return $orgs;
    }

    private function enrollmentStudentIds(Organization $organization, array $requiredYears, AcademicTerm $term): Collection
    {
        $query = User::query()
            ->join('student_enrollments', function ($join) use ($term) {
                $join->on('student_enrollments.user_id', '=', 'users.id')
                    ->where('student_enrollments.academic_term_id', $term->id)
                    ->where('student_enrollments.is_enrolled', true);
            });

        if ($organization->type === OrganizationType::ISC && $organization->institute_id) {
            $query->where('student_enrollments.institute_id', $organization->institute_id);
        } elseif ($organization->type === OrganizationType::SRO && $organization->program_id) {
            $query->where('student_enrollments.program_id', $organization->program_id);
        }

        $years = array_map('strval', $requiredYears ?: []);
        if ($years && !in_array('all', $years, true)) {
            $query->whereIn('student_enrollments.year_level', $years);
        }

        return $query->pluck('users.id')->map(fn ($id) => (int) $id);
    }

    private function legacyStudentIds(Organization $organization, array $requiredYears): Collection
    {
        $query = User::query()
            ->where('is_enrolled', true)
            ->whereNull('deleted_at');

        if ($organization->type === OrganizationType::ISC && $organization->institute_id) {
            $query->where('institute_id', $organization->institute_id);
        } elseif ($organization->type === OrganizationType::SRO && $organization->program_id) {
            $query->where('program_id', $organization->program_id);
        }

        $years = array_map('strval', $requiredYears ?: []);
        if ($years && !in_array('all', $years, true)) {
            $query->whereIn('year_level', $years);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id);
    }
}