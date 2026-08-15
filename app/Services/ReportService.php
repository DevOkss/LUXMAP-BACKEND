<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function attendanceReport(?int $organizationId = null, ?string $from = null, ?string $to = null): array
    {
        $query = Attendance::query()
            ->join('qr_configurations', 'attendances.qr_configuration_id', '=', 'qr_configurations.id')
            ->join('events', 'qr_configurations.event_id', '=', 'events.id')
            ->selectRaw("
                DATE(attendances.scanned_at) as date,
                events.organization_id,
                COUNT(*) as total_attendances,
                COUNT(DISTINCT attendances.user_id) as unique_students
            ")
            ->when($organizationId, fn($q) => $q->where('events.organization_id', $organizationId))
            ->when($from, fn($q) => $q->whereDate('attendances.scanned_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('attendances.scanned_at', '<=', $to))
            ->groupBy('date', 'events.organization_id')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'data' => $query,
            'totals' => [
                'total_attendances' => $query->sum('total_attendances'),
                'unique_students' => $query->sum('unique_students'),
            ],
        ];
    }

    public function financialReport(?int $organizationId = null, ?string $from = null, ?string $to = null): array
    {
        $query = Payment::query()
            ->selectRaw("
                DATE(paid_at) as date,
                payment_method,
                organization_id,
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount
            ")
            ->where('status', Payment::STATUS_PAID)
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->when($from, fn($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('paid_at', '<=', $to))
            ->groupBy('date', 'payment_method', 'organization_id')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'data' => $query,
            'totals' => [
                'total_transactions' => $query->sum('total_transactions'),
                'total_amount' => (float) $query->sum('total_amount'),
            ],
        ];
    }

    public function penaltyReport(?int $organizationId = null, ?string $from = null, ?string $to = null): array
    {
        $query = Payment::query()
            ->isPenalty()
            ->selectRaw("
                DATE(COALESCE(exempted_at, paid_at, created_at)) as date,
                status,
                isExempted,
                organization_id,
                COUNT(*) as total_penalties,
                SUM(amount) as total_amount
            ")
            ->when($organizationId, fn($q) => $q->where('organization_id', $organizationId))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->groupBy('date', 'status', 'isExempted', 'organization_id')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'data' => $query,
            'totals' => [
                'total_penalties' => $query->sum('total_penalties'),
                'total_amount' => (float) $query->sum('total_amount'),
            ],
        ];
    }
}
