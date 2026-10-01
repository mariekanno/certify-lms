<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AiChatMessageService
{
    public function __construct(
        private readonly GeminiService $gemini,
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     status: int,
     *     user_message?: AiChatMessage,
     *     assistant_message?: AiChatMessage,
     *     upstream_status?: int|null
     * }
     */
    public function send(
        User $user,
        AiChatConversation $conversation,
        string $content,
    ): array {
        if ((string) config('ai-chat.gemini.api_key') === '') {
            return [
                'success' => false,
                'status' => 503,
                'unavailable' => true,
            ];
        }
        $dailyLimit = (int) config('ai-chat.daily_limit', 30);

        $todayCount = AiChatMessage::query()
            ->where('role', AiChatMessageRole::User->value)
            ->whereDate('created_at', today())
            ->whereHas('conversation', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->count();

        if ($todayCount >= $dailyLimit) {
            return [
                'success' => false,
                'status' => 429,
            ];
        }

        $userMessage = $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => $content,
        ]);

        $assistantMessage = $conversation->messages()->create([
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Pending,
            'content' => '',
            'model' => config(
                'ai-chat.gemini.model',
                'gemini-3.1-flash-lite',
            ),
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        $conversation->loadMissing([
            'enrollment.certification',
            'section.chapter.part',
        ]);

        try {
            $result = $this->gemini->generate(
                $this->buildHistory($conversation),
                $this->buildSystemInstruction($conversation),
            );

            $assistantMessage->update([
                'status' => AiChatMessageStatus::Completed,
                'content' => $result['content'],
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'response_time_ms' => $result['response_time_ms'],
                'error_detail' => null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            $this->updateTitleIfNeeded(
                $conversation,
                $userMessage->content,
                $assistantMessage->content,
            );

            Log::channel('ai-chat')->info('Gemini response completed.', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'message_id' => $assistantMessage->id,
                'model' => $result['model'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'response_time_ms' => $result['response_time_ms'],
            ]);

            return [
                'success' => true,
                'status' => 200,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage->fresh(),
            ];
        } catch (RequestException $e) {
            return $this->handleFailure(
                $conversation,
                $userMessage,
                $assistantMessage,
                $e,
                $e->response?->status(),
            );
        } catch (RuntimeException $e) {
            return $this->handleFailure(
                $conversation,
                $userMessage,
                $assistantMessage,
                $e,
            );
        } catch (Throwable $e) {
            report($e);

            return $this->handleFailure(
                $conversation,
                $userMessage,
                $assistantMessage,
                $e,
            );
        }
    }

    /**
     * @return array<int, array{
     *     role: string,
     *     parts: array<int, array{text: string}>
     * }>
     */
    private function buildHistory(
        AiChatConversation $conversation,
    ): array {
        $historyLimit = max(
            1,
            (int) config('ai-chat.history_limit', 10),
        );

        return $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->whereIn('role', [
                AiChatMessageRole::User->value,
                AiChatMessageRole::Assistant->value,
            ])
            ->latest('created_at')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiChatMessage $message): array => [
                'role' => $message->role === AiChatMessageRole::User
                    ? 'user'
                    : 'model',
                'parts' => [
                    [
                        'text' => $message->content,
                    ],
                ],
            ])
            ->all();
    }

    private function buildSystemInstruction(
        AiChatConversation $conversation,
    ): string {
        $lines = [
            'あなたは資格学習を支援するAIアシスタントです。',
            '受講生の質問に対して、わかりやすく簡潔に日本語で回答してください。',
            '不確かな内容は断定せず、不明であることを伝えてください。',
        ];

        $certification = $conversation->enrollment?->certification;

        if ($certification !== null) {
            $lines[] = '現在の学習対象資格: '.$certification->name;
        }

        if ($conversation->section !== null) {
            $lines[] = '現在閲覧している教材Section: '
                .$conversation->section->title;

            $sectionContent = $this->sectionContent(
                $conversation->section,
            );

            if ($sectionContent !== null) {
                $lines[] = "教材内容:\n".$sectionContent;
            }
        }

        return implode("\n\n", $lines);
    }

    private function sectionContent(object $section): ?string
    {
        foreach (['content', 'body', 'description'] as $attribute) {
            $value = $section->{$attribute} ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function updateTitleIfNeeded(
        AiChatConversation $conversation,
        string $userMessage,
        string $assistantMessage,
    ): void {
        if (! $conversation->auto_title_enabled) {
            return;
        }

        if ($conversation->title !== '新しい相談') {
            return;
        }

        try {
            $result = $this->gemini->generate(
                [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => <<<TEXT
次の会話内容を表す日本語タイトルを作ってください。

ユーザー:
{$userMessage}

AI:
{$assistantMessage}
TEXT,
                            ],
                        ],
                    ],
                ],
                '資格学習AIチャットの会話タイトルを生成してください。30文字以内の簡潔なタイトルのみを返し、かぎ括弧や説明文は付けないでください。',
            );

            $title = trim($result['content']);

            if ($title === '') {
                return;
            }

            $conversation->update([
                'title' => mb_substr($title, 0, 100),
            ]);
        } catch (Throwable $e) {
            Log::channel('ai-chat')->warning(
                'AI chat title generation failed.',
                [
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    /**
     * @return array{
     *     success: false,
     *     status: 502,
     *     user_message: AiChatMessage,
     *     assistant_message: AiChatMessage,
     *     upstream_status: int|null
     * }
     */
    private function handleFailure(
        AiChatConversation $conversation,
        AiChatMessage $userMessage,
        AiChatMessage $assistantMessage,
        Throwable $exception,
        ?int $upstreamStatus = null,
    ): array {
        $detail = $upstreamStatus !== null
            ? "{$upstreamStatus} {$exception->getMessage()}"
            : $exception->getMessage();

        $assistantMessage->update([
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => $detail,
        ]);

        $conversation->update([
            'last_message_at' => now(),
        ]);

        Log::channel('ai-chat')->error('Gemini response failed.', [
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'upstream_status' => $upstreamStatus,
            'error' => $exception->getMessage(),
        ]);

        return [
            'success' => false,
            'status' => 502,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage->fresh(),
            'upstream_status' => $upstreamStatus,
        ];
    }
}
