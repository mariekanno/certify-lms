<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MeetingCanceledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Meeting $meeting,
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
            ->subject('面談がキャンセルされました')
            ->greeting($notifiable->name.'さん')
            ->line('予約されていた面談がキャンセルされました。')
            ->action(
                '面談を確認する',
                route('meetings.show', $this->meeting),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => $this->meeting->scheduled_at?->format('Y/m/d H:i').' の面談がキャンセルされました。',
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
