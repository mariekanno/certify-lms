<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_coachs_own_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $own = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(2)->startOfHour(),
            ]);

        Meeting::factory()
            ->reserved()
            ->forCoach($otherCoach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $result = app(IndexAsCoachAction::class)(
            $coach,
            'upcoming',
        );

        $this->assertTrue($result->contains('id', $own->id));
        $this->assertCount(1, $result);
    }

    public function test_filters_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $target = Meeting::factory()
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

        $result = app(IndexAsCoachAction::class)(
            $coach,
            'upcoming',
            $student->id,
        );

        $this->assertTrue($result->contains('id', $target->id));
        $this->assertCount(1, $result);
    }
}
