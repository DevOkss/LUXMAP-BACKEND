<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Event;
use App\Services\AttendanceExportService;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private AttendanceExportService $attendanceExportService
    ) {}

    public function scan(AttendanceRequest $request)
    {
        $attendance = $this->attendanceService->scan(
            $request->validated(),
            $request->user()
        );

        return AttendanceResource::make($attendance)->response()->setStatusCode(201);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'records' => ['required', 'array'],
            'records.*.qr_configuration_id' => ['required', 'integer', 'exists:qr_configurations,id'],
            'records.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.scanned_at' => ['required', 'date'],
        ]);

        // Process inline so attendance is actually saved without depending on a
        // queue worker (the old implementation dispatched a queued job and
        // returned success immediately, leaving records unsaved when no worker
        // was running).
        $results = $this->attendanceService->syncOffline($request->input('records'), $request->user());

        $saved = collect($results)->where('success', true)->count();

        return response()->json([
            'processed' => count($results),
            'saved' => $saved,
            'skipped' => count($results) - $saved,
            'results' => $results,
        ]);
    }

    public function history(Request $request)
    {
        $orgId = $request->integer('organization_id');
        $perPage = min((int) $request->integer('per_page', 10), 50);

        if ($orgId) {
            $attendances = $this->attendanceService->history($request->user(), $perPage, $orgId);
            return AttendanceResource::collection($attendances);
        }

        $attendances = $this->attendanceService->history($request->user());
        return AttendanceResource::collection($attendances);
    }

    public function studentStats(Request $request): JsonResponse
    {
        $stats = $this->attendanceService->getStudentStats($request->user());

        return response()->json(['organizations' => $stats]);
    }

    public function studentEvents(Request $request): JsonResponse
    {
        $orgId = $request->integer('organization_id');
        $organization = \App\Models\Organization::findOrFail($orgId);

        return response()->json([
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
            'data' => $this->attendanceService->getStudentEventsByOrg($request->user(), $orgId),
        ]);
    }

    public function exportEvent(Event $event): StreamedResponse
    {
        Gate::authorize('update', $event);

        return $this->attendanceExportService->download($event);
    }
}

