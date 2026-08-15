<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->list($request->user());

        return response()->json([
            'notifications' => NotificationResource::collection($notifications),
            'unread_count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->notificationService->markRead($request->user(), $id);
        if (! $notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(new NotificationResource($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllRead($request->user());

        return response()->json([
            'message' => "{$count} notifications marked as read",
            'count' => $count,
        ]);
    }

    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $user = $this->notificationService->updatePushSubscription(
            $request->user(),
            $request->only(['endpoint', 'keys']),
        );

        return response()->json([
            'message' => 'Push subscription updated successfully',
            'user' => ['id' => $user->id, 'push_subscriptions_count' => $user->pushSubscriptions->count()],
        ]);
    }

    public function removePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $this->notificationService->removePushSubscription($request->user(), $request->input('endpoint'));

        return response()->json(['message' => 'Push subscription removed successfully']);
    }
}
