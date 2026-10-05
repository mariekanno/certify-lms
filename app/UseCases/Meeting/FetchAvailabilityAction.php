<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;

final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    /**
     * @return array{
     *     date: string,
     *     slots: array<int, array{
     *         slot_start: string,
     *         slot_end: string,
     *         available_coach_count: int
     *     }>
     * }
     */
    public function __invoke(Enrollment $enrollment, string $date): array
    {
        $targetDate = Carbon::parse($date);

        $slots = $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $targetDate,
        );

        return [
            'date' => $targetDate->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ];
    }
}
