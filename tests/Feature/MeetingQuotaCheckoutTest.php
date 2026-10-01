<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingPackStatus;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingQuotaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_student_can_view_only_published_meeting_packs(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::InProgress,
        ]);

        $publishedPack = MeetingPack::factory()->create([
            'name' => '公開パック',
            'status' => MeetingPackStatus::Published,
        ]);

        $unpublishedPack = MeetingPack::factory()->create([
            'name' => '非公開パック',
            'status' => MeetingPackStatus::Draft,
        ]);

        $response = $this
            ->actingAs($student)
            ->get('/meeting-quota/checkout');

        $response
            ->assertOk()
            ->assertSee($publishedPack->name)
            ->assertDontSee($unpublishedPack->name);
    }

    public function test_unpublished_meeting_pack_cannot_be_purchased_directly(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::InProgress,
        ]);

        $unpublishedPack = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Draft,
        ]);

        $response = $this
            ->actingAs($student)
            ->post('/meeting-quota/checkout', [
                'meeting_pack_id' => $unpublishedPack->id,
            ]);

        $response->assertNotFound();
    }

    public function test_non_student_cannot_access_checkout(): void
    {
        $coach = User::factory()->create([
            'role' => 'coach',
            'status' => UserStatus::InProgress,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get('/meeting-quota/checkout');

        $response->assertForbidden();
    }

    public function test_non_in_progress_student_cannot_access_checkout(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::Graduated,
        ]);

        $response = $this
            ->actingAs($student)
            ->get('/meeting-quota/checkout');

        $response->assertForbidden();
    }
}
