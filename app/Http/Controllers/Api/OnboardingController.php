<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OnboardingRequest;
use App\Http\Resources\UserResource;
use App\Models\Institute;
use App\Models\Program;
use App\Services\AcademicTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OnboardingController extends Controller
{
    public function __construct(
        private AcademicTermService $termService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $institutes = Institute::with('programs')->active()->get();

        return response()->json([
            'needs_onboarding' => $user->needsOnboarding(),
            'institutes' => $institutes->pluck('name', 'code'),
            'programs' => $institutes
                ->mapWithKeys(fn (Institute $institute) => [
                    $institute->code => $institute->programs->pluck('code')->values()->all(),
                ]),
        ]);
    }

    public function update(OnboardingRequest $request): JsonResponse
    {
        $user = $request->user();

        $institute = Institute::where('code', $request->input('institute'))->firstOrFail();
        $program = Program::where('code', $request->input('program'))->firstOrFail();

        $term = $this->termService->current();

        if (!$term) {
            throw ValidationException::withMessages([
                'academic_term' => ['No active academic term is set.'],
            ]);
        }

        $enrollment = $this->termService->syncEnrollment($user, $term, [
            'institute_id' => $institute->id,
            'program_id' => $program->id,
            'is_enrolled' => true,
        ]);

        // Mirror the selection onto the current snapshot.
        $data = $request->validated();
        unset($data['institute'], $data['program']);

        $user->update([
            ...$data,
            'institute_id' => $enrollment->institute_id,
            'program_id' => $enrollment->program_id,
            'is_enrolled' => true,
        ]);

        return response()->json([
            'user' => UserResource::make($user->fresh()),
        ]);
    }
}