<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EnrollmentGoalManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_goal_for_own_learning_enrollment(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $response = $this
            ->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問を5年分解く',
                'description' => '毎日少しずつ進める',
                'target_date' => now()->addMonth()->toDateString(),
            ]);

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '目標を追加しました。');

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問を5年分解く',
            'description' => '毎日少しずつ進める',
        ]);
    }

    public function test_student_can_update_own_goal(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
            'title' => '変更前',
        ]);

        $response = $this
            ->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '変更後',
                'description' => '更新しました',
                'target_date' => now()->addWeeks(2)->toDateString(),
            ]);

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '目標を更新しました。');

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '変更後',
            'description' => '更新しました',
        ]);
    }

    public function test_student_can_delete_own_goal(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal));

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '目標を削除しました。');

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }

    public function test_student_can_mark_goal_as_achieved(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->unachieved()
            ->create([
                'enrollment_id' => $enrollment->id,
            ]);

        $response = $this
            ->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal));

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '目標を達成済みにしました。');

        $this->assertNotNull(
            $goal->fresh()->achieved_at,
        );
    }

    public function test_student_can_unmark_achieved_goal(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()
            ->achieved()
            ->create([
                'enrollment_id' => $enrollment->id,
            ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal));

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', '目標を未達成に戻しました。');

        $this->assertNull(
            $goal->fresh()->achieved_at,
        );
    }

    public function test_other_student_cannot_update_goal(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($owner, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '不正更新',
                'description' => null,
                'target_date' => null,
            ]);

        $response->assertForbidden();

        $this->assertNotSame(
            '不正更新',
            $goal->fresh()->title,
        );
    }

    public function test_coach_cannot_update_goal(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($coach)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => 'コーチによる更新',
                'description' => null,
                'target_date' => null,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_update_goal(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '管理者による更新',
                'description' => null,
                'target_date' => null,
            ]);

        $response->assertForbidden();
    }

    public function test_goal_cannot_be_managed_when_enrollment_is_passed(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->passed()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '変更不可',
                'description' => null,
                'target_date' => null,
            ]);

        $response->assertForbidden();
    }

    public function test_goal_validation_requires_title(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $response = $this
            ->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '',
                'description' => null,
                'target_date' => null,
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_goal_validation_rejects_too_long_title_and_description(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $response = $this
            ->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => Str::repeat('あ', 101),
                'description' => Str::repeat('い', 1001),
                'target_date' => null,
            ]);

        $response->assertSessionHasErrors([
            'title',
            'description',
        ]);
    }

    public function test_goal_validation_rejects_invalid_target_date(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $response = $this
            ->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '目標',
                'description' => null,
                'target_date' => 'invalid-date',
            ]);

        $response->assertSessionHasErrors('target_date');
    }

    public function test_goals_are_ordered_unachieved_then_target_date_then_achieved(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $later = EnrollmentGoal::factory()->unachieved()->create([
            'enrollment_id' => $enrollment->id,
            'title' => '未達成・後',
            'target_date' => now()->addDays(10)->toDateString(),
        ]);

        $achieved = EnrollmentGoal::factory()->achieved()->create([
            'enrollment_id' => $enrollment->id,
            'title' => '達成済',
            'target_date' => now()->addDay()->toDateString(),
        ]);

        $noDate = EnrollmentGoal::factory()->unachieved()->create([
            'enrollment_id' => $enrollment->id,
            'title' => '未達成・期日なし',
            'target_date' => null,
        ]);

        $earlier = EnrollmentGoal::factory()->unachieved()->create([
            'enrollment_id' => $enrollment->id,
            'title' => '未達成・先',
            'target_date' => now()->addDays(2)->toDateString(),
        ]);

        $ids = $enrollment
            ->goals()
            ->pluck('id')
            ->all();

        $this->assertSame([
            $earlier->id,
            $later->id,
            $noDate->id,
            $achieved->id,
        ], $ids);
    }

    public function test_enrollment_soft_delete_physically_deletes_goals(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $enrollment = Enrollment::factory()
            ->for($student, 'user')
            ->learning()
            ->create();

        $goal = EnrollmentGoal::factory()->create([
            'enrollment_id' => $enrollment->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('enrollments.destroy', $enrollment));

        $response->assertRedirect();

        $this->assertSoftDeleted('enrollments', [
            'id' => $enrollment->id,
        ]);

        $this->assertDatabaseMissing('enrollment_goals', [
            'id' => $goal->id,
        ]);
    }
}
