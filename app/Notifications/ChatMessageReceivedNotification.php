<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ChatMessageReceivedNotification extends Notification implements ShouldQueue
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
        private readonly ChatRoom $room,
        private readonly ChatMessage $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (
            ! $notifiable instanceof User
            || $notifiable->status !== UserStatus::InProgress
            || $notifiable->role === UserRole::Admin
        ) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('新しいチャットメッセージが届きました')
            ->greeting($notifiable->name.'さん')
            ->line('新しいチャットメッセージが届きました。')
            ->action(
                'チャットを確認する',
                route('chat.show', $this->room),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'chat_message_received',
            'title' => '新しいチャットメッセージが届きました',
            'message' => $this->message->body,
            'url' => route('chat.show', $this->room),
        ];
    }
}
