<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_creates_reserved_meeting_and_consumes_quota(): void
    {
        $student = User::factory()->student()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $result = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            '相談したい',
        );

        $this->assertSame(MeetingStatus::Reserved, $result->status);
        $this->assertSame($student->id, $result->student_id);
        $this->assertSame($coach->id, $result->coach_id);
        $this->assertSame($enrollment->id, $result->enrollment_id);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $result->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
        ]);
    }

    public function test_succeeds_when_google_calendar_event_creation_fails(): void
    {
        $student = User::factory()->student()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        GoogleCredential::query()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $mock = $this->mock(GoogleCalendarService::class);

        $mock->shouldReceive('busyPeriods')
            ->andReturn([]);

        $mock->shouldReceive('createEvent')
            ->once()
            ->andThrow(new RuntimeException('Google Calendar API failed.'));

        $result = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            '相談したい',
        );

        $this->assertSame(MeetingStatus::Reserved, $result->status);
        $this->assertNull($result->google_calendar_event_id);
    }

    public function test_saves_google_calendar_event_id_when_creation_succeeds(): void
    {
        $student = User::factory()->student()->create([
            'max_meetings' => 3,
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();

        $this->attachCoach($certification, $coach, $admin);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        GoogleCredential::query()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $scheduledAt = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->setTime(10, 0);

        $mock = $this->mock(GoogleCalendarService::class);

        $mock->shouldReceive('busyPeriods')
            ->andReturn([]);

        $mock->shouldReceive('createEvent')
            ->once()
            ->withArgs(function (
                $credential,
                $start,
                $end,
                $summary,
                $description
            ) use ($coach): bool {
                return (string) $credential->user_id === (string) $coach->id
                    && $summary === 'Certify LMS 面談'
                    && $description === 'https://meet.example.com/coach-room'
                    && abs($start->diffInMinutes($end)) == 60;
            })
            ->andReturn('google-event-123');

        $result = app(StoreAction::class)(
            $enrollment,
            $scheduledAt->format('Y-m-d\TH:i:s'),
            '相談したい',
        );

        $this->assertSame(MeetingStatus::Reserved, $result->status);

        $this->assertSame(
            'google-event-123',
            $result->google_calendar_event_id,
        );

        $this->assertDatabaseHas('meetings', [
            'id' => $result->id,
            'google_calendar_event_id' => 'google-event-123',
            'meeting_url_snapshot' => 'https://meet.example.com/coach-room',
        ]);
    }
}
