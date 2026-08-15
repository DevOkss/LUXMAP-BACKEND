<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventLog;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\EventRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class EventService
{
    public function __construct(
        private EventRepository $repository,
        private AcademicTermService $terms
    ) {}

    public function list(array $filters = []): Collection
    {
        return $this->repository->all($filters);
    }

    public function find(int $id): ?Event
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Event
    {
        $data['qr_secret'] = Str::random(64);
        $data['academic_term_id'] = $data['academic_term_id'] ?? $this->terms->current()?->id;

        $event = $this->repository->create($data);
        $this->log($event, request()->user(), 'created', ['title' => $event->title]);

        return $event;
    }

    public function update(Event $event, array $data): Event
    {
        $before = $event->only(['title', 'description', 'venue', 'event_date']);
        $event = $this->repository->update($event, $data);
        $this->log($event, request()->user(), 'updated', ['before' => $before, 'after' => $event->only(['title', 'description', 'venue', 'event_date'])]);

        return $event;
    }

    public function delete(Event $event): bool
    {
        $this->log($event, request()->user(), 'deleted', ['title' => $event->title]);
        return $this->repository->delete($event);
    }

    public function publish(Event $event): Event
    {
        $event = $this->repository->update($event, ['status' => 'published']);
        $this->log($event, request()->user(), 'published');
        return $event;
    }

    public function unpublish(Event $event): Event
    {
        $event = $this->repository->update($event, ['status' => 'draft']);
        $this->log($event, request()->user(), 'unpublished');
        return $event;
    }

    public function completeAttendance(Event $event): Event
    {
        $event = $this->repository->update($event, ['status' => 'completed']);
        $this->log($event, request()->user(), 'completed');

        return $event;
    }

    public function getUpcoming(int|array $organizationIds): Collection
    {
        return $this->repository->getUpcoming($organizationIds);
    }

    public function getStudentOrganizationStats(User $user): array
    {
        $orgs = [];

        $ssc = Organization::ssc()->active()->first();
        if ($ssc) {
            $orgs[] = $ssc;
        }

        $instituteId = $user->institute_id;
        $programId = $user->program_id;

        $enrollment = $user->currentEnrollment();
        if ($enrollment) {
            $instituteId = $enrollment->institute_id ?? $instituteId;
            $programId = $enrollment->program_id ?? $programId;
        }

        if ($instituteId) {
            $isc = Organization::isc()->active()->where('institute_id', $instituteId)->first();
            if ($isc) {
                $orgs[] = $isc;
            }
        }

        if ($programId) {
            $sro = Organization::sro()->active()->where('program_id', $programId)->first();
            if ($sro) {
                $orgs[] = $sro;
            }
        }

        return array_map(function (Organization $org) {
            $total = Event::where('organization_id', $org->id)->count();
            $upcoming = Event::where('organization_id', $org->id)
                ->where('status', 'published')
                ->upcoming()
                ->count();

            return [
                'id' => $org->id,
                'name' => $org->name,
                'code' => $org->code,
                'type' => $org->type,
                'total_events' => $total,
                'upcoming_count' => $upcoming,
            ];
        }, $orgs);
    }

    public function log(Event $event, ?User $user, string $action, ?array $details = null): void
    {
        if (!$user) return;
        EventLog::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
