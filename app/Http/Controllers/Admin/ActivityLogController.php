<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService
    ) {}

    public function index(Request $request): Response
    {
        $orgIds = $this->accessScopeService->scopeOrganizationIds($request->user());
        $perPage = min(max((int) $request->integer('per_page', 20), 10), 100);
        $from = $request->input('from');
        $to = $request->input('to');

        $query = EventLog::with(['user:id,name,student_number', 'event:id,uuid,title,organization_id'])
            ->whereHas('event', fn ($q) => $q->whereIn('organization_id', $orgIds));

        if ($from) {
            $query->whereDate('event_logs.created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('event_logs.created_at', '<=', $to);
        }

        $logs = $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EventLog $log) => [
                'id' => $log->id,
                'user' => $log->user
                    ? ['id' => $log->user->id, 'name' => $log->user->name, 'student_number' => $log->user->student_number]
                    : null,
                'action' => $log->action,
                'details' => $log->details,
                'event' => $log->event
                    ? ['uuid' => $log->event->uuid, 'title' => $log->event->title]
                    : null,
                'created_at' => $log->created_at,
            ]);

        return Inertia::render('admin/activity-logs/Index', [
            'logs' => $logs,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
