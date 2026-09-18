<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->string('tab')->toString();

        if (! in_array($tab, ['all', 'unread'], true)) {
            $tab = 'all';
        }

        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        $notifications = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    public function show(
        DatabaseNotification $notification,
        Request $request,
    ): View {
        $this->ensureOwnNotification(
            $notification,
            $request,
        );

        $notification->markAsRead();

        return view('notifications.show', [
            'notification' => $notification,
        ]);
    }

    public function markAsRead(
        DatabaseNotification $notification,
        Request $request,
    ): RedirectResponse {
        $this->ensureOwnNotification(
            $notification,
            $request,
        );

        $notification->markAsRead();

        if (
            ($notification->data['notification_type'] ?? null)
            === 'admin_announcement'
        ) {
            return redirect()->route(
                'notifications.show',
                $notification,
            );
        }

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
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
}
