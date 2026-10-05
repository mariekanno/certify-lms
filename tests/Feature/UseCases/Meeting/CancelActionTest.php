<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_reserved_meeting(): void
    {
        $student = User::factory()->student()->create([
            'max_meetings' => 5,
        ]);
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        $result = app(CancelAction::class)(
            $meeting,
            $student,
        );

        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertNotNull($result->canceled_at);
        $this->assertSame($student->id, $result->canceled_by_user_id);
    }

    public function test_refunds_meeting_quota_when_canceled(): void
    {
        $student = User::factory()->student()->create([
            'max_meetings' => 5,
        ]);
        $coach = User::factory()->coach()->create();

        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forStudent($student)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
            ]);

        app(CancelAction::class)(
            $meeting,
            $student,
        );

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }
}
