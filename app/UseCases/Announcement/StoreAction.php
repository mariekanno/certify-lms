<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\DispatchAdminAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(array $validated, User $admin): Announcement
    {
        return DB::transaction(function () use ($validated, $admin): Announcement {
            $targetType = AnnouncementTargetType::from(
                $validated['target_type'],
            );

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType->value,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? $validated['target_certification_id']
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? $validated['target_user_id']
                    : null,
                'dispatched_count' => 0,
                'dispatched_at' => null,
                'created_by_user_id' => $admin->id,
            ]);

            $recipientCount = $this->recipientsQuery(
                $targetType,
                $validated,
            )->count();

            $announcement->update([
                'dispatched_count' => $recipientCount,
                'dispatched_at' => now(),
            ]);

            DispatchAdminAnnouncementNotifications::dispatch($announcement);

            return $announcement->fresh([
                'targetCertification',
                'targetUser',
                'createdBy',
            ]) ?? $announcement;
        });
    }

    /**
     * @param array<string, mixed> $validated
     *
     * @return Builder<User>
     */
    private function recipientsQuery(
        AnnouncementTargetType $targetType,
        array $validated,
    ): Builder {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        match ($targetType) {
            AnnouncementTargetType::AllStudents => null,

            AnnouncementTargetType::Certification => $query->whereHas(
                'enrollments',
                function (Builder $query) use ($validated): void {
                    $query
                        ->where(
                            'certification_id',
                            $validated['target_certification_id'],
                        )
                        ->where(
                            'status',
                            EnrollmentStatus::Learning->value,
                        );
                },
            ),

            AnnouncementTargetType::User => $query->where(
                'id',
                $validated['target_user_id'],
            ),
        };

        return $query->distinct();
    }
}
