<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_announcement_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.announcements.index'));

        $response
            ->assertOk()
            ->assertViewIs('announcement.management.index');
    }

    public function test_admin_can_view_announcement_create_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.announcements.create'));

        $response
            ->assertOk()
            ->assertViewIs('announcement.management.create');
    }

    public function test_student_cannot_access_announcement_management(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($student)
            ->get(route('admin.announcements.index'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_access_announcement_management(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($coach)
            ->get(route('admin.announcements.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_send_announcement_to_all_active_students(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $firstStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $secondStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '全受講生へのお知らせ',
                'body' => '全受講生向けのお知らせ本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ]);

        $announcement = Announcement::query()->latest()->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.announcements.show', $announcement),
            )
            ->assertSessionHas(
                'success',
                'お知らせを配信しました (2件)。',
            );

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => '全受講生へのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 2,
            'created_by_user_id' => $admin->id,
        ]);

        $this->assertNotNull($announcement->dispatched_at);

        Notification::assertSentTo(
            [$firstStudent, $secondStudent],
            AdminAnnouncementNotification::class,
            function (
                AdminAnnouncementNotification $notification,
                array $channels,
            ): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        Notification::assertNotSentTo(
            $graduatedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $coach,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_admin_can_send_announcement_to_learning_students_of_certification(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $otherCertification = Certification::factory()
            ->published()
            ->create();

        $learningStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $passedStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherCertificationStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        Enrollment::factory()
            ->for($learningStudent, 'user')
            ->for($certification)
            ->learning()
            ->create();

        Enrollment::factory()
            ->for($passedStudent, 'user')
            ->for($certification)
            ->passed()
            ->create();

        Enrollment::factory()
            ->for($otherCertificationStudent, 'user')
            ->for($otherCertification)
            ->learning()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '資格指定のお知らせ',
                'body' => '対象資格を受講中の方へのお知らせです。',
                'target_type' => AnnouncementTargetType::Certification->value,
                'target_certification_id' => $certification->id,
            ]);

        $announcement = Announcement::query()->latest()->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.announcements.show', $announcement),
            )
            ->assertSessionHas(
                'success',
                'お知らせを配信しました (1件)。',
            );

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame(
            $certification->id,
            $announcement->target_certification_id,
        );

        Notification::assertSentTo(
            $learningStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $passedStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherCertificationStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_admin_can_send_announcement_to_specific_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $targetStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => '個別のお知らせ',
                'body' => '個別のお知らせ本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $targetStudent->id,
            ]);

        $announcement = Announcement::query()->latest()->firstOrFail();

        $response
            ->assertRedirect(
                route('admin.announcements.show', $announcement),
            )
            ->assertSessionHas(
                'success',
                'お知らせを配信しました (1件)。',
            );

        $this->assertSame(
            $targetStudent->id,
            $announcement->target_user_id,
        );

        Notification::assertSentTo(
            $targetStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }

    public function test_certification_target_requires_certification_id(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '資格指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::Certification->value,
            ]);

        $response
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_certification_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_user_target_requires_user_id(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::User->value,
            ]);

        $response
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_user_target_rejects_non_active_student(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();

        $graduatedStudent = User::factory()
            ->student()
            ->graduated()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => 'ユーザー指定のお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $graduatedStudent->id,
            ]);

        $response
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_non_certification_target_rejects_certification_id(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()
            ->published()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '全受講生へのお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_certification_id' => $certification->id,
            ]);

        $response
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_certification_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_non_user_target_rejects_user_id(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), [
                'title' => '全受講生へのお知らせ',
                'body' => '本文です。',
                'target_type' => AnnouncementTargetType::AllStudents->value,
                'target_user_id' => $student->id,
            ]);

        $response
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);

        Notification::assertNothingSent();
    }

    public function test_recipient_can_view_own_announcement_notification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $notification = $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '運営からのお知らせ',
                'message' => 'お知らせ本文です。',
                'announcement_id' => (string) Str::ulid(),
                'url' => null,
            ],
            'read_at' => null,
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('notifications.show', $notification));

        $response
            ->assertOk()
            ->assertViewIs('notifications.show')
            ->assertSee('運営からのお知らせ')
            ->assertSee('お知らせ本文です。');

        $this->assertNotNull(
            $notification->fresh()->read_at,
        );
    }

    public function test_user_cannot_view_another_users_notification(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $notification = $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AdminAnnouncementNotification::class,
            'data' => [
                'notification_type' => 'admin_announcement',
                'title' => '他人宛のお知らせ',
                'message' => '他人宛の本文です。',
                'announcement_id' => (string) Str::ulid(),
                'url' => null,
            ],
            'read_at' => null,
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->get(route('notifications.show', $notification));

        $response->assertForbidden();

        $this->assertNull(
            $notification->fresh()->read_at,
        );
    }
}
