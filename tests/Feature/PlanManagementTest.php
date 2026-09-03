<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();
    }

    public function test_student_cannot_view_plan_index(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get('/admin/plans');

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_plan_index(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get('/admin/plans');

        $response->assertForbidden();
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => 'テストプラン',
                'description' => 'テスト用です',
                'duration_days' => 90,
                'default_meeting_quota' => 12,
                'sort_order' => 10,
            ]);

        $plan = Plan::query()->where('name', 'テストプラン')->firstOrFail();

        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();

        $plan = Plan::factory()
            ->for($admin, 'createdBy')
            ->for($admin, 'updatedBy')
            ->draft()
            ->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => '更新後プラン',
                'description' => '更新しました',
                'duration_days' => 180,
                'default_meeting_quota' => 24,
                'sort_order' => 20,
            ]);

        $plan->refresh();

        $this->assertSame('更新後プラン', $plan->name);
        $this->assertSame(180, $plan->duration_days);
        $this->assertSame(24, $plan->default_meeting_quota);
        $this->assertSame($admin->id, $plan->updated_by_user_id);

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');
    }

    public function test_admin_can_publish_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.publish', $plan));

        $this->assertSame(
            PlanStatus::Published,
            $plan->refresh()->status
        );

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');
    }

    public function test_admin_can_archive_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.archive', $plan));

        $this->assertSame(
            PlanStatus::Archived,
            $plan->refresh()->status
        );

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');
    }

    public function test_admin_can_unarchive_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.unarchive', $plan));

        $this->assertSame(
            PlanStatus::Draft,
            $plan->refresh()->status
        );

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('success');
    }

    public function test_published_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->delete(route('admin.plans.destroy', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_draft_plan_without_users_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan));

        $response
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_published_plan_cannot_be_published_again(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->post(route('admin.plans.publish', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertSame(
            PlanStatus::Published,
            $plan->refresh()->status
        );
    }

    public function test_draft_plan_cannot_be_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->post(route('admin.plans.archive', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertSame(
            PlanStatus::Draft,
            $plan->refresh()->status
        );
    }

    public function test_published_plan_cannot_be_unarchived(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->post(route('admin.plans.unarchive', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertSame(
            PlanStatus::Published,
            $plan->refresh()->status
        );
    }

    public function test_archived_plan_cannot_be_published(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->post(route('admin.plans.publish', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertSame(
            PlanStatus::Archived,
            $plan->refresh()->status
        );
    }

    public function test_archived_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->delete(route('admin.plans.destroy', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_draft_plan_with_user_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        User::factory()->student()->create([
            'plan_id' => $plan->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.show', $plan))
            ->delete(route('admin.plans.destroy', $plan));

        $response
            ->assertRedirect(route('admin.plans.show', $plan))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
        ]);
    }

    public function test_plan_index_can_filter_by_keyword(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->create([
            'name' => 'ベーシックプラン',
        ]);

        Plan::factory()->create([
            'name' => 'アドバンスプラン',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'keyword' => 'ベーシック',
            ]));

        $response
            ->assertOk()
            ->assertSee('ベーシックプラン')
            ->assertDontSee('アドバンスプラン');
    }

    public function test_plan_index_can_filter_by_status(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->draft()->create([
            'name' => '下書きプラン',
        ]);

        Plan::factory()->published()->create([
            'name' => '公開プラン',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index', [
                'status' => PlanStatus::Draft->value,
            ]));

        $response
            ->assertOk()
            ->assertSee('下書きプラン')
            ->assertDontSee('公開プラン');
    }

    public function test_plan_index_is_paginated_by_15_items(): void
    {
        $admin = User::factory()->admin()->create();

        Plan::factory()->count(16)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.plans.index'));

        $response->assertOk();

        $plans = $response->viewData('plans');

        $this->assertSame(15, $plans->perPage());
        $this->assertSame(16, $plans->total());
        $this->assertCount(15, $plans->items());
    }

    public function test_store_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.plans.store'), [
                'name' => '',
                'description' => str_repeat('あ', 2001),
                'duration_days' => 0,
                'default_meeting_quota' => 1001,
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors([
            'name',
            'description',
            'duration_days',
            'default_meeting_quota',
            'sort_order',
        ]);
    }

    public function test_update_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), [
                'name' => str_repeat('a', 101),
                'description' => str_repeat('あ', 2001),
                'duration_days' => 3651,
                'default_meeting_quota' => -1,
                'sort_order' => -1,
            ]);

        $response->assertSessionHasErrors([
            'name',
            'description',
            'duration_days',
            'default_meeting_quota',
            'sort_order',
        ]);
    }

    public function test_student_cannot_create_plan(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)
            ->get('/admin/plans/create');

        $response->assertForbidden();
    }

    public function test_coach_cannot_create_plan(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->get('/admin/plans/create');

        $response->assertForbidden();
    }

    public function test_student_cannot_view_plan_detail(): void
    {
        $student = User::factory()->student()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($student)
            ->get('/admin/plans/'.$plan->id);

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_plan_detail(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($coach)
            ->get('/admin/plans/'.$plan->id);

        $response->assertForbidden();
    }
}
