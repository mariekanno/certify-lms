<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\StoreReplyRequest;
use App\Http\Requests\QaBoard\UpdateReplyRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QaReplyController extends Controller
{
    /**
     * 回答を投稿。
     */
    public function store(
        QaThread $thread,
        StoreReplyRequest $request,
    ): RedirectResponse {
        QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    /**
     * 回答編集画面。
     */
    public function edit(
        QaThread $thread,
        QaReply $reply,
    ): View {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    /**
     * 回答を更新。
     */
    public function update(
        QaThread $thread,
        QaReply $reply,
        UpdateReplyRequest $request,
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $reply->update($request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    /**
     * 回答を削除。
     */
    public function destroy(
        QaThread $thread,
        QaReply $reply,
        Request $request,
    ): RedirectResponse {
        abort_unless($reply->qa_thread_id === $thread->id, 404);

        $this->authorize('delete', $reply);

        $reply->delete();

        if ($request->routeIs('admin.*')) {
            return redirect()
                ->route('admin.qa-board.show', $thread)
                ->with('success', '回答を削除しました。');
        }

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }
}
