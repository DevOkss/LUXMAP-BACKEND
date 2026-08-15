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

    private function formatDate($date): string
    {
        return $date ? $date->format('M j, Y') : 'TBA';
    }
}
