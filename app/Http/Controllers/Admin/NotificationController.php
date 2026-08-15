<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $notifications = $this->notificationService->list($request->user(), 100);

        return Inertia::render('admin/notifications/Index', [
            'notifications' => $notifications->map(fn($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body' => $n->data['body'] ?? '',
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
                'is_read' => !is_null($n->read_at),
            ]),
            'unread_count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    public function markRead(string $id, Request $request)
    {
        $this->notificationService->markRead($request->user(), $id);

        return redirect()->back();
    }

    public function markAllRead(Request $request)
    {
        $this->notificationService->markAllRead($request->user());

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
