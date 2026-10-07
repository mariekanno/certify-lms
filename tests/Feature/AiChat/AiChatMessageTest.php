<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Carbon\CarbonImmutable;
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
                'message' => 'AI相談機能は現在ご利用いただけません。',
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
                'message' => '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。',
            ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => '上限後の質問',
        ]);
    }

    public function test_message_send_returns_429_after_50_messages(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.daily_limit' => 50,
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
        ]);

        AiChatMessage::factory()
            ->count(50)
            ->create([
                'conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'created_at' => now(),
            ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '51通目の質問'],
            )
            ->assertStatus(429)
            ->assertJson([
                'message' => '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。',
            ]);

        $this->assertDatabaseMissing('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => '51通目の質問',
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

    public function test_daily_limit_resets_at_midnight_jst(): void
    {
        config([
            'app.timezone' => 'Asia/Tokyo',
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.daily_limit' => 1,
        ]);

        $this->travelTo(
            CarbonImmutable::parse('2026-10-07 00:01:00', 'Asia/Tokyo')
        );

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
        ]);

        AiChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'created_at' => '2026-10-06 23:59:00',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答です。'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '日付変更後の質問'],
            )
            ->assertOk();

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'content' => '日付変更後の質問',
        ]);
    }

    public function test_gemini_receives_latest_20_completed_messages_only(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.daily_limit' => 50,
            'ai-chat.history_limit' => 20,
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'テスト回答'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'title' => '手動設定したタイトル',
            'auto_title_enabled' => false,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            AiChatMessage::factory()->create([
                'conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => "履歴{$i}",
                'created_at' => now()->subMinutes(30 - $i),
            ]);
        }

        AiChatMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '除外対象',
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '新しい質問'],
            )
            ->assertOk();

        Http::assertSent(function ($request) {
            $contents = $request->data()['contents'] ?? [];

            $texts = array_map(
                fn (array $item) => $item['parts'][0]['text'],
                $contents,
            );

            return count($texts) === 20
                && $texts[0] === '履歴7'
                && $texts[18] === '履歴25'
                && $texts[19] === '新しい質問'
                && ! in_array('除外対象', $texts, true)
                && ! in_array('履歴1', $texts, true);
        });
    }

    public function test_gemini_receives_section_learning_context(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '教材に関する回答です。'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $student = User::factory()
            ->student()
            ->inProgress()
            ->create();

        $certification = Certification::factory()->create([
            'name' => '基本情報技術者試験',
        ]);

        $part = Part::factory()
            ->forCertification($certification)
            ->create(['title' => 'コンピュータ基礎']);

        $chapter = Chapter::factory()
            ->forPart($part)
            ->create(['title' => 'データの表現']);

        $section = Section::factory()
            ->forChapter($chapter)
            ->create([
                'title' => '2進数',
                'body' => '2進数は0と1で数値を表現します。',
            ]);

        $conversation = AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'section_id' => $section->id,
            'title' => '手動設定したタイトル',
            'auto_title_enabled' => false,
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '2進数について教えてください。'],
            )
            ->assertOk();

        Http::assertSent(function ($request) {
            $instruction = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';

            return str_contains($instruction, '教材の所属資格: 基本情報技術者試験')
                && str_contains($instruction, 'Part: コンピュータ基礎')
                && str_contains($instruction, 'Chapter: データの表現')
                && str_contains($instruction, 'Section: 2進数')
                && str_contains($instruction, '2進数は0と1で数値を表現します。');
        });
    }

    public function test_manual_title_change_is_not_overwritten_by_auto_title(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-key',
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

        Http::fake(function ($request) use ($conversation) {
            $contents = $request->data()['contents'] ?? [];

            $isTitleGeneration = str_contains(
                $contents[0]['parts'][0]['text'] ?? '',
                '次の会話内容を表す日本語タイトルを作ってください。'
            );

            if ($isTitleGeneration) {
                AiChatConversation::query()
                    ->whereKey($conversation->id)
                    ->update([
                        'title' => '手動で変更したタイトル',
                        'auto_title_enabled' => false,
                    ]);

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'AIが生成したタイトル'],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'AIの回答です。'],
                            ],
                        ],
                    ],
                ],
            ], 200);
        });

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '質問です。'],
            )
            ->assertOk();

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => '手動で変更したタイトル',
            'auto_title_enabled' => false,
        ]);

        $this->assertDatabaseMissing('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => 'AIが生成したタイトル',
        ]);
    }
}
