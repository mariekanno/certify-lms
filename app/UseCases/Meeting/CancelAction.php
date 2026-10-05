<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Services\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor): Meeting {
            $locked = Meeting::query()
                ->whereKey($meeting->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);

            $locked->loadMissing([
                'student',
                'coach.googleCredential',
            ]);

            $credential = $locked->coach->googleCredential;

            if (
                $credential !== null
                && $locked->google_calendar_event_id !== null
            ) {
                try {
                    $this->googleCalendarService->deleteEvent(
                        $credential,
                        $locked->google_calendar_event_id,
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $recipient = $actor->id === $locked->student_id
                ? $locked->coach
                : $locked->student;

            $recipient->notify(
                new MeetingCanceledNotification($locked),
            );

            return $locked->fresh();
        });
    }
}
