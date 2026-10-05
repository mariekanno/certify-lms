<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Meeting\FetchAvailabilityAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FetchAvailabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_available_slots_for_enrollment_certification(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        CoachAvailability::factory()
            ->forCoach($coach)
            ->onDay(1)
            ->timeRange('09:00:00', '12:00:00')
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->for($certification)
            ->learning()
            ->create();

        $date = now()
            ->startOfDay()
            ->next(Carbon::MONDAY)
            ->format('Y-m-d');

        $result = app(FetchAvailabilityAction::class)(
            $enrollment,
            $date,
        );

        $this->assertSame($date, $result['date']);
        $this->assertCount(3, $result['slots']);

        $this->assertArrayHasKey('slot_start', $result['slots'][0]);
        $this->assertArrayHasKey('slot_end', $result['slots'][0]);
        $this->assertArrayHasKey('available_coach_count', $result['slots'][0]);
    }
}
