<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShiftRequestStoreRequest;
use App\Models\Institute;
use App\Models\Program;
use App\Models\ShiftRequest;
use App\Services\AcademicTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftRequestController extends Controller
{
    public function __construct(
        private AcademicTermService $termService
    ) {}

    /**
     * The authenticated student's shift requests and their statuses.
     */
    public function index(Request $request): JsonResponse
    {
        $requests = $request->user()->shiftRequests()
            ->with(['requestedInstitute', 'requestedProgram', 'currentInstitute', 'currentProgram'])
            ->latest()
            ->get()
            ->map(fn (ShiftRequest $shift) => $this->payload($shift));

        return response()->json(['data' => $requests]);
    }

    public function store(ShiftRequestStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $enrollment = $user->currentEnrollment();

        if (!$enrollment || !$enrollment->institute_id || !$enrollment->program_id) {
            throw ValidationException::withMessages([
                'shift' => ['Complete onboarding before requesting a shift.'],
            ]);
        }

        if ($user->shiftRequests()->pending()->exists()) {
            throw ValidationException::withMessages([
                'shift' => ['You already have a pending shift request.'],
            ]);
        }

        $institute = Institute::where('code', $request->input('requested_institute'))->firstOrFail();
        $program = Program::where('code', $request->input('requested_program'))->firstOrFail();

        $shift = $user->shiftRequests()->create([
            'current_institute_id' => $enrollment->institute_id,
            'current_program_id' => $enrollment->program_id,
            'requested_institute_id' => $institute->id,
            'requested_program_id' => $program->id,
            'reason' => $request->input('reason'),
            'status' => ShiftRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'data' => $this->payload($shift->load(['requestedInstitute', 'requestedProgram', 'currentInstitute', 'currentProgram'])),
        ], 201);
    }

    public function show(Request $request, ShiftRequest $shiftRequest): JsonResponse
    {
        abort_unless($shiftRequest->user_id === $request->user()->id, 404);

        return response()->json([
            'data' => $this->payload($shiftRequest->load(['requestedInstitute', 'requestedProgram', 'currentInstitute', 'currentProgram'])),
        ]);
    }

    private function payload(ShiftRequest $shift): array
    {
        return [
            'id' => $shift->id,
            'status' => $shift->status,
            'reason' => $shift->reason,
            'remarks' => $shift->remarks,
            'current' => [
                'institute' => $shift->currentInstitute?->name,
                'program' => $shift->currentProgram?->name,
            ],
            'requested' => [
                'institute' => $shift->requestedInstitute?->name,
                'program' => $shift->requestedProgram?->name,
            ],
            'created_at' => $shift->created_at,
            'reviewed_at' => $shift->reviewed_at,
        ];
    }
}