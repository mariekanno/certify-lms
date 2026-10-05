<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_students_own_upcoming_meetings(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $own = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(2)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($otherStudent)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $result = app(IndexAction::class)(
            $student,
            'upcoming',
        );

        $this->assertTrue($result->contains('id', $own->id));
        $this->assertCount(1, $result);
    }

    public function test_past_filter_returns_past_meetings(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();

        $past = Meeting::factory()
            ->completed()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->subDays(2)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(2)->startOfHour(),
            ]);

        $result = app(IndexAction::class)(
            $student,
            'past',
        );

        $this->assertTrue($result->contains('id', $past->id));
        $this->assertCount(1, $result);
    }
}
