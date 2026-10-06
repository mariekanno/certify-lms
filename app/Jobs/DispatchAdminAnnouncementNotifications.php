<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchAdminAnnouncementNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly Announcement $announcement,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(): void
    {
        $this->recipients()
            ->chunkById(100, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    $recipient->notify(
                        new AdminAnnouncementNotification($this->announcement),
                    );
                }
            });
    }

    /**
     * @return Builder<User>
     */
    private function recipients(): Builder
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        match ($this->announcement->target_type) {
            AnnouncementTargetType::AllStudents => null,

            AnnouncementTargetType::Certification => $query->whereHas(
                'enrollments',
                function (Builder $query): void {
                    $query
                        ->where(
                            'certification_id',
                            $this->announcement->target_certification_id,
                        )
                        ->where(
                            'status',
                            EnrollmentStatus::Learning->value,
                        );
                },
            ),

            AnnouncementTargetType::User => $query->where(
                'id',
                $this->announcement->target_user_id,
            ),
        };

        return $query->distinct();
    }
}
