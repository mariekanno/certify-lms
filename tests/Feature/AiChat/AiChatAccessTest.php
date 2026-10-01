<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_own_conversation(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertOk();
    }

    public function test_other_student_cannot_view_conversation(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->get(route('ai-chat.conversations.show', $conversation));

        $response->assertForbidden();
    }

    public function test_other_student_cannot_update_conversation(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
            'title' => '変更前',
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->patch(
                route('ai-chat.conversations.update', $conversation),
                [
                    'title' => '変更後',
                ],
            );

        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '変更前',
        ]);
    }

    public function test_other_student_cannot_delete_conversation(): void
    {
        $owner = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $otherStudent = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this
            ->actingAs($otherStudent)
            ->delete(route('ai-chat.conversations.destroy', $conversation));

        $response->assertForbidden();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_coach_cannot_access_ai_chat(): void
    {
        $coach = User::factory()
            ->coach()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($coach)
            ->get(route('ai-chat.index'));

        $response->assertForbidden();
    }

    public function test_graduated_student_cannot_access_ai_chat(): void
    {
        $student = User::factory()
            ->student()
            ->graduated()
            ->create();

        $response = $this
            ->actingAs($student)
            ->get(route('ai-chat.index'));

        $response->assertForbidden();
    }
}
