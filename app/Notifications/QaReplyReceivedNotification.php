<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class QaReplyReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly QaThread $thread,
        private readonly QaReply $reply,
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
            ->subject('質問に回答が届きました')
            ->greeting($notifiable->name.'さん')
            ->line('投稿した質問に新しい回答が届きました。')
            ->action(
                '回答を確認する',
                route('qa-board.show', $this->thread),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が届きました',
            'message' => $this->reply->body,
            'url' => route('qa-board.show', $this->thread),
        ];
    }
}
