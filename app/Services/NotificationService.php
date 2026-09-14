<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Fee;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class NotificationService
{
    public function __construct(
        private WebPushService $webPushService,
        private EligibilityService $eligibility
    ) {}

    public function list(User $user, int $limit = 50): Collection
    {
        return $user->notifications()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(User $user, string $id): ?DatabaseNotification
    {
        $notification = $user->notifications()->find($id);
        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function delete(User $user, string $id): bool
    {
        return $user->notifications()->where('id', $id)->delete() > 0;
    }

    public function clearAll(User $user): int
    {
        return $user->notifications()->delete();
    }

    public function updatePushSubscription(User $user, array $subscription): User
    {
        $user->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $subscription['endpoint']],
            [
                'p256dh' => $subscription['keys']['p256dh'],
                'auth' => $subscription['keys']['auth'],
            ],
        );

        return $user->fresh();
    }

    public function removePushSubscription(User $user, string $endpoint): void
    {
        $user->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }

    /**
     * Persist a database notification and deliver a web push in one shot.
     */
    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $user->notify(new GeneralNotification($title, $body, $data));
        $this->webPushService->sendToUser($user, $title, $body, $data);
    }

    /**
     * Recipients for an org-scoped message: org members (all students with a
     * role/assignment in that org) unioned with the org's officers & heads.
     */
    public function recipientsForOrganization(Organization $organization, ?\App\Models\AcademicTerm $term = null): Collection
    {
        $memberIds = $this->organizationStudentIds($organization, $term);
        $officerIds = $organization->users()
            ->wherePivotIn('role', UserRole::officerRoles())
            ->pluck('users.id');

        $ids = $memberIds->merge($officerIds)->unique()->values();

        return User::whereIn('id', $ids)->get();
    }

    /**
     * Students covered by an organization's outreach for the given academic
     * term (defaults to the currently active term):
     * SSC covers everyone, ISC covers its institute, SRO covers its program.
     */
    private function organizationStudentIds(Organization $organization, ?\App\Models\AcademicTerm $term = null): \Illuminate\Support\Collection
    {
        return $this->eligibility->studentIds($organization, [], $term);
    }

    public function notifyFeePosted(Fee $fee): void
    {
        $recipients = $this->recipientsForOrganization($fee->organization, $fee->academicTerm);
        $amount = number_format((float) $fee->amount, 2);

        foreach ($recipients as $user) {
            $this->notifyUser(
                $user,
                'Fee posted: '.$fee->name,
                "{$fee->organization->name}: {$amount} due by ".$this->formatDate($fee->due_date),
                [
                    'type' => 'fee_posted',
                    'fee_id' => $fee->id,
                    'url' => '/fees',
                ],
            );
        }
    }

    public function notifyEventPosted(Event $event): void
    {
        $recipients = $this->recipientsForOrganization($event->organization, $event->academicTerm);

        foreach ($recipients as $user) {
            $this->notifyUser(
                $user,
                'New activity: '.$event->title,
                "{$event->organization->name} • ".$this->formatDate($event->event_date).' • '.$event->venue,
                [
                    'type' => 'event_posted',
                    'event_id' => $event->uuid,
                    'url' => '/events',
                ],
            );
        }
    }

    public function notifyFeeDue(Fee $fee): void
    {
        $recipients = $this->recipientsForOrganization($fee->organization, $fee->academicTerm);
        $amount = number_format((float) $fee->amount, 2);
        $daysLeft = (int) now()->startOfDay()->diffInDays($fee->due_date->startOfDay(), false);

        foreach ($recipients as $user) {
            $this->notifyUser(
                $user,
                'Fee due in '.max($daysLeft, 0).' day'.(max($daysLeft, 0) === 1 ? '' : 's'),
                "{$fee->name}: {$amount} due on ".$this->formatDate($fee->due_date),
                [
                    'type' => 'fee_due',
                    'fee_id' => $fee->id,
                    'url' => '/fees',
                ],
            );
        }
    }

    /**
     * Notify a student that a walk-in payment has been recorded by an officer.
     * One notification per batch (transaction), with total, fees breakdown,
     * payment date and the single receipt reference number.
     */
    public function notifyPaymentRecorded(User $student, \Illuminate\Support\Collection $payments, \App\Models\Receipt $receipt, ?string $organizationName = null): void
    {
        $total = number_format((float) $payments->sum('amount'), 2);
        $feeNames = $payments->map(function ($p) {
            if ($p->relationLoaded('fee') && $p->fee) return $p->fee->name;
            if ($p->relationLoaded('event') && $p->event) return $p->event->title;
            // Fallback when not eager loaded
            if ($p->fee_type === 'penalty' && $p->event) return $p->event->title;
            if ($p->fee) return $p->fee->name;
            return $p->fee_type === 'penalty' ? 'Penalty' : 'Fee';
        })->filter()->implode(', ');

        $date = $receipt->issued_at ? $receipt->issued_at->format('M j, Y') : now()->format('M j, Y');
        $orgLabel = $organizationName ? " for {$organizationName}" : '';
        $body = "Your payment of ₱{$total}{$orgLabel} has been recorded on {$date}. Receipt: {$receipt->receipt_number}.";
        if ($feeNames !== '') {
            $body .= " Fees: {$feeNames}.";
        }

        $this->notifyUser(
            $student,
            'Payment recorded — ₱'.$total,
            $body,
            [
                'type' => 'payment_recorded',
                'receipt_number' => $receipt->receipt_number,
                'receipt_id' => $receipt->id,
                'batch_id' => $receipt->batch_id ?? $payments->first()?->batch_id,
                'total' => (float) $payments->sum('amount'),
                'fees' => $feeNames,
                'paid_at' => $receipt->issued_at?->toIsoString() ?? now()->toIsoString(),
                'url' => '/receipts/'.$receipt->id,
            ],
        );
    }

    /**
     * Notify all super_admins that a student has submitted a program shift request.
     * The notification links to the admin shift-requests index.
     */
    public function notifyShiftRequestSubmitted(\App\Models\ShiftRequest $shift): void
    {
        $shift->loadMissing(['user', 'currentInstitute', 'currentProgram', 'requestedInstitute', 'requestedProgram']);
        $student = $shift->user;
        if (! $student) {
            return;
        }

        $current = trim(($shift->currentInstitute?->name ?? '—').' / '.($shift->currentProgram?->name ?? '—'), ' /');
        $requested = trim(($shift->requestedInstitute?->name ?? '—').' / '.($shift->requestedProgram?->name ?? '—'), ' /');
        $reason = $shift->reason ? ' Reason: '.$shift->reason : '';

        $title = 'New shift request — '.($student->name ?? 'Student');
        $body = ($student->name ?? 'A student').' ('.($student->student_number ?? '—').") requested to shift from {$current} to {$requested}.{$reason}";

        foreach ($this->superAdmins() as $admin) {
            $this->notifyUser(
                $admin,
                $title,
                $body,
                [
                    'type' => 'shift_request',
                    'shift_request_id' => $shift->id,
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'status' => $shift->status,
                    'url' => '/admin/shift-requests',
                ],
            );
        }
    }

    /**
     * Notify the student that their shift request has been reviewed (approved/rejected).
     * Links to the PWA shift page (/shift).
     */
    public function notifyShiftRequestReviewed(\App\Models\ShiftRequest $shift): void
    {
        $shift->loadMissing(['user', 'requestedInstitute', 'requestedProgram', 'currentInstitute', 'currentProgram', 'reviewedBy']);
        $student = $shift->user;
        if (! $student) {
            return;
        }

        $requested = trim(($shift->requestedInstitute?->name ?? '—').' / '.($shift->requestedProgram?->name ?? '—'), ' /');
        $isApproved = $shift->status === \App\Models\ShiftRequest::STATUS_APPROVED;
        $title = $isApproved ? 'Shift request approved' : 'Shift request rejected';
        $statusVerb = $isApproved ? 'approved' : 'rejected';
        $body = "Your request to shift to {$requested} has been {$statusVerb}.";
        if ($shift->remarks) {
            $body .= ' Remarks: '.$shift->remarks;
        }
        if ($isApproved) {
            $body .= ' Your institute/program has been updated.';
        }

        $this->notifyUser(
            $student,
            $title,
            $body,
            [
                'type' => $isApproved ? 'shift_request_approved' : 'shift_request_rejected',
                'shift_request_id' => $shift->id,
                'status' => $shift->status,
                'requested_institute' => $shift->requestedInstitute?->name,
                'requested_program' => $shift->requestedProgram?->name,
                'remarks' => $shift->remarks,
                'url' => '/shift',
            ],
        );
    }

    private function superAdmins(): Collection
    {
        $ids = \Illuminate\Support\Facades\DB::table('organization_user')
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return User::whereIn('id', $ids)->get();
    }

    private function formatDate($date): string
    {
        return $date ? $date->format('M j, Y') : 'TBA';
    }
}
