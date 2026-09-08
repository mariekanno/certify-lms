<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification(
            $user,
            title: '自分宛の通知',
        );

        $this->createNotification(
            $otherUser,
            title: '他人宛の通知',
        );

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.index'));

        $response
            ->assertOk()
            ->assertSee('自分宛の通知')
            ->assertDontSee('他人宛の通知');
    }

    public function test_unread_tab_only_displays_unread_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification(
            $user,
            title: '未読通知',
        );

        $this->createNotification(
            $user,
            title: '既読通知',
            readAt: now(),
        );

        $response = $this
            ->actingAs($user)
            ->get(route('notifications.index', ['tab' => 'unread']));

        $response
            ->assertOk()
            ->assertSee('未読通知')
            ->assertDontSee('既読通知');
    }

    public function test_user_can_mark_own_notification_as_read_and_redirect_to_related_url(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user,
            title: '関連画面あり',
            url: '/dashboard',
        );

        $response = $this
            ->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect('/dashboard');

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_notification_without_related_url_redirects_to_notification_index(): void
    {
        $user = User::factory()->create();

        $notification = $this->createNotification(
            $user,
            title: '関連画面なし',
        );

        $response = $this
            ->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('notifications.index'));

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notification = $this->createNotification(
            $otherUser,
            title: '他人宛の通知',
        );

        $response = $this
            ->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertForbidden();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $first = $this->createNotification(
            $user,
            title: '通知1',
        );

        $second = $this->createNotification(
            $user,
            title: '通知2',
        );

        $otherNotification = $this->createNotification(
            $otherUser,
            title: '他人の通知',
        );

        $response = $this
            ->actingAs($user)
            ->post(route('notifications.markAllAsRead'));

        $response
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas(
                'success',
                'すべての通知を既読にしました。',
            );

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);

        $this->assertNull(
            $otherNotification->fresh()->read_at,
        );
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $response = $this->get(route('notifications.index'));

        $response->assertRedirect();
    }

    public function test_qa_reply_post_notifies_thread_owner_by_database_and_mail(): void
    {
        Notification::fake();

        $questioner = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $replier = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $questioner->id,
        ]);

        $response = $this
            ->actingAs($replier)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => 'テスト回答です。',
            ]);

        $response->assertRedirect(
            route('qa-board.show', $thread),
        );

        Notification::assertSentTo(
            $questioner,
            QaReplyReceivedNotification::class,
            function (
                QaReplyReceivedNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );
    }

    public function test_qa_reply_post_does_not_notify_when_thread_owner_replies_to_own_thread(): void
    {
        Notification::fake();

        $questioner = User::factory()->create([
            'role' => UserRole::Student,
            'status' => UserStatus::InProgress,
        ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $questioner->id,
        ]);

        $response = $this
            ->actingAs($questioner)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '自己回答です。',
            ]);

        $response->assertRedirect(
            route('qa-board.show', $thread),
        );

        Notification::assertNothingSent();
    }

    private function createNotification(
        User $user,
        string $title,
        ?string $url = null,
        mixed $readAt = null,
    ) {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'Tests\\TestNotification',
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => $title,
                'message' => 'テスト通知です。',
                'url' => $url,
            ],
            'read_at' => $readAt,
        ]);
    }

    public function test_chat_message_notifies_other_room_members_by_database_and_mail(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
        ]);

        $enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $coach->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $room = ChatRoom::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);

        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->post(route('chat.storeMessage', $room), [
                'body' => 'チャット通知テストです。',
            ]);

        $response->assertRedirect(
            route('chat.show', $room),
        );

        Notification::assertSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
            function (
                ChatMessageReceivedNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $student,
            ChatMessageReceivedNotification::class,
        );
    }

    public function test_meeting_reservation_notifies_assigned_coach_by_database_and_mail(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create([
                'max_meetings' => 3,
            ]);

        $admin = User::factory()
            ->admin()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create([
                'meeting_url' => 'https://meet.example.com/coach-room',
            ]);

        $certification = Certification::factory()
            ->published()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $response = $this
            ->actingAs($student)
            ->post(route('meetings.store', $enrollment), [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => '通知テスト',
            ]);

        $response->assertRedirect();

        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            function (
                MeetingReservedNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $student,
            MeetingReservedNotification::class,
        );
    }

    public function test_meeting_cancellation_by_student_notifies_coach_by_database_and_mail(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $response = $this
            ->actingAs($student)
            ->post(route('meetings.cancel', $meeting));

        $response->assertRedirect(
            route('meetings.show', $meeting),
        );

        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
            function (
                MeetingCanceledNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $student,
            MeetingCanceledNotification::class,
        );
    }

    public function test_meeting_cancellation_by_coach_notifies_student_by_database_and_mail(): void
    {
        Notification::fake();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $response = $this
            ->actingAs($coach)
            ->post(route('meetings.cancel', $meeting));

        $response->assertRedirect(
            route('meetings.show', $meeting),
        );

        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
            function (
                MeetingCanceledNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $coach,
            MeetingCanceledNotification::class,
        );
    }

    public function test_notification_channels_are_empty_for_non_in_progress_user(): void
    {
        $user = User::factory()->student()->create([
            'status' => UserStatus::Graduated,
        ]);

        $thread = QaThread::factory()->create();

        $reply = $thread->replies()->create([
            'user_id' => User::factory()->student()->inProgress()->create()->id,
            'body' => 'テスト回答です。',
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        $this->assertSame([], $notification->via($user));
    }

    public function test_notification_channels_are_empty_for_admin(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $thread = QaThread::factory()->create();

        $reply = $thread->replies()->create([
            'user_id' => User::factory()->student()->inProgress()->create()->id,
            'body' => 'テスト回答です。',
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        $this->assertSame([], $notification->via($admin));
    }

    public function test_notification_channels_include_database_and_mail_for_in_progress_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $thread = QaThread::factory()->create();

        $reply = $thread->replies()->create([
            'user_id' => User::factory()->student()->inProgress()->create()->id,
            'body' => 'テスト回答です。',
        ]);

        $notification = new QaReplyReceivedNotification($thread, $reply);

        $this->assertSame(
            ['database', 'mail'],
            $notification->via($student)
        );
    }
}
