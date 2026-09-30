<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * 担当コーチ集合の面談可能時間枠を 60 分単位で展開し、空きスロットを集計する Service。
 *
 * 受講生の予約画面が「該当資格の担当コーチ全員の有効枠 Union」を 1 日単位で取得し、
 * 既存予約済時刻と Google Calendar の予定あり時間を除外して各スロットの
 * 「予約可能なコーチ数」を返す。受講生にコーチ個別は提示せず、
 * 予約確定時にコーチを自動割当する。
 */
final class MeetingAvailabilityService
{
    /**
     * 同一リクエスト内で取得済みの Google Calendar の予定あり時間を保持する。
     *
     * @var array<string, array<int, array{start: string, end: string}>>
     */
    private array $googleBusyCache = [];

    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * 指定 Certification の担当コーチ集合について、指定日 1 日分の 60 分単位空きスロットを返す。
     *
     * LMS 内の既存予約に加え、Google Calendar 連携済みコーチについては
     * Google Calendar の予定あり時間と重複するスロットも除外する。
     *
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function slotsForCertification(
        Certification $certification,
        Carbon $date,
    ): Collection {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $dayOfWeek = $date->dayOfWeek;

        $coaches = $certification->coaches()
            ->with('googleCredential')
            ->get();

        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachesById = $coaches->keyBy('id');
        $coachIds = $coaches->pluck('id')->all();

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereIn('status', [
                MeetingStatus::Reserved->value,
                MeetingStatus::Completed->value,
            ])
            ->get(['coach_id', 'scheduled_at']);

        // 予約済スロットを (coach_id => Set<H:i>) で索引化
        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(
                fn ($rows) => $rows
                    ->map(
                        fn (Meeting $meeting) => $meeting->scheduled_at->format('H:i')
                    )
                    ->all()
            );

        /** @var array<string, int> $slotCounts スロット開始時刻(H:i) → available coach 数 */
        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse(
                $date->format('Y-m-d').' '.$availability->start_time
            );

            $end = Carbon::parse(
                $date->format('Y-m-d').' '.$availability->end_time
            );

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $coachId = $availability->coach_id;
                $booked = $bookedByCoach[$coachId] ?? [];
                $coach = $coachesById->get($coachId);
                $slotEnd = $slot->copy()->addHour();

                if (
                    ! in_array($slotKey, $booked, true)
                    && $coach instanceof User
                    && ! $this->hasGoogleConflict($coach, $slot, $slotEnd)
                ) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)
            ->map(function (int $count, string $time) use ($date) {
                $start = Carbon::parse(
                    $date->format('Y-m-d').' '.$time
                );

                return [
                    'slot_start' => $start,
                    'slot_end' => $start->copy()->addHour(),
                    'available_coach_count' => $count,
                ];
            })
            ->values();
    }

    /**
     * 指定 scheduled_at に予約可能な担当コーチ集合を返す。
     *
     * 有効な availability 枠を持ち、LMS 内の既存予約および
     * Google Calendar の予定あり時間と重複しないコーチのみを返す。
     *
     * @return Collection<int, User>
     */
    public function availableCoachesForSlot(
        Certification $certification,
        Carbon $scheduledAt,
    ): Collection {
        $slotEnd = $scheduledAt->copy()->addHour();
        $startTime = $scheduledAt->format('H:i:s');
        $endTime = $slotEnd->format('H:i:s');

        return $certification->coaches()
            ->with('googleCredential')
            ->whereHas(
                'coachAvailabilities',
                function ($query) use (
                    $scheduledAt,
                    $startTime,
                    $endTime,
                ) {
                    $query->where('day_of_week', $scheduledAt->dayOfWeek)
                        ->where('is_active', true)
                        ->where('start_time', '<=', $startTime)
                        ->where('end_time', '>=', $endTime);
                }
            )
            ->whereDoesntHave(
                'meetingsAsCoach',
                function ($query) use ($scheduledAt) {
                    $query->where('scheduled_at', $scheduledAt)
                        ->whereIn('status', [
                            MeetingStatus::Reserved->value,
                            MeetingStatus::Completed->value,
                        ]);
                }
            )
            ->get()
            ->reject(
                fn (User $coach) => $this->hasGoogleConflict(
                    $coach,
                    $scheduledAt,
                    $slotEnd,
                )
            )
            ->values();
    }

    /**
     * 指定 scheduled_at が certification 担当コーチ集合の有効枠内かを検証する。
     * LMS 内の既存予約または Google Calendar の予定あり時間と重複する場合も予約不可とする。
     * 枠外なら MeetingOutOfAvailabilityException を throw する。
     *
     * @throws MeetingOutOfAvailabilityException
     */
    public function validateSlot(
        Certification $certification,
        Carbon $scheduledAt,
    ): void {
        $slots = $this->slotsForCertification(
            $certification,
            $scheduledAt->copy()->startOfDay(),
        );

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt)
                && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }

    /**
     * 指定スロットが Google Calendar の予定あり時間と重複するかを判定する。
     *
     * Google Calendar 未連携の場合は重複なしとして扱う。
     * Google API 通信に失敗した場合も LMS 本体の予約処理を継続するため重複なしとして扱う。
     */
    private function hasGoogleConflict(
        User $coach,
        Carbon $slotStart,
        Carbon $slotEnd,
    ): bool {
        $credential = $coach->googleCredential;

        if ($credential === null) {
            return false;
        }

        $busyPeriods = $this->googleBusyPeriodsForDay(
            $coach,
            $slotStart,
        );

        foreach ($busyPeriods as $period) {
            $busyStart = Carbon::parse($period['start']);
            $busyEnd = Carbon::parse($period['end']);

            if (
                $busyStart->lessThan($slotEnd)
                && $busyEnd->greaterThan($slotStart)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 指定コーチ・指定日の Google Calendar の予定あり時間を返す。
     *
     * 同一リクエスト内では取得結果を再利用し、同じ日について
     * Google API を繰り返し呼び出さない。
     * Google API 通信に失敗した場合は空配列をキャッシュし、
     * LMS 本体の空き判定を継続する。
     *
     * @return array<int, array{start: string, end: string}>
     */
    private function googleBusyPeriodsForDay(
        User $coach,
        Carbon $date,
    ): array {
        $credential = $coach->googleCredential;

        if ($credential === null) {
            return [];
        }

        $cacheKey = $credential->id.':'.$date->toDateString();

        if (array_key_exists($cacheKey, $this->googleBusyCache)) {
            return $this->googleBusyCache[$cacheKey];
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->addDay()->startOfDay();

        try {
            $busyPeriods = $this->googleCalendarService->busyPeriods(
                $credential,
                $start,
                $end,
            );
        } catch (Throwable $e) {
            report($e);

            $busyPeriods = [];
        }

        return $this->googleBusyCache[$cacheKey] = $busyPeriods;
    }
}
