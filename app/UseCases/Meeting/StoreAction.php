<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Notifications\MeetingReservedNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(
        Enrollment $enrollment,
        string $scheduledAt,
        ?string $topic,
    ): Meeting {
        return DB::transaction(function () use ($enrollment, $scheduledAt, $topic): Meeting {
            $scheduledAt = Carbon::parse($scheduledAt);
            $student = $enrollment->user;

            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot(
                $enrollment->certification,
                $scheduledAt,
            );

            $candidates = $this->availabilityService->availableCoachesForSlot(
                $enrollment->certification,
                $scheduledAt,
            );

            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);

            $meeting->update([
                'meeting_quota_transaction_id' => $transaction->id,
            ]);

            $meeting->loadMissing('coach.googleCredential');

            $credential = $meeting->coach->googleCredential;

            if ($credential !== null) {
                try {
                    $eventId = $this->googleCalendarService->createEvent(
                        $credential,
                        $meeting->scheduled_at,
                        $meeting->scheduled_at->copy()->addHour(),
                        'Certify LMS 面談',
                        $meeting->meeting_url_snapshot,
                    );

                    $meeting->update([
                        'google_calendar_event_id' => $eventId,
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $meeting->coach->notify(
                new MeetingReservedNotification($meeting),
            );

            return $meeting->fresh();
        });
    }
}
