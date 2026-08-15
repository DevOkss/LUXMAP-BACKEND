<?php

namespace App\Repositories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;

class EventRepository
{
    public function all(array $filters = []): Collection
    {
        $query = Event::with('organization', 'qrConfigurations');

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['organization_ids'])) {
            $query->whereIn('organization_id', $filters['organization_ids']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('event_date', 'desc')->get();
    }

    public function findById(int $id): ?Event
    {
        return Event::with('organization', 'attendances')->find($id);
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);
        return $event->fresh();
    }

    public function delete(Event $event): bool
    {
        return $event->delete();
    }

    public function getUpcoming(int|array $organizationIds): Collection
    {
        $query = Event::with('organization', 'qrConfigurations')
            ->where('status', 'published')
            ->upcoming()
            ->orderBy('event_date');

        if (is_array($organizationIds)) {
            $query->whereIn('organization_id', $organizationIds);
        } else {
            $query->where('organization_id', $organizationIds);
        }

        return $query->get();
    }
}
