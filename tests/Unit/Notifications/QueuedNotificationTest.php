<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\AdminAnnouncementNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReminderNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class QueuedNotificationTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function notificationProvider(): array
    {
        return [
            'admin announcement' => [AdminAnnouncementNotification::class],
            'chat message' => [ChatMessageReceivedNotification::class],
            'meeting canceled' => [MeetingCanceledNotification::class],
            'meeting reminder' => [MeetingReminderNotification::class],
            'meeting reserved' => [MeetingReservedNotification::class],
            'qa reply' => [QaReplyReceivedNotification::class],
        ];
    }

    #[DataProvider('notificationProvider')]
    public function test_notification_is_queued(string $notificationClass): void
    {
        $this->assertTrue(
            is_subclass_of($notificationClass, ShouldQueue::class),
        );
    }
}
