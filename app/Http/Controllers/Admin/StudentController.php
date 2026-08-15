<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Services\AcademicTermService;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService,
        private AcademicTermService $termService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $target = $this->accessScopeService->headOrganizations($user)->first();
        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $years = array_filter(explode(',', (string) $request->input('years', '')));
        $programId = $request->input('program_id');
        $perPage = min(max((int) $request->input('per_page', 10), 10), 100);

        $programs = null;
        $instituteId = null;

        if ($target?->type === OrganizationType::ISC) {
            $institute = Institute::with('programs:id,institute_id,name')->where('code', str_replace('-ISC', '', $target->code))->first();
            if ($institute) {
                $instituteId = $institute->id;
                $programs = $institute->programs->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values();
            }
        }

        // The current academic term (when set) is the authority for who is
        // enrolled, their institute/program and year level.
        $term = $this->termService->current();

        $students = User::query()
            ->whereDoesntHave('organizations', fn ($q) => $q->whereIn('role', array_map(
                fn (UserRole $r) => $r->value,
                UserRole::headRoles()
            )))
            ->when($term, function ($q) use ($term) {
                $q->join('student_enrollments', function ($join) use ($term) {
                    $join->on('student_enrollments.user_id', '=', 'users.id')
                        ->where('student_enrollments.academic_term_id', $term->id);
                })->select('users.*');
            })
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.student_number', 'like', "%{$search}%");
            }))
            ->when($status === 'enrolled', fn ($q) => $q->where($term ? 'student_enrollments.is_enrolled' : 'users.is_enrolled', true))
            ->when($status === 'not_enrolled', fn ($q) => $q->where($term ? 'student_enrollments.is_enrolled' : 'users.is_enrolled', false))
            ->when(count($years), fn ($q) => $q->whereIn($term ? 'student_enrollments.year_level' : 'users.year_level', array_map('intval', $years)))
            ->when($programId, fn ($q) => $q->where($term ? 'student_enrollments.program_id' : 'users.program_id', $programId))
            ->when($instituteId, fn ($q) => $q->where($term ? 'student_enrollments.institute_id' : 'users.institute_id', $instituteId))
            ->when($target && $target->type === OrganizationType::SRO, function ($q) use ($target) {
                $program = Program::where('code', str_replace('-SRO', '', $target->code))->first();
                if ($program) {
                    $q->where('users.program_id', $program->id);
                }

                return $q;
            })
            ->with([
                'program:id,name',
                'studentEnrollments' => fn ($q) => $term ? $q->where('academic_term_id', $term->id) : $q->whereRaw('1 = 0'),
            ])
            ->orderBy($sort === 'name' ? 'users.name' : 'users.year_level', $direction)
            ->paginate($perPage)
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'student_number' => $u->student_number,
                'year_level' => $u->studentEnrollments->first()?->year_level ?? $u->year_level,
                'program' => $u->program?->name,
                'is_enrolled' => $u->studentEnrollments->first()?->is_enrolled ?? $u->is_enrolled,
            ]);

        return Inertia::render('admin/students/Index', [
            'students' => $students,
            'programs' => $programs,
            'filters' => ['search' => $search, 'status' => $status, 'sort' => $sort, 'direction' => $direction, 'years' => $request->input('years'), 'program_id' => $programId],
        ]);
    }
}
