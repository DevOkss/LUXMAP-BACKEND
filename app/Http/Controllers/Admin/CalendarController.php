<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AccessScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $orgIds = $this->accessScopeService->scopeOrganizationIds($user);

        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);

        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        $events = Event::with('organization:id,code,name,type')
            ->whereIn('organization_id', $orgIds)
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->orderBy('event_date')
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'title' => $event->title,
                'event_date' => $event->event_date->format('Y-m-d'),
                'status' => $event->status,
                'organization' => $event->organization
                    ? ['id' => $event->organization->id, 'code' => $event->organization->code, 'name' => $event->organization->name]
                    : null,
            ]);

        return Inertia::render('admin/calendar/Index', [
            'events' => $events,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
