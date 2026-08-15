<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PenaltyFee;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes a student's outstanding penalties on demand. Never persists
 * penalty obligations to the payments table — payments only holds actual
 * transactions (payment or exemption). An obligation is considered settled
 * when a completed or exempted payment record exists for that event.
 */
class PenaltyService
{
    public function __construct(
        private EligibilityService $eligibility,
        private AcademicTermService $terms
    ) {}

    /**
     * Outstanding penalties for a student:
     *
     *   Outstanding Penalty = Total Missing Required QR Attendances
     *                         x Latest Organization Penalty Amount
     *
     * For each Published/Completed event the student is required to attend,
     * every required qr_configuration without an attendance record counts as
     * one absence. Draft and ongoing events are never penalized. Events
     * already settled (completed or exempted payment) are skipped.
     */
    public function studentOutstanding(
        User $user,
        ?\App\Models\AcademicTerm $term = null,
        ?Organization $scope = null
    ): Collection {
        $currentTerm = $term ?? $this->terms->current();

        $orgs = $scope
            ? collect([$scope])->filter()
            : $this->eligibility->userOrganizationsForTerm($user, $currentTerm);

        if ($orgs->isEmpty()) {
            return new Collection();
        }

        $events = Event::with(['organization', 'qrConfigurations'])
            ->whereIn('organization_id', $orgs->pluck('id'))
            ->whereIn('status', ['published', 'completed'])
            ->when($currentTerm, fn ($q) => $q->where('academic_term_id', $currentTerm->id))
            ->where(fn ($q) => $q
                ->where('status', 'completed')
                ->orWhere(fn ($q2) => $q2
                    ->where('status', 'published')
                    ->ended()))
            ->get()
            ->filter(fn (Event $event) => $this->eligibility->studentIdsForEvent($event)
                ->contains($user->id));

        if ($events->isEmpty()) {
            return new Collection();
        }

        $settledEventIds = Payment::isPenalty()
            ->where('user_id', $user->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->where(function ($q) {
                $q->where('status', Payment::STATUS_PAID)
                    ->orWhere('isExempted', true);
            })
            ->pluck('event_id')
            ->map(fn ($id) => (int) $id);

        $obligations = new Collection();

        foreach ($events as $event) {
            if ($settledEventIds->contains($event->id)) {
                continue;
            }

            $qrIds = $event->qrConfigurations->pluck('id')
                ->map(fn ($id) => (int) $id);

            if ($qrIds->isEmpty()) {
                continue;
            }

            $scanned = Attendance::whereIn('qr_configuration_id', $qrIds)
                ->where('user_id', $user->id)
                ->pluck('qr_configuration_id')
                ->map(fn ($id) => (int) $id)
                ->unique();

            $missing = $qrIds->diff($scanned)->values();

            if ($missing->isEmpty()) {
                continue;
            }

            $unit = (float) PenaltyFee::currentAmountFor($event->organization_id);

            $obligations->push([
                'event_id' => $event->id,
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_date' => $event->event_date?->toDateString(),
                    'organization' => [
                        'id' => $event->organization->id,
                        'name' => $event->organization->name,
                        'type' => $event->organization->type,
                    ],
                ],
                'absences' => $missing->count(),
                'missing_qr_configurations' => $event->qrConfigurations
                    ->whereIn('id', $missing)
                    ->values()
                    ->map(fn ($qr) => [
                        'id' => $qr->id,
                        'type' => $qr->type,
                        'valid_from' => $qr->valid_from,
                        'valid_until' => $qr->valid_until,
                    ]),
                'unit_amount' => $unit,
                'amount' => round($missing->count() * $unit, 2),
                'status' => 'pending',
                'isExempted' => false,
            ]);
        }

        return $obligations;
    }
}
