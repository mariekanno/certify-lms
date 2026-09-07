<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Plan\IndexRequest;
use App\Http\Requests\Plan\StoreRequest;
use App\Http\Requests\Plan\UpdateRequest;
use App\Models\Plan;
use App\UseCases\Plan\ArchiveAction;
use App\UseCases\Plan\DestroyAction;
use App\UseCases\Plan\IndexAction;
use App\UseCases\Plan\PublishAction;
use App\UseCases\Plan\StoreAction;
use App\UseCases\Plan\UnarchiveAction;
use App\UseCases\Plan\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $filters = $request->filters();

        return view('plan.management.index', [
            'plans' => $action($filters),
            'keyword' => $filters['keyword'],
            'status' => $filters['status'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Plan::class);

        return view('plan.management.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $plan = $action($request->validated(), $request->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを作成しました。');
    }

    public function show(Plan $plan): View
    {
        $this->authorize('view', $plan);

        $plan->load([
            'users',
            'createdBy',
            'updatedBy',
        ]);

        return view('plan.management.show', [
            'plan' => $plan,
        ]);
    }

    public function edit(Plan $plan): View
    {
        $this->authorize('update', $plan);

        return view('plan.management.edit', [
            'plan' => $plan,
        ]);
    }

    public function update(
        UpdateRequest $request,
        Plan $plan,
        UpdateAction $action
    ): RedirectResponse {
        $plan = $action($plan, $request->validated(), $request->user());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを更新しました。');
    }

    public function destroy(
        Plan $plan,
        DestroyAction $action
    ): RedirectResponse {
        $this->authorize('delete', $plan);

        $action($plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('success', 'プランを削除しました。');
    }

    public function publish(
        Plan $plan,
        PublishAction $action
    ): RedirectResponse {
        $this->authorize('publish', $plan);

        $plan = $action($plan);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを公開しました。');
    }

    public function archive(
        Plan $plan,
        ArchiveAction $action
    ): RedirectResponse {
        $this->authorize('archive', $plan);

        $plan = $action($plan);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランをアーカイブしました。');
    }

    public function unarchive(
        Plan $plan,
        UnarchiveAction $action
    ): RedirectResponse {
        $this->authorize('unarchive', $plan);

        $plan = $action($plan);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('success', 'プランを下書きに戻しました。');
    }
}
