<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\QrConfiguration;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private AttendanceRepository $repository,
        private QrCodeService $qrCodeService,
    ) {}

    public function scan(array $data, User $user): Attendance
    {
        $qrConfig = QrConfiguration::find($data['qr_configuration_id'] ?? 0);
        if (! $qrConfig) {
            throw ValidationException::withMessages(['qr_configuration_id' => ['QR configuration not found.']]);
        }

        $existing = $this->repository->findByConfigurationAndUser($qrConfig->id, $user->id);

        if ($existing) {
            throw ValidationException::withMessages(['attendance' => ['You have already marked attendance for this session.']]);
        }

        $academicTermId = Event::whereKey($qrConfig->event_id)->value('academic_term_id');

        return $this->repository->create([
            'qr_configuration_id' => $qrConfig->id,
            'user_id' => $user->id,
            'academic_term_id' => $academicTermId,
            'scanned_at' => $data['scanned_at'] ?? now(),
            'synced_at' => $data['synced_at'] ?? now(),
        ]);
    }

    public function syncOffline(array $records, User $user): array
    {
        $results = [];

        foreach ($records as $record) {
            try {
                if (($record['user_id'] ?? null) != $user->id) {
                    throw ValidationException::withMessages(['user_id' => ['Does not match authenticated user.']]);
                }

                $attendance = $this->scan($record, $user);
                $results[] = ['success' => true, 'attendance_id' => $attendance->id, 'event_id' => $record['event_id'] ?? null];
            } catch (\Exception $e) {
                $results[] = ['success' => false, 'event_id' => $record['event_id'] ?? null, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function history(User $user, int $limit = 50, ?int $orgId = null)
    {
        if ($orgId) {
            return $this->repository->getUserHistoryByOrg($user, $orgId, $limit);
        }

        return $this->repository->getUserHistory($user, $limit);
    }

    public function getEventExportData(Event $event): array
    {
        $configs = $event->qrConfigurations()->orderBy('id')->get();

        $attendances = Attendance::with(['user', 'qrConfiguration'])
            ->whereHas('qrConfiguration', fn ($q) => $q->where('event_id', $event->id))
            ->get();

        $rows = [];
        $configIds = $configs->pluck('id')->all();

        foreach ($attendances as $attendance) {
            $user = $attendance->user;
            if (! $user) {
                continue;
            }

            if (! isset($rows[$user->id])) {
                $rows[$user->id] = [
                    'student_number' => $user->student_number,
                    'name' => $user->name,
                    'times' => array_fill_keys($configIds, null),
                ];
            }

            $rows[$user->id]['times'][$attendance->qr_configuration_id] = $attendance->scanned_at?->setTimezone('Asia/Manila')->format('h:i A');
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return [
            'event' => [
                'title' => $event->title,
                'event_date' => $event->event_date?->format('Y-m-d'),
                'time_from' => $event->time_from,
                'time_to' => $event->time_to,
            ],
            'qr_configs' => $configs->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'valid_from' => $c->valid_from,
                'valid_until' => $c->valid_until,
            ])->values()->all(),
            'rows' => $rows,
        ];
    }

    public function getStudentEventsByOrg(User $user, int $orgId): array
    {
        $attendances = Attendance::with(['qrConfiguration.event.organization'])
            ->where('user_id', $user->id)
            ->whereHas('event', fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('scanned_at', 'desc')
            ->get()
            ->filter(fn ($a) => $a->qrConfiguration !== null);

        return $attendances
            ->groupBy(fn ($a) => $a->qrConfiguration->event_id)
            ->map(function ($group) {
                $event = $group->first()->qrConfiguration->event;
                $totalQrConfigs = $event->qrConfigurations()->count();
                $attendances = $group->map(fn ($a) => [
                    'id' => $a->id,
                    'qr_configuration_id' => $a->qr_configuration_id,
                    'type' => $a->qrConfiguration->type,
                    'scanned_at' => $a->scanned_at?->toIso8601String(),
                    'synced_at' => $a->synced_at?->toIso8601String(),
                ])->values();

                return [
                    'event' => [
                        'id' => $event->uuid,
                        'title' => $event->title,
                        'event_date' => $event->event_date?->toDateString(),
                        'venue' => $event->venue,
                        'status' => $event->status,
                        'organization' => $event->organization
                            ? [
                                'id' => $event->organization->id,
                                'name' => $event->organization->name,
                                'type' => $event->organization->type,
                            ]
                            : null,
                    ],
                    'attended_count' => $group->count(),
                    'total_qr_configs' => $totalQrConfigs,
                    'complete' => $group->count() >= $totalQrConfigs,
                    'attendances' => $attendances,
                ];
            })
            ->values()
            ->all();
    }

    public function getStudentStats(User $user): array
    {
        $ssc = Organization::ssc()->active()->first();

        $instituteId = $user->institute_id;
        $programId = $user->program_id;

        $enrollment = $user->currentEnrollment();
        if ($enrollment) {
            $instituteId = $enrollment->institute_id ?? $instituteId;
            $programId = $enrollment->program_id ?? $programId;
        }

        $isc = null;
        if ($instituteId) {
            $isc = Organization::isc()->active()->where('institute_id', $instituteId)->first();
        }

        $sro = null;
        if ($programId) {
            $sro = Organization::sro()->active()->where('program_id', $programId)->first();
        }

        $orgs = array_values(array_filter([$ssc, $isc, $sro]));

        $attendances = Attendance::with('qrConfiguration')
            ->where('user_id', $user->id)
            ->get();

        return array_map(function (Organization $org) use ($attendances) {
            $orgEventIds = Event::where('organization_id', $org->id)->pluck('id');
            $totalAttended = 0;
            $completeAttendance = 0;

            foreach ($orgEventIds as $eventId) {
                $eventQrCount = QrConfiguration::where('event_id', $eventId)->count();
                $userScansForEvent = $attendances->filter(
                    fn ($a) => $a->qrConfiguration?->event_id === $eventId
                )->count();

                if ($userScansForEvent > 0) {
                    $totalAttended++;
                    if ($userScansForEvent >= $eventQrCount) {
                        $completeAttendance++;
                    }
                }
            }

            return [
                'id' => $org->id,
                'name' => $org->name,
                'type' => $org->type,
                'total' => $totalAttended,
                'complete' => $completeAttendance,
            ];
        }, $orgs);
    }
}
