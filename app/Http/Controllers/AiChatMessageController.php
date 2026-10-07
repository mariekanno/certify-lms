<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\AiChat\AiChatMessageService;
use Illuminate\Http\JsonResponse;

class AiChatMessageController extends Controller
{
    public function store(
        StoreMessageRequest $request,
        AiChatConversation $conversation,
        AiChatMessageService $messageService,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $conversation->user_id === $user->id,
            403,
        );

        $result = $messageService->send(
            $user,
            $conversation,
            $request->validated('content'),
        );

        if (($result['unavailable'] ?? false) === true) {
            return response()->json([
                'message' => 'AI相談機能は現在ご利用いただけません。',
            ], 503);
        }

        if ($result['status'] === 429) {
            return response()->json([
                'message' => '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。',
            ], 429);
        }

        if (! $result['success']) {
            return response()->json([
                'message' => 'AIが応答できませんでした。',
                'upstream_status' => $result['upstream_status'] ?? null,
                'user_message' => $this->messagePayload(
                    $result['user_message'],
                ),
                'assistant_message' => $this->messagePayload(
                    $result['assistant_message'],
                ),
            ], 502);
        }

        return response()->json([
            'user_message' => $this->messagePayload(
                $result['user_message'],
            ),
            'assistant_message' => $this->messagePayload(
                $result['assistant_message'],
            ),
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(
        AiChatMessage $message,
    ): array {
        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'status' => $message->status->value,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
            'response_time_ms' => $message->response_time_ms,
            'output_tokens' => $message->output_tokens,
        ];
    }
}
