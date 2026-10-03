<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->formatNotification($notification));

        return response()->json([
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(
        DatabaseNotification $notification,
        Request $request,
    ): JsonResponse {
        $this->ensureOwnNotification($notification, $request);

        $notification->markAsRead();
        $notification->refresh();

        return response()->json([
            'data' => $this->formatNotification($notification),
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->count(),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return response()->json([
            'unread_count' => 0,
        ]);
    }

    private function ensureOwnNotification(
        DatabaseNotification $notification,
        Request $request,
    ): void {
        abort_unless(
            $notification->notifiable_id === $request->user()->id
            && $notification->notifiable_type === $request->user()::class,
            403,
        );
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     message: string,
     *     url: string,
     *     is_unread: bool,
     *     created_at: string|null,
     *     created_relative: string
     * }
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data)
            ? $notification->data
            : [];

        $url = $data['url'] ?? null;

        if (
            ($data['notification_type'] ?? null) === 'admin_announcement'
            || ! is_string($url)
            || $url === ''
        ) {
            $url = route('notifications.show', $notification);
        }

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? '通知',
            'message' => $data['message'] ?? ($data['body_preview'] ?? ''),
            'url' => $url,
            'is_unread' => $notification->read_at === null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_relative' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }
}
