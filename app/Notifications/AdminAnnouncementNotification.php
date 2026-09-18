<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminAnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting($notifiable->name.'さん')
            ->line('運営からのお知らせがあります。')
            ->line($this->announcement->body)
            ->action(
                '通知一覧を確認する',
                route('notifications.index'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => $this->announcement->body,
            'announcement_id' => $this->announcement->id,
            'url' => null,
        ];
    }
}
