<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentNote\StoreRequest;
use App\Http\Requests\EnrollmentNote\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\UseCases\EnrollmentNote\DestroyAction;
use App\UseCases\EnrollmentNote\StoreAction;
use App\UseCases\EnrollmentNote\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentNoteController extends Controller
{
    public function store(
        StoreRequest $request,
        Enrollment $enrollment,
        StoreAction $action,
    ): RedirectResponse {
        $this->authorize('create', [EnrollmentNote::class, $enrollment]);

        $action(
            $request->user(),
            $enrollment,
            $request->validated(),
        );

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'メモを追加しました。');
    }

    public function edit(EnrollmentNote $note): View
    {
        $this->authorize('update', $note);

        return view('enrollment-note.edit', [
            'note' => $note,
        ]);
    }

    public function update(
        UpdateRequest $request,
        EnrollmentNote $note,
        UpdateAction $action,
    ): RedirectResponse {
        $this->authorize('update', $note);

        $action($note, $request->validated());

        return redirect()
            ->route('enrollments.show', $note->enrollment)
            ->with('success', 'メモを更新しました。');
    }

    public function destroy(
        EnrollmentNote $note,
        DestroyAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $note);

        $enrollment = $note->enrollment;

        $action($note);

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', 'メモを削除しました。');
    }
}
