<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Services\AiChat\AiChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiChatConversationController extends Controller
{
    public function store(
        StoreConversationRequest $request,
        AiChatMessageService $messageService,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        $section = null;

        if ($request->filled('section_id')) {
            $section = Section::query()
                ->with('chapter.part')
                ->findOrFail($request->validated('section_id'));
        }

        $enrollment = $this->resolveEnrollment(
            $user->id,
            $section,
        );

        /*
         * 教材 Section から開始した場合は、
         * 同一教材の会話を乱立させず既存会話を再利用する。
         */
        if ($section !== null) {
            $existing = AiChatConversation::query()
                ->where('user_id', $user->id)
                ->where('section_id', $section->id)
                ->latest('last_message_at')
                ->first();

            if ($existing !== null) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'conversation' => $existing,
                    ]);
                }

                return redirect()->route(
                    'ai-chat.conversations.show',
                    $existing,
                );
            }
        }

        $conversation = AiChatConversation::query()->create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment?->id,
            'section_id' => $section?->id,
            'title' => '新しい相談',
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ]);

        /*
         * Widget からの作成時は会話 ID を JSON で返す。
         * 最初のメッセージは Widget 側から別途 message API に送信される。
         */
        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
            ], 201);
        }

        /*
         * フル画面の新規会話モーダルでは、
         * 最初の質問が入力されていればそのまま Gemini へ送信する。
         */
        if ($request->filled('message')) {
            $result = $messageService->send(
                $user,
                $conversation,
                $request->validated('message'),
            );

            if (($result['unavailable'] ?? false) === true) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with(
                        'error',
                        'AI相談は現在利用できません。',
                    );
            }

            if ($result['status'] === 429) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with(
                        'error',
                        '本日のAI相談の利用上限に達しました。',
                    );
            }

            if (! $result['success']) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with(
                        'error',
                        'AIが応答できませんでした。質問は保存されています。',
                    );
            }
        }

        return redirect()->route(
            'ai-chat.conversations.show',
            $conversation,
        );
    }

    public function show(
        Request $request,
        AiChatConversation $conversation,
    ): JsonResponse|View {
        $this->ensureOwner($request, $conversation);

        $conversation->load([
            'enrollment.certification',
            'section',
            'messages',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => $conversation,
                'messages' => $conversation->messages
                    ->map(fn ($message) => [
                        'id' => $message->id,
                        'role' => $message->role->value,
                        'status' => $message->status->value,
                        'content' => $message->content,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ])
                    ->values(),
            ]);
        }

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function update(
        UpdateConversationRequest $request,
        AiChatConversation $conversation,
    ): RedirectResponse {
        $this->ensureOwner($request, $conversation);

        $conversation->update([
            'title' => $request->validated('title'),
            'auto_title_enabled' => false,
        ]);

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(
        Request $request,
        AiChatConversation $conversation,
    ): RedirectResponse {
        $this->ensureOwner($request, $conversation);

        $conversation->delete();

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '会話を削除しました。');
    }

    private function ensureOwner(
        Request $request,
        AiChatConversation $conversation,
    ): void {
        abort_unless(
            $conversation->user_id === $request->user()?->id,
            403,
        );
    }

    private function resolveEnrollment(
        string $userId,
        ?Section $section,
    ): ?Enrollment {
        $query = Enrollment::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ]);

        if ($section !== null) {
            $certificationId = $section->chapter?->part?->certification_id;

            if ($certificationId !== null) {
                return $query
                    ->where('certification_id', $certificationId)
                    ->first();
            }
        }

        return $query
            ->orderBy('created_at')
            ->first();
    }
}
