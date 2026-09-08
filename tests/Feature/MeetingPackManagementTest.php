<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPackManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_index(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->count(3)->create();

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response->assertOk();
    }

    public function test_student_cannot_access_meeting_pack_management(): void
    {
        $student = User::factory()->student()->create();

        $response = $this
            ->actingAs($student)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }

    public function test_coach_cannot_access_meeting_pack_management(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->get(route('admin.meeting-packs.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '3 回パック',
                'description' => '追加面談3回分です。',
                'meeting_count' => 3,
                'price' => 9000,
                'stripe_price_id' => null,
                'sort_order' => 10,
            ]);

        $plan = MeetingPack::query()
            ->where('name', '3 回パック')
            ->firstOrFail();

        $response
            ->assertRedirect(route('admin.meeting-packs.show', $plan))
            ->assertSessionHas('success', '面談パックを作成しました。');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Draft->value,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => '更新後パック',
                'description' => '更新しました。',
                'meeting_count' => 5,
                'price' => 12000,
                'stripe_price_id' => null,
                'sort_order' => 20,
            ]);

        $response
            ->assertRedirect(route('admin.meeting-packs.show', $plan))
            ->assertSessionHas('success', '面談パックを更新しました。');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '更新後パック',
            'meeting_count' => 5,
            'price' => 12000,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_publish_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $plan));

        $response
            ->assertRedirect(route('admin.meeting-packs.show', $plan))
            ->assertSessionHas('success', '面談パックを公開しました。');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    public function test_admin_can_archive_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->published()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $plan));

        $response
            ->assertRedirect(route('admin.meeting-packs.show', $plan))
            ->assertSessionHas('success', '面談パックをアーカイブしました。');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Archived->value,
        ]);
    }

    public function test_admin_can_return_archived_meeting_pack_to_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->archived()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $plan));

        $response
            ->assertRedirect(route('admin.meeting-packs.show', $plan))
            ->assertSessionHas('success', '面談パックを下書きに戻しました。');

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Draft->value,
        ]);
    }

    public function test_published_meeting_pack_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->published()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertStatus(409);

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
        ]);
    }

    public function test_draft_meeting_pack_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $plan));

        $response
            ->assertRedirect(route('admin.meeting-packs.index'))
            ->assertSessionHas('success', '面談パックを削除しました。');

        $this->assertDatabaseMissing('meeting_packs', [
            'id' => $plan->id,
        ]);
    }

    public function test_published_meeting_pack_cannot_be_published_again(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->published()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $plan));

        $response->assertStatus(409);

        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'status' => MeetingPackStatus::Published->value,
        ]);
    }

    public function test_meeting_pack_index_can_be_filtered_by_keyword(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->create([
            'name' => '特別3回パック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        MeetingPack::factory()->create([
            'name' => '通常5回パック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => '特別',
            ]));

        $response
            ->assertOk()
            ->assertSee('特別3回パック')
            ->assertDontSee('通常5回パック');
    }

    public function test_meeting_pack_index_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()->published()->create([
            'name' => '公開中パック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        MeetingPack::factory()->draft()->create([
            'name' => '下書きパック',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => MeetingPackStatus::Published->value,
            ]));

        $response
            ->assertOk()
            ->assertSee('公開中パック')
            ->assertDontSee('下書きパック');
    }

    public function test_meeting_pack_index_is_paginated(): void
    {
        $admin = User::factory()->admin()->create();

        MeetingPack::factory()
            ->count(16)
            ->create([
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.meeting-packs.index'));

        $response
            ->assertOk()
            ->assertViewHas('plans', function ($plans): bool {
                return $plans->count() === 15
                    && $plans->total() === 16;
            });
    }

    public function test_store_meeting_pack_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '',
                'description' => str_repeat('あ', 2001),
                'meeting_count' => 0,
                'price' => 1000001,
                'stripe_price_id' => str_repeat('a', 256),
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors([
            'name',
            'description',
            'meeting_count',
            'price',
            'stripe_price_id',
            'sort_order',
        ]);
    }

    public function test_update_meeting_pack_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => str_repeat('a', 101),
                'description' => str_repeat('あ', 2001),
                'meeting_count' => 101,
                'price' => -1,
                'stripe_price_id' => str_repeat('a', 256),
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors([
            'name',
            'description',
            'meeting_count',
            'price',
            'stripe_price_id',
            'sort_order',
        ]);
    }

    public function test_student_cannot_create_meeting_pack(): void
    {
        $student = User::factory()->student()->create();

        $response = $this
            ->actingAs($student)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '3回パック',
                'meeting_count' => 3,
                'price' => 9000,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('meeting_packs', [
            'name' => '3回パック',
        ]);
    }

    public function test_coach_cannot_create_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '3回パック',
                'meeting_count' => 3,
                'price' => 9000,
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('meeting_packs', [
            'name' => '3回パック',
        ]);
    }

    public function test_student_cannot_view_meeting_pack_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $plan = MeetingPack::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('admin.meeting-packs.show', $plan));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_meeting_pack_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $plan = MeetingPack::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get(route('admin.meeting-packs.show', $plan));

        $response->assertForbidden();
    }
}
