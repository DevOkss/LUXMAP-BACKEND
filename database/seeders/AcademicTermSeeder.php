<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    public function run(): void
    {
        $term = AcademicTerm::firstOrCreate(
            ['academic_year' => '2026-2027', 'semester' => '1st'],
            [
                'start_date' => '2026-08-01',
                'end_date' => '2026-12-20',
                'is_active' => true,
            ]
        );

        if (! $term->is_active) {
            AcademicTerm::where('id', '!=', $term->id)->update(['is_active' => false]);
            $term->update(['is_active' => true]);
        }

        // Snapshot each user's current enrollment columns into the active term
        // (idempotent — never overwrites institute/program once onboarded).
        User::withTrashed()->chunkById(200, function ($users) use ($term) {
            foreach ($users as $user) {
                StudentEnrollment::firstOrCreate(
                    ['user_id' => $user->id, 'academic_term_id' => $term->id],
                    [
                        'institute_id' => $user->institute_id,
                        'program_id' => $user->program_id,
                        'year_level' => $user->year_level,
                        'is_enrolled' => $user->is_enrolled,
                    ]
                );
            }
        });
    }
}