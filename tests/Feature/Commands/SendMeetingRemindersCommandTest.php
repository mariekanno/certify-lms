<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_eve_reminder_is_sent_to_student_and_coach(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 15:00:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput('前日リマインダーを送信しました。')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        $this->assertNotNull(
            $meeting->fresh()->eve_reminded_at,
        );

        $this->assertNull(
            $meeting->fresh()->one_hour_before_reminded_at,
        );
    }

    public function test_eve_reminder_only_targets_meetings_on_the_next_calendar_day(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $target = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 23:59:00'),
            ]);

        $notTarget = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-28 00:01:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertExitCode(0);

        $this->assertNotNull(
            $target->fresh()->eve_reminded_at,
        );

        $this->assertNull(
            $notTarget->fresh()->eve_reminded_at,
        );
    }

    public function test_one_hour_before_reminder_uses_five_minute_window(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $target = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-26 11:03:00'),
            ]);

        $outsideWindow = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-26 11:06:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'one_hour_before'],
        )
            ->expectsOutput('1時間前リマインダーを送信しました。')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );

        $this->assertNotNull(
            $target->fresh()->one_hour_before_reminded_at,
        );

        $this->assertNull(
            $outsideWindow->fresh()->one_hour_before_reminded_at,
        );
    }

    public function test_canceled_and_completed_meetings_are_not_targeted(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $canceled = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 12:00:00'),
                'status' => MeetingStatus::Canceled,
            ]);

        $completed = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 13:00:00'),
                'status' => MeetingStatus::Completed,
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput('対象となる面談はありません。')
            ->assertExitCode(0);

        Notification::assertNothingSent();

        $this->assertNull(
            $canceled->fresh()->eve_reminded_at,
        );

        $this->assertNull(
            $completed->fresh()->eve_reminded_at,
        );
    }

    public function test_reminder_is_not_sent_twice(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 12:00:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertExitCode(0);

        $firstRemindedAt = $meeting->fresh()->eve_reminded_at;

        Notification::fake();

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput('送信済みのため重複配信しない')
            ->assertExitCode(0);

        Notification::assertNothingSent();

        $this->assertTrue(
            $firstRemindedAt->equalTo(
                $meeting->fresh()->eve_reminded_at,
            )
        );
    }

    public function test_eve_and_one_hour_before_are_managed_separately(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 11:00:00'),
                'eve_reminded_at' => now(),
                'one_hour_before_reminded_at' => null,
            ]);

        Carbon::setTestNow('2026-09-27 10:00:00');

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'one_hour_before'],
        )->assertExitCode(0);

        $meeting->refresh();

        $this->assertNotNull($meeting->eve_reminded_at);
        $this->assertNotNull($meeting->one_hour_before_reminded_at);
    }

    public function test_inactive_participant_does_not_receive_reminder(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::Graduated,
        ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 12:00:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertExitCode(0);

        Notification::assertNotSentTo(
            $student,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $coach,
            MeetingReminderNotification::class,
        );
    }

    public function test_admin_does_not_receive_reminder(): void
    {
        Notification::fake();

        Carbon::setTestNow('2026-09-26 10:00:00');

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($admin)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 12:00:00'),
            ]);

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )->assertExitCode(0);

        Notification::assertNotSentTo(
            $admin,
            MeetingReminderNotification::class,
        );

        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
        );
    }

    public function test_invalid_window_returns_failure(): void
    {
        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'invalid'],
        )
            ->expectsOutput(
                'window には eve または one_hour_before を指定してください。'
            )
            ->assertExitCode(1);
    }

    public function test_no_target_meetings_returns_success(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput('対象となる面談はありません。')
            ->assertExitCode(0);
    }

    public function test_failed_notification_is_not_marked_as_sent(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');

        $coach = User::factory()->coach()->create([
            'status' => UserStatus::InProgress,
        ]);

        $student = User::factory()->student()->create([
            'status' => UserStatus::InProgress,
        ]);

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => Carbon::parse('2026-09-27 12:00:00'),
            ]);

        $this->mock(
            Dispatcher::class,
            function ($mock): void {
                $mock->shouldReceive('send')
                    ->andThrow(new RuntimeException('notification failed'));
            },
        );

        $this->artisan(
            'notifications:send-meeting-reminders',
            ['--window' => 'eve'],
        )
            ->expectsOutput('通知送信に失敗しました。')
            ->assertExitCode(1);

        $this->assertNull(
            $meeting->fresh()->eve_reminded_at,
        );
    }
}
