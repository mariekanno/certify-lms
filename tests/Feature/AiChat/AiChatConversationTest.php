<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_reuses_existing_conversation_for_same_section(): void
    {
        config([
            'ai-chat.enabled' => true,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $section = Section::factory()
            ->published()
            ->create();

        $section->load('chapter.part');

        $enrollment = Enrollment::factory()
            ->learning()
            ->create([
                'user_id' => $student->id,
                'certification_id' => $section->chapter->part->certification_id,
            ]);

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => '既存の教材相談',
        ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.store'),
                [
                    'source' => 'widget',
                    'section_id' => $section->id,
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'conversation.id',
                $conversation->id,
            );

        $this->assertDatabaseCount(
            'ai_chat_conversations',
            1,
        );
    }

    public function test_widget_creates_new_conversation_for_section(): void
    {
        config([
            'ai-chat.enabled' => true,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $section = Section::factory()
            ->published()
            ->create();

        $section->load('chapter.part');

        $enrollment = Enrollment::factory()
            ->learning()
            ->create([
                'user_id' => $student->id,
                'certification_id' => $section->chapter->part->certification_id,
            ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.store'),
                [
                    'source' => 'widget',
                    'section_id' => $section->id,
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'conversation.section_id',
                $section->id,
            );

        $this->assertDatabaseHas(
            'ai_chat_conversations',
            [
                'user_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'section_id' => $section->id,
                'title' => '新しい相談',
                'auto_title_enabled' => true,
            ],
        );
    }

    public function test_ai_chat_route_returns_404_when_feature_is_disabled(): void
    {
        config([
            'ai-chat.enabled' => false,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $response = $this
            ->actingAs($student)
            ->get(route('ai-chat.index'));

        $response->assertNotFound();
    }

    public function test_full_screen_creation_with_first_message_saves_ai_response_and_redirects(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => 'テスト用のAI回答です。',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 10,
                        'candidatesTokenCount' => 5,
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => 'テスト相談',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 8,
                        'candidatesTokenCount' => 3,
                    ],
                ], 200),
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        Enrollment::factory()
            ->learning()
            ->create([
                'user_id' => $student->id,
            ]);

        $response = $this
            ->actingAs($student)
            ->post(
                route('ai-chat.conversations.store'),
                [
                    'source' => 'full-screen',
                    'message' => '最初の質問です。',
                ],
            );

        $conversation = AiChatConversation::query()
            ->where('user_id', $student->id)
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'ai-chat.conversations.show',
                $conversation,
            ),
        );

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => '最初の質問です。',
            ],
        );

        $this->assertDatabaseHas(
            'ai_chat_messages',
            [
                'conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => 'テスト用のAI回答です。',
            ],
        );

        $this->assertDatabaseHas(
            'ai_chat_conversations',
            [
                'id' => $conversation->id,
                'title' => 'テスト相談',
            ],
        );

        Http::assertSentCount(2);
    }
}
