<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShiftRequest;
use App\Services\AcademicTermService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftRequestController extends Controller
{
    public function __construct(
        private AcademicTermService $termService,
        private NotificationService $notifications
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
                    'institute_id' => $shift->current_institute_id,
                    'program_id' => $shift->current_program_id,
                ],
                'requested' => [
                    'institute' => $shift->requestedInstitute?->name,
                    'program' => $shift->requestedProgram?->name,
                    'institute_id' => $shift->requested_institute_id,
                    'program_id' => $shift->requested_program_id,
                ],
                'reason' => $shift->reason,
                'status' => $shift->status,
                'remarks' => $shift->remarks,
                'reviewed_at' => $shift->reviewed_at?->toDateTimeString(),
                'created_at' => $shift->created_at?->toDateTimeString(),
            ]);

        $institutes = \App\Models\Institute::with('programs:id,institute_id,code,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($inst) => [
                'id' => $inst->id,
                'code' => $inst->code,
                'name' => $inst->name,
                'programs' => $inst->programs->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name])->values(),
            ])->values();

        return Inertia::render('admin/shift-requests/Index', [
            'requests' => $requests,
            'filters' => ['status' => $request->input('status')],
            'institutes' => $institutes,
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

        try {
            $this->notifications->notifyShiftRequestReviewed($shiftRequest->fresh(['user', 'requestedInstitute', 'requestedProgram']));
        } catch (\Throwable $e) {
            report($e);
        }

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

        try {
            $this->notifications->notifyShiftRequestReviewed($shiftRequest->fresh(['user', 'requestedInstitute', 'requestedProgram']));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.shift-requests.index')
            ->with('success', 'Shift request rejected.');
    }

    public function update(Request $request, ShiftRequest $shiftRequest): RedirectResponse
    {
        $this->authorize($shiftRequest);

        if ($shiftRequest->status !== ShiftRequest::STATUS_PENDING) {
            return redirect()->route('admin.shift-requests.index')
                ->with('error', 'Only pending requests can be modified.');
        }

        $validated = $request->validate([
            'requested_institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'requested_program_id' => ['required', 'integer', 'exists:programs,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $program = \App\Models\Program::find($validated['requested_program_id']);
        if ($program && (int) $program->institute_id !== (int) $validated['requested_institute_id']) {
            return redirect()->back()->withErrors(['requested_program_id' => 'The program does not belong to the selected institute.']);
        }

        $shiftRequest->update([
            'requested_institute_id' => $validated['requested_institute_id'],
            'requested_program_id' => $validated['requested_program_id'],
            'reason' => $validated['reason'] ?? $shiftRequest->reason,
        ]);

        return redirect()->route('admin.shift-requests.index')
            ->with('success', 'Shift request updated.');
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