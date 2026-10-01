<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_send_returns_503_when_api_key_is_missing(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => '',
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => 'テスト質問です。',
                ],
            );

        $response
            ->assertStatus(503)
            ->assertJson([
                'message' => 'AI相談は現在利用できません。',
            ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => 'テスト質問です。',
        ]);
    }

    public function test_message_send_returns_429_when_daily_limit_is_reached(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.daily_limit' => 2,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
        ]);

        AiChatMessage::factory()
            ->count(2)
            ->create([
                'conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'created_at' => now(),
            ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '上限後の質問',
                ],
            );

        $response
            ->assertStatus(429)
            ->assertJson([
                'message' => '本日のAI相談の利用上限に達しました。',
            ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => '上限後の質問',
        ]);
    }

    public function test_user_message_remains_when_gemini_request_fails(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
        ]);

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
        ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '失敗しても残る質問',
                ],
            );

        $response
            ->assertStatus(502)
            ->assertJson([
                'message' => 'AIが応答できませんでした。',
                'upstream_status' => 503,
            ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => '失敗しても残る質問',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);
    }

    public function test_successful_message_send_saves_response_and_generates_title(): void
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
                                        'text' => '1010は10進数では10です。',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 12,
                        'candidatesTokenCount' => 8,
                    ],
                ], 200)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => '2進数1010の変換',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 10,
                        'candidatesTokenCount' => 5,
                    ],
                ], 200),
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'title' => '新しい相談',
            'auto_title_enabled' => true,
        ]);

        $response = $this
            ->actingAs($student)
            ->postJson(
                route(
                    'ai-chat.conversations.messages.store',
                    $conversation,
                ),
                [
                    'content' => '2進数の1010は10進数でいくつですか？',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'assistant_message.content',
                '1010は10進数では10です。',
            )
            ->assertJsonPath(
                'conversation.title',
                '2進数1010の変換',
            );

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => '1010は10進数では10です。',
            'input_tokens' => 12,
            'output_tokens' => 8,
        ]);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '2進数1010の変換',
            'auto_title_enabled' => true,
        ]);

        Http::assertSentCount(2);
    }
}
