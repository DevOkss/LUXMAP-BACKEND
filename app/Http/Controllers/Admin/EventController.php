<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventDraftRequest;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Organization;
use App\Services\AccessScopeService;
use App\Services\AttendanceExportService;
use App\Services\EventService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService,
        private EventService $eventService,
        private AttendanceExportService $attendanceExportService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request): Response
    {
        $orgIds = $this->accessScopeService->scopeOrganizationIds($request->user());

        $events = Event::with('organization:id,code,name,type')
            ->whereIn('organization_id', $orgIds)
            ->orderBy('event_date', 'desc')
            ->paginate(20)
            ->through(fn (Event $event) => [
                'id' => $event->id,
                'uuid' => $event->uuid,
                'title' => $event->title,
                'description' => $event->description,
                'venue' => $event->venue,
                'event_date' => $event->event_date,
                'time_from' => $event->time_from,
                'time_to' => $event->time_to,
                'status' => $event->status,
                'organization' => $event->organization
                    ? ['id' => $event->organization->id, 'code' => $event->organization->code, 'name' => $event->organization->name]
                    : null,
            ]);

        return Inertia::render('admin/events/Index', [
            'events' => $events,
            'can_manage_events' => $this->canManageEvents($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/events/Create', [
            'organizations' => $this->scopeOrganizations($request->user()),
        ]);
    }

    public function store(EventDraftRequest $request): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), Organization::findOrFail($request->input('organization_id')))) {
            abort(403);
        }

        $data = $request->validated();
        $data['status'] = 'draft';
        $event = $this->eventService->create($data);

        return redirect()->route('admin.events.show', $event)->with('success', 'Activity draft created.');
    }

    public function show(Event $event, Request $request): Response
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403, 'This event is outside your scope.');
        }

        return Inertia::render('admin/events/Show', [
            'event' => $event->load(['organization:id,code,name,type', 'qrConfigurations']),
            'can_manage_events' => $this->canManageEvents($request->user()),
        ]);
    }

    public function edit(Event $event, Request $request): Response
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403, 'This event is outside your scope.');
        }

        return Inertia::render('admin/events/Edit', [
            'event' => $event->load('organization:id,code,name,type'),
            'organizations' => $this->scopeOrganizations($request->user()),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->update($event, $request->validated());

        return redirect()->route('admin.events.show', $event)->with('success', 'Activity updated.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->delete($event);

        return redirect()->route('admin.events.index')->with('success', 'Activity deleted.');
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->publish($event);
        $this->notificationService->notifyEventPosted($event->refresh());

        return redirect()->route('admin.events.show', $event)->with('success', 'Activity posted.');
    }

    public function unpublish(Request $request, Event $event): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->unpublish($event);

        return redirect()->route('admin.events.show', $event)->with('success', 'Activity unposted.');
    }

    public function complete(Request $request, Event $event): RedirectResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->completeAttendance($event);

        return redirect()->route('admin.events.show', $event)->with('success', 'Activity completed.');
    }

    public function qr(Event $event, Request $request): Response
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403, 'This event is outside your scope.');
        }

        return Inertia::render('admin/events/QrConfig', [
            'event' => $event->load('organization:id,code,name,type'),
            'configs' => $event->qrConfigurations()->orderBy('id')->get(),
        ]);
    }

    public function exportAttendance(Event $event, Request $request): StreamedResponse
    {
        if (! $this->accessScopeService->isWithinScope($request->user(), $event->organization)) {
            abort(403);
        }

        $this->eventService->log($event, $request->user(), 'attendance_exported');

        return $this->attendanceExportService->download($event);
    }

    private function scopeOrganizations($user): array
    {
        return $this->accessScopeService->scopeOrganizations($user)
            ->map(fn ($org) => [
                'id' => $org->id,
                'code' => $org->code,
                'name' => $org->name,
                'type' => $org->type->value,
            ])
            ->values()
            ->all();
    }

    private function canManageEvents($user): bool
    {
        return $user->hasStaffRole();
    }
}
