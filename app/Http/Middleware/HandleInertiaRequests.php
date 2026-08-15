<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Services\NotificationService;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        $permissions = $user
            ? app(PermissionRegistry::class)->permissionsFor($user)
            : null;

        $currentOrganization = null;

        $notifications = null;

        if ($user) {
            $currentOrganization = Organization::find($request->session()->get('current_organization_id'));
            $notifications = app(NotificationService::class)->list($user, 5);
        }

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'permissions' => $permissions,
            'current_organization' => $currentOrganization?->only(['id', 'name', 'code', 'type']),
            'notifications' => $notifications
                ? [
                    'unread_count' => app(NotificationService::class)->unreadCount($user),
                    'recent' => $notifications->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? 'Notification',
                        'body' => $n->data['body'] ?? '',
                        'created_at' => $n->created_at,
                        'is_read' => ! is_null($n->read_at),
                    ])->values(),
                ]
                : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }
}
