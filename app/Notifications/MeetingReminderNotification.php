<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        private readonly Meeting $meeting,
        private readonly string $window,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting($notifiable->name.'さん')
            ->line($this->message())
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
            'notification_type' => 'meeting_reminder',
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => route('meetings.show', $this->meeting),
        ];
    }

    private function title(): string
    {
        return match ($this->window) {
            'eve' => '明日の面談リマインダー',
            'one_hour_before' => '1時間後の面談リマインダー',
            default => '面談リマインダー',
        };
    }

    private function message(): string
    {
        $scheduledAt = $this->meeting->scheduled_at?->format('Y/m/d H:i');

        return match ($this->window) {
            'eve' => "{$scheduledAt} の面談は明日です。",
            'one_hour_before' => "{$scheduledAt} の面談は1時間後です。",
            default => "{$scheduledAt} の面談予定があります。",
        };
    }
}
