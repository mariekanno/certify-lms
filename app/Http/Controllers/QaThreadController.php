<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaBoard\IndexRequest;
use App\Http\Requests\QaBoard\StoreThreadRequest;
use App\Http\Requests\QaBoard\UpdateThreadRequest;
use App\Models\Certification;
use App\Models\QaThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QaThreadController extends Controller
{
    /**
     * 質問掲示板一覧。
     * student: 公開済資格すべて
     * coach: 公開済み + 担当資格のみ
     * admin: 公開停止を含む全資格
     */
    public function index(IndexRequest $request): View
    {
        $viewer = $request->user();
        $filters = $request->filters();

        $query = QaThread::query()
            ->with([
                'user',
                'certification',
            ])
            ->withCount('replies');

        if ($viewer->role === UserRole::Admin) {
            $certifications = Certification::query()
                ->orderBy('name')
                ->get();
        } elseif ($viewer->role === UserRole::Coach) {
            $query
                ->publishedCertification()
                ->assignedToCoach($viewer);

            $certifications = Certification::query()
                ->published()
                ->assignedTo($viewer)
                ->orderBy('name')
                ->get();
        } else {
            $query->publishedCertification();

            $certifications = Certification::query()
                ->published()
                ->orderBy('name')
                ->get();
        }

        if (! empty($filters['certification_id'])) {
            $query->where(
                'certification_id',
                $filters['certification_id'],
            );
        }

        $query
            ->statusFilter($filters['status'])
            ->keyword($filters['keyword']);

        $threads = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $certifications,
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    /**
     * 質問投稿画面。
     */
    public function create(Request $request): View
    {
        $this->authorize('create', QaThread::class);

        $certifications = Certification::query()
            ->published()
            ->orderBy('name')
            ->get();

        return view('qa-thread.create', [
            'certifications' => $certifications,
        ]);
    }

    /**
     * 質問を投稿。
     */
    public function store(StoreThreadRequest $request): RedirectResponse
    {
        $thread = QaThread::create([
            'certification_id' => $request->validated('certification_id'),
            'user_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'body' => $request->validated('body'),
            'status' => QaThreadStatus::Unresolved,
            'resolved_at' => null,
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    /**
     * 質問詳細。
     */
    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load([
            'user',
            'certification',
            'replies.user',
        ])->loadCount('replies');

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    /**
     * 質問編集画面。
     */
    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        $thread->load('certification');

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    /**
     * 質問を更新。
     */
    public function update(
        UpdateThreadRequest $request,
        QaThread $thread,
    ): RedirectResponse {
        $thread->update($request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    /**
     * 質問を削除。
     */
    public function destroy(
        QaThread $thread,
        Request $request,
    ): RedirectResponse {
        $this->authorize('delete', $thread);

        // 管理者以外は、回答が付いている質問を削除できない
        if (
            $request->user()->role !== UserRole::Admin
            && $thread->replies()->exists()
        ) {
            abort(409, '回答が付いている質問は削除できません。');
        }

        $thread->delete();

        if ($request->routeIs('admin.*')) {
            return redirect()
                ->route('admin.qa-board.index')
                ->with('success', '質問を削除しました。');
        }

        return redirect()
            ->route('qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    /**
     * 質問を解決済みに変更。
     */
    public function resolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を解決済にマークしました。');
    }

    /**
     * 質問を未解決に戻す。
     */
    public function unresolve(QaThread $thread): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $thread->update([
            'status' => QaThreadStatus::Unresolved,
            'resolved_at' => null,
        ]);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を未解決に戻しました。');
    }
}
