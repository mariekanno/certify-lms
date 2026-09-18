<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->first();

        if ($admin === null) {
            return;
        }

        $certification = Certification::query()
            ->whereHas('enrollments', function ($query): void {
                $query->where('status', EnrollmentStatus::Learning->value);
            })
            ->first();

        $student = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();

        Announcement::query()->firstOrCreate(
            [
                'title' => '全受講生へのお知らせ',
                'target_type' => AnnouncementTargetType::AllStudents->value,
            ],
            [
                'body' => '全受講生向けのお知らせです。',
                'target_certification_id' => null,
                'target_user_id' => null,
                'dispatched_count' => 0,
                'dispatched_at' => now(),
                'created_by_user_id' => $admin->id,
            ],
        );

        if ($certification !== null) {
            Announcement::query()->firstOrCreate(
                [
                    'title' => '資格指定のお知らせ',
                    'target_type' => AnnouncementTargetType::Certification->value,
                ],
                [
                    'body' => '対象資格を受講中の方へのお知らせです。',
                    'target_certification_id' => $certification->id,
                    'target_user_id' => null,
                    'dispatched_count' => 0,
                    'dispatched_at' => now(),
                    'created_by_user_id' => $admin->id,
                ],
            );
        }

        if ($student !== null) {
            Announcement::query()->firstOrCreate(
                [
                    'title' => 'ユーザー指定のお知らせ',
                    'target_type' => AnnouncementTargetType::User->value,
                ],
                [
                    'body' => '個別のお知らせです。',
                    'target_certification_id' => null,
                    'target_user_id' => $student->id,
                    'dispatched_count' => 1,
                    'dispatched_at' => now(),
                    'created_by_user_id' => $admin->id,
                ],
            );
        }
    }
}
