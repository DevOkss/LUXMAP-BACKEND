<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventDraftRequest;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function __construct(
        private EventService $eventService,
        private AccessScopeService $accessScopeService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['organization_id', 'status']);
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            $scopedIds = $this->resolvedScopeIds($user);

            if (!empty($filters['organization_id'])) {
                if (!in_array((int) $filters['organization_id'], $scopedIds)) {
                    abort(403);
                }
            } elseif (!empty($scopedIds)) {
                $filters['organization_ids'] = $scopedIds;
            }
        }

        $events = $this->eventService->list($filters);

        return EventResource::collection($events);
    }

    public function store(EventRequest $request)
    {
        if (!$this->accessScopeService->isWithinScope($request->user(), Organization::findOrFail($request->input('organization_id')))) {
            abort(403);
        }

        $event = $this->eventService->create($request->validated());

        return EventResource::make($event)->response()->setStatusCode(201);
    }

    public function storeDraft(EventDraftRequest $request)
    {
        if (!$this->accessScopeService->isWithinScope($request->user(), Organization::findOrFail($request->input('organization_id')))) {
            abort(403);
        }

        $data = $request->validated();
        $data['status'] = 'draft';

        $event = $this->eventService->create($data);

        return EventResource::make($event)->response()->setStatusCode(201);
    }

    public function show(Event $event)
    {
        Gate::authorize('view', $event);

        $event->load('organization', 'qrConfigurations');

        return EventResource::make($event);
    }

    public function update(EventRequest $request, Event $event)
    {
        Gate::authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        return EventResource::make($event);
    }

    public function destroy(Event $event)
    {
        Gate::authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json(null, 204);
    }

    public function publish(Event $event)    { Gate::authorize('update', $event); $event = $this->eventService->publish($event); return EventResource::make($event); }
    public function unpublish(Event $event)  { Gate::authorize('update', $event); $event = $this->eventService->unpublish($event); return EventResource::make($event); }
    public function complete(Event $event)   { Gate::authorize('update', $event); $event = $this->eventService->completeAttendance($event); return EventResource::make($event); }

    public function upcoming(Request $request)
    {
        $orgIds = $this->resolvedScopeIds($request->user());
        $events = $this->eventService->getUpcoming($orgIds);

        return EventResource::collection($events);
    }

    private function resolvedScopeIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Organization::query()->pluck('id')->all();
        }

        return $user->hasOfficerRole()
            ? $this->accessScopeService->scopeOrganizationIds($user)
            : $this->accessScopeService->viewableOrganizationIds($user);
    }

    public function studentEvents(Request $request)
    {
        $user = $request->user();
        $organizations = $this->eventService->getStudentOrganizationStats($user);

        return response()->json(['organizations' => $organizations]);
    }
}

