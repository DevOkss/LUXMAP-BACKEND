<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShiftRequest;
use App\Services\AcademicTermService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftRequestController extends Controller
{
    public function __construct(
        private AcademicTermService $termService
    ) {}

    public function index(Request $request): Response
    {
        $requests = ShiftRequest::with([
            'user:id,name,student_number',
            'currentInstitute:id,name',
            'currentProgram:id,name',
            'requestedInstitute:id,name',
            'requestedProgram:id,name',
        ])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->through(fn (ShiftRequest $shift) => [
                'id' => $shift->id,
                'student' => [
                    'name' => $shift->user?->name,
                    'student_number' => $shift->user?->student_number,
                ],
                'current' => [
                    'institute' => $shift->currentInstitute?->name,
                    'program' => $shift->currentProgram?->name,
                ],
                'requested' => [
                    'institute' => $shift->requestedInstitute?->name,
                    'program' => $shift->requestedProgram?->name,
                ],
                'reason' => $shift->reason,
                'status' => $shift->status,
                'remarks' => $shift->remarks,
                'reviewed_at' => $shift->reviewed_at?->toDateTimeString(),
                'created_at' => $shift->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/shift-requests/Index', [
            'requests' => $requests,
            'filters' => ['status' => $request->input('status')],
        ]);
    }

    public function approve(Request $request, ShiftRequest $shiftRequest): RedirectResponse
    {
        $this->authorize($shiftRequest);

        if ($shiftRequest->status !== ShiftRequest::STATUS_PENDING) {
            return redirect()->route('admin.shift-requests.index')
                ->with('error', 'This request has already been reviewed.');
        }

        $user = $shiftRequest->user;
        $term = $this->termService->current();

        if ($term && $user) {
            $enrollment = $user->enrollmentForTerm($term);

            if ($enrollment) {
                // Only the current term's snapshot is updated; past terms stay intact.
                $enrollment->update([
                    'institute_id' => $shiftRequest->requested_institute_id,
                    'program_id' => $shiftRequest->requested_program_id,
                ]);

                $user->update([
                    'institute_id' => $shiftRequest->requested_institute_id,
                    'program_id' => $shiftRequest->requested_program_id,
                ]);
            }
        }

        $shiftRequest->update([
            'status' => ShiftRequest::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        return redirect()->route('admin.shift-requests.index')
            ->with('success', 'Shift request approved — the student now belongs to the requested institute/program.');
    }

    public function reject(Request $request, ShiftRequest $shiftRequest): RedirectResponse
    {
        $this->authorize($shiftRequest);

        if ($shiftRequest->status !== ShiftRequest::STATUS_PENDING) {
            return redirect()->route('admin.shift-requests.index')
                ->with('error', 'This request has already been reviewed.');
        }

        // Rejection never touches the enrollment record.
        $shiftRequest->update([
            'status' => ShiftRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $request->input('remarks'),
        ]);

        return redirect()->route('admin.shift-requests.index')
            ->with('success', 'Shift request rejected.');
    }

    private function authorize(ShiftRequest $shiftRequest): void
    {
        abort_unless($this->canReview(), 403, 'You are not allowed to review shift requests.');
    }

    private function canReview(): bool
    {
        return request()->user()->isSuperAdmin();
    }
}