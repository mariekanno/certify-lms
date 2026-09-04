<?php

declare(strict_types=1);

namespace Tests\Feature\EnrollmentNote;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_create_note(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $response = $this
            ->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '次回面談で進捗を確認する。',
            ]);

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを追加しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '次回面談で進捗を確認する。',
        ]);
    }

    public function test_unassigned_coach_cannot_create_note(): void
    {
        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $enrollment = Enrollment::factory()->create();

        $response = $this
            ->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '担当外のメモ',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_notes', [
            'user_id' => $coach->id,
            'body' => '担当外のメモ',
        ]);
    }

    public function test_student_cannot_create_note(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $enrollment = Enrollment::factory()->create();

        $response = $this
            ->actingAs($student)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '受講生からのメモ',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('enrollment_notes', [
            'user_id' => $student->id,
            'body' => '受講生からのメモ',
        ]);
    }

    public function test_coach_can_update_own_note(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '変更前',
        ]);

        $response = $this
            ->actingAs($coach)
            ->patch(route('enrollment-notes.update', $note), [
                'body' => '変更後',
            ]);

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを更新しました。');

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '変更後',
        ]);
    }

    public function test_coach_cannot_update_another_coachs_note(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $otherCoach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $enrollment->certification->coaches()->attach($otherCoach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $otherCoach->id,
            'body' => '他コーチのメモ',
        ]);

        $response = $this
            ->actingAs($coach)
            ->patch(route('enrollment-notes.update', $note), [
                'body' => '勝手に変更',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '他コーチのメモ',
        ]);
    }

    public function test_coach_can_delete_own_note(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '削除対象',
        ]);

        $response = $this
            ->actingAs($coach)
            ->delete(route('enrollment-notes.destroy', $note));

        $response
            ->assertRedirect(route('enrollments.show', $enrollment))
            ->assertSessionHas('success', 'メモを削除しました。');

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_coach_cannot_delete_another_coachs_note(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $otherCoach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $enrollment->certification->coaches()->attach($otherCoach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $otherCoach->id,
            'body' => '他コーチのメモ',
        ]);

        $response = $this
            ->actingAs($coach)
            ->delete(route('enrollment-notes.destroy', $note));

        $response->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_admin_can_update_any_note(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $enrollment = Enrollment::factory()->create();

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '変更前',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('enrollment-notes.update', $note), [
                'body' => '管理者が更新',
            ]);

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '管理者が更新',
        ]);
    }

    public function test_admin_can_delete_any_note(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $enrollment = Enrollment::factory()->create();

        $note = EnrollmentNote::query()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $coach->id,
            'body' => '削除対象',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('enrollment-notes.destroy', $note));

        $response->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_body_is_required(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $response = $this
            ->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => '',
            ]);

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_body_must_not_exceed_2000_characters(): void
    {
        [$coach, $enrollment] = $this->createAssignedCoachAndEnrollment();

        $response = $this
            ->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), [
                'body' => str_repeat('あ', 2001),
            ]);

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    private function createAssignedCoachAndEnrollment(): array
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $coach = User::factory()->create([
            'role' => UserRole::Coach,
        ]);

        $certification = Certification::factory()->create();

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $enrollment = Enrollment::factory()->create([
            'certification_id' => $certification->id,
        ]);

        return [$coach, $enrollment];
    }
}
