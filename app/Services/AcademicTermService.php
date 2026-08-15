<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\StudentEnrollment;
use App\Models\User;

class AcademicTermService
{
    /**
     * The single currently-active term. Returns null when none is active.
     */
    public function current(): ?AcademicTerm
    {
        return AcademicTerm::active()->latest('id')->first();
    }

    /**
     * Find an existing term, or create it, from the institution API payload's
     * academic_year + semester. If no term is currently active, the newly
     * created term becomes active.
     */
    public function fromPayload(?string $academicYear, ?string $semester): ?AcademicTerm
    {
        if (!$academicYear || !$semester) {
            return null;
        }

        $term = AcademicTerm::forYearSemester($academicYear, $semester)->first();

        if ($term) {
            return $term;
        }

        return AcademicTerm::create([
            'academic_year' => $academicYear,
            'semester' => $semester,
            'is_active' => AcademicTerm::active()->doesntExist(),
        ]);
    }

    /**
     * Activate one term and deactivate the rest.
     */
    public function activate(AcademicTerm $term): AcademicTerm
    {
        AcademicTerm::where('id', '!=', $term->id)->update(['is_active' => false]);
        $term->update(['is_active' => true]);

        return $term->fresh();
    }

    /**
     * Create or refresh the student's enrollment for the given term from the
     * institution API values. Institute/program are only updated when
     * explicitly provided (onboarding/shift approval).
     *
     * Never overwrite historical term records — each term keeps its own row.
     */
    public function syncEnrollment(
        User $user,
        AcademicTerm $term,
        array $data
    ): StudentEnrollment {
        $enrollment = $user->enrollmentForTerm($term);

        $values = [
            'is_enrolled' => $data['is_enrolled'] ?? true,
        ];

        if (array_key_exists('year_level', $data)) {
            $values['year_level'] = $data['year_level'];
        }

        if (array_key_exists('institute_id', $data)) {
            $values['institute_id'] = $data['institute_id'];
        }

        if (array_key_exists('program_id', $data)) {
            $values['program_id'] = $data['program_id'];
        }

        if ($enrollment) {
            $enrollment->update($values);

            return $enrollment->fresh();
        }

        return StudentEnrollment::create([
            'user_id' => $user->id,
            'academic_term_id' => $term->id,
            ...$values,
        ]);
    }
}