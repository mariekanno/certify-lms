<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AnnouncementController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->with([
                'targetCertification',
                'targetUser',
                'createdBy',
            ])
            ->orderByDesc('dispatched_at')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('announcement.management.index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        $certifications = Certification::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $students = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        return view('announcement.management.create', [
            'certifications' => $certifications,
            'students' => $students,
        ]);
    }

    public function store(
        StoreRequest $request,
        StoreAction $action,
    ): RedirectResponse {
        $announcement = $action(
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with(
                'success',
                "お知らせを配信しました ({$announcement->dispatched_count}件)。",
            );
    }

    public function show(Announcement $announcement): View
    {
        $this->authorize('view', $announcement);

        $announcement->load([
            'targetCertification',
            'targetUser',
            'createdBy',
        ]);

        return view('announcement.management.show', [
            'announcement' => $announcement,
        ]);
    }
}
