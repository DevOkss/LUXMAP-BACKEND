<?php

namespace App\Repositories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceRepository
{
    public function findByConfigurationAndUser(int $configId, int $userId): ?Attendance
    {
        return Attendance::where('qr_configuration_id', $configId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }

    public function getUserHistory(User $user, int $limit = 50): Collection
    {
        return Attendance::with(['qrConfiguration', 'event.organization'])
            ->where('user_id', $user->id)
            ->orderBy('scanned_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUserHistoryByOrg(User $user, int $orgId, int $perPage = 10): LengthAwarePaginator
    {
        return Attendance::with(['qrConfiguration', 'event.organization'])
            ->where('user_id', $user->id)
            ->whereHas('event', fn($q) => $q->where('organization_id', $orgId))
            ->orderBy('scanned_at', 'desc')
            ->paginate($perPage);
    }

    public function getEventAttendances(int $eventId): Collection
    {
        return Attendance::with('user')
            ->whereHas('qrConfiguration', fn($q) => $q->where('event_id', $eventId))
            ->orderBy('scanned_at')
            ->get();
    }

    public function countByEvent(int $eventId): int
    {
        return Attendance::whereHas('qrConfiguration', fn($q) => $q->where('event_id', $eventId))->count();
    }
}
