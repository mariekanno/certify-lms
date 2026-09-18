<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

final class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders
                            {--window= : eve または one_hour_before}';

    protected $description = '予約済み面談の前日・1時間前リマインダーを送信する';

    public function handle(): int
    {
        $window = $this->option('window');

        if (! in_array($window, ['eve', 'one_hour_before'], true)) {
            $this->error(
                'window には eve または one_hour_before を指定してください。'
            );

            return self::FAILURE;
        }

        $meetings = $this->targetMeetings($window)->get();

        if ($meetings->isEmpty()) {
            if ($this->hasAlreadyRemindedMeetings($window)) {
                $this->info('送信済みのため重複配信しない');

                return self::SUCCESS;
            }

            $this->info('対象となる面談はありません。');

            return self::SUCCESS;
        }

        $sentCount = 0;

        try {
            foreach ($meetings as $meeting) {
                $recipients = $this->recipients($meeting);

                if ($recipients->isEmpty()) {
                    continue;
                }

                foreach ($recipients as $recipient) {
                    $recipient->notify(
                        new MeetingReminderNotification($meeting, $window)
                    );
                }

                $this->markAsReminded($meeting, $window);

                $sentCount++;
            }
        } catch (Throwable $e) {
            report($e);

            $this->error('通知送信に失敗しました。');

            return self::FAILURE;
        }

        if ($sentCount === 0) {
            $this->info('対象となる面談はありません。');

            return self::SUCCESS;
        }

        if ($window === 'eve') {
            $this->info('前日リマインダーを送信しました。');
        } else {
            $this->info('1時間前リマインダーを送信しました。');
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<Meeting>
     */
    private function targetMeetings(string $window): Builder
    {
        $query = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->with(['student', 'coach']);

        if ($window === 'eve') {
            return $query
                ->whereNull('eve_reminded_at')
                ->whereBetween('scheduled_at', [
                    now()->copy()->addDay()->startOfDay(),
                    now()->copy()->addDay()->endOfDay(),
                ]);
        }

        $from = now()->copy()->addHour();
        $to = $from->copy()->addMinutes(5);

        return $query
            ->whereNull('one_hour_before_reminded_at')
            ->where('scheduled_at', '>=', $from)
            ->where('scheduled_at', '<', $to);
    }

    private function hasAlreadyRemindedMeetings(string $window): bool
    {
        $query = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value);

        if ($window === 'eve') {
            return $query
                ->whereNotNull('eve_reminded_at')
                ->whereBetween('scheduled_at', [
                    now()->copy()->addDay()->startOfDay(),
                    now()->copy()->addDay()->endOfDay(),
                ])
                ->exists();
        }

        $from = now()->copy()->addHour();
        $to = $from->copy()->addMinutes(5);

        return $query
            ->whereNotNull('one_hour_before_reminded_at')
            ->where('scheduled_at', '>=', $from)
            ->where('scheduled_at', '<', $to)
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Meeting $meeting): Collection
    {
        return collect([
            $meeting->student,
            $meeting->coach,
        ])
            ->filter(
                fn (?User $user): bool => $user !== null
                    && $user->status === UserStatus::InProgress
                    && in_array(
                        $user->role,
                        [
                            UserRole::Student,
                            UserRole::Coach,
                        ],
                        true,
                    )
            )
            ->unique('id')
            ->values();
    }

    private function markAsReminded(
        Meeting $meeting,
        string $window,
    ): void {
        if ($window === 'eve') {
            $meeting->update([
                'eve_reminded_at' => now(),
            ]);

            return;
        }

        $meeting->update([
            'one_hour_before_reminded_at' => now(),
        ]);
    }
}
