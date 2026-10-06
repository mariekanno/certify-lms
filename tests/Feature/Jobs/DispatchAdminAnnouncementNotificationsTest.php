<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\AnnouncementTargetType;
use App\Jobs\DispatchAdminAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class DispatchAdminAnnouncementNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_notifies_only_active_students(): void
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

        $announcement = Announcement::create([
            'title' => '全受講生へのお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'dispatched_count' => 2,
            'dispatched_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        $job = new DispatchAdminAnnouncementNotifications($announcement);
        $job->handle();

        Notification::assertSentTo(
            [$firstStudent, $secondStudent],
            AdminAnnouncementNotification::class,
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

    public function test_certification_target_notifies_only_learning_students(): void
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

        $announcement = Announcement::create([
            'title' => '資格指定のお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
            'target_user_id' => null,
            'dispatched_count' => 1,
            'dispatched_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        $job = new DispatchAdminAnnouncementNotifications($announcement);
        $job->handle();

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

    public function test_user_target_notifies_only_selected_student(): void
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

        $announcement = Announcement::create([
            'title' => '個別のお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_certification_id' => null,
            'target_user_id' => $targetStudent->id,
            'dispatched_count' => 1,
            'dispatched_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        $job = new DispatchAdminAnnouncementNotifications($announcement);
        $job->handle();

        Notification::assertSentTo(
            $targetStudent,
            AdminAnnouncementNotification::class,
        );

        Notification::assertNotSentTo(
            $otherStudent,
            AdminAnnouncementNotification::class,
        );
    }
}
