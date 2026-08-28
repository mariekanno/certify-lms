<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_question(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->published()->create();

        $response = $this
            ->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => '質問タイトル',
                'body' => '質問本文です。',
            ]);

        $thread = QaThread::query()->first();

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を投稿しました。');

        $this->assertDatabaseHas('qa_threads', [
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '質問タイトル',
            'body' => '質問本文です。',
        ]);
    }

    public function test_question_creation_requires_certification_title_and_body(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($student)
            ->from(route('qa-board.create'))
            ->post(route('qa-board.store'), [
                'certification_id' => '',
                'title' => '',
                'body' => '',
            ]);

        $response
            ->assertRedirect(route('qa-board.create'))
            ->assertSessionHasErrors([
                'certification_id',
                'title',
                'body',
            ]);

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_question_creation_rejects_too_long_title_and_body(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->published()->create();

        $response = $this
            ->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $certification->id,
                'title' => str_repeat('a', 201),
                'body' => str_repeat('b', 5001),
            ]);

        $response->assertSessionHasErrors([
            'title',
            'body',
        ]);

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_student_cannot_edit_another_students_question(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.edit', $thread));

        $response->assertForbidden();
    }

    public function test_student_cannot_delete_another_students_question(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('qa-board.destroy', $thread));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_student_cannot_delete_another_users_reply(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $otherStudent->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]));

        $response->assertForbidden();

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_student_cannot_view_question_for_unpublished_certification(): void
    {
        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Draft,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_coach_cannot_view_question_for_unassigned_certification(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get(route('qa-board.show', $thread));

        $response->assertForbidden();
    }

    public function test_coach_can_view_question_for_assigned_certification(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()
            ->published()
            ->create();

        $admin = User::factory()
            ->admin()
            ->create();

        $certification->coaches()->attach($coach->id, [
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get(route('qa-board.show', $thread));

        $response->assertOk();
    }

    public function test_student_can_update_own_question(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '更新後タイトル',
                'body' => '更新後本文',
            ]);

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を更新しました。');

        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後タイトル',
            'body' => '更新後本文',
        ]);
    }

    public function test_student_can_resolve_own_question(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->unresolved()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->post(route('qa-board.resolve', $thread));

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を解決済みにしました。');

        $thread->refresh();

        $this->assertSame(
            QaThreadStatus::Resolved,
            $thread->status
        );

        $this->assertNotNull($thread->resolved_at);
    }

    public function test_student_can_unresolve_own_question(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->resolved()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->post(route('qa-board.unresolve', $thread));

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を未解決に戻しました。');

        $thread->refresh();

        $this->assertSame(
            QaThreadStatus::Unresolved,
            $thread->status
        );

        $this->assertNull($thread->resolved_at);
    }

    public function test_student_can_create_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $otherStudent->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答本文です。',
            ]);

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '回答を投稿しました。');

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答本文です。',
        ]);
    }

    public function test_reply_body_is_required_and_has_max_length(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $this
            ->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '',
            ])
            ->assertSessionHasErrors('body');

        $this
            ->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('qa_replies', 0);
    }

    public function test_student_can_update_own_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), [
                'body' => '更新後の回答',
            ]);

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '回答を更新しました。');

        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答',
        ]);
    }

    public function test_student_can_delete_own_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]));

        $response
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_admin_can_view_unpublished_certification_question(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $certification = Certification::factory()->create([
            'status' => CertificationStatus::Draft,
        ]);

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread));

        $response->assertOk();
    }

    public function test_admin_can_delete_question(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread));

        $response
            ->assertRedirect(route('admin.qa-board.index'))
            ->assertSessionHas('success', '質問を削除しました。');

        $this->assertDatabaseMissing('qa_threads', [
            'id' => $thread->id,
        ]);
    }

    public function test_admin_can_delete_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]));

        $response
            ->assertRedirect(route('admin.qa-board.show', $thread))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertDatabaseMissing('qa_replies', [
            'id' => $reply->id,
        ]);
    }

    public function test_admin_cannot_create_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        $thread = QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '管理者回答',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('qa_replies', [
            'user_id' => $admin->id,
        ]);
    }

    public function test_status_filter_only_returns_matching_questions(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->unresolved()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '未解決質問',
        ]);

        QaThread::factory()->resolved()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '解決済質問',
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.index', [
                'status' => QaThreadStatus::Unresolved->value,
            ]));

        $response
            ->assertOk()
            ->assertSee('未解決質問')
            ->assertDontSee('解決済質問');
    }

    public function test_keyword_filter_searches_title_and_body(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => 'Laravelについて',
            'body' => '普通の本文',
        ]);

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '別の質問',
            'body' => 'Laravelの本文検索テスト',
        ]);

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
            'title' => '関係ない質問',
            'body' => '関係ない本文',
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.index', [
                'keyword' => 'Laravel',
            ]));

        $response
            ->assertOk()
            ->assertSee('Laravelについて')
            ->assertSee('別の質問')
            ->assertDontSee('関係ない質問');
    }

    public function test_certification_filter_only_returns_matching_questions(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $certificationA = Certification::factory()->published()->create();
        $certificationB = Certification::factory()->published()->create();

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certificationA->id,
            'title' => '資格Aの質問',
        ]);

        QaThread::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certificationB->id,
            'title' => '資格Bの質問',
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.index', [
                'certification_id' => $certificationA->id,
            ]));

        $response
            ->assertOk()
            ->assertSee('資格Aの質問')
            ->assertDontSee('資格Bの質問');
    }

    public function test_question_list_is_paginated(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        QaThread::factory()->count(16)->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->get(route('qa-board.index'));

        $response
            ->assertOk()
            ->assertViewHas('threads', function ($threads) {
                return $threads->perPage() === 15
                    && $threads->total() === 16
                    && $threads->lastPage() === 2;
            });
    }
}
