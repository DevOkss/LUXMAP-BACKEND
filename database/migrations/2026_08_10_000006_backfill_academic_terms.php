<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs run migrations before seeders create users; those get
        // their term + enrollments from DatabaseSeeder (AcademicTermSeeder).
        // This backfill only snapshots an already-populated database.
        if (DB::table('users')->count() === 0) {
            return;
        }

        $year = '2026-2027';
        $semester = '1st';

        $termId = DB::table('academic_terms')->insertGetId([
            'academic_year' => $year,
            'semester' => $semester,
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-20',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tag any existing institution accounts with the current term.
        DB::table('institution_accounts')
            ->whereNull('academic_year')
            ->update(['academic_year' => $year, 'semester' => $semester]);

        // Snapshot every user's current enrollment into the active term so the
        // per-term history table matches today's live state.
$users = DB::table('users')
            ->select('id', 'institute_id', 'program_id', 'year_level', 'is_enrolled', 'created_at', 'updated_at')
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            DB::table('student_enrollments')->insert([
                'user_id' => $user->id,
                'academic_term_id' => $termId,
                'institute_id' => $user->institute_id,
                'program_id' => $user->program_id,
                'year_level' => $user->year_level,
                'is_enrolled' => $user->is_enrolled,
                'created_at' => $user->created_at ?? now(),
                'updated_at' => $user->updated_at ?? now(),
            ]);
        }

        // Assign existing fees and events to the active term.
        DB::table('fees')->whereNull('academic_term_id')->update(['academic_term_id' => $termId]);
        DB::table('events')->whereNull('academic_term_id')->update(['academic_term_id' => $termId]);
    }

    public function down(): void
    {
        DB::table('fees')->whereNull('academic_term_id')->delete();
        DB::table('events')->whereNull('academic_term_id')->delete();
        DB::table('student_enrollments')->delete();
        DB::table('institution_accounts')->update(['academic_year' => null, 'semester' => null]);
        DB::table('academic_terms')->delete();
    }
};