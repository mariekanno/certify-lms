<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexAsCoachAction
{
    public function __invoke(
        User $coach,
        string $filter = 'upcoming',
        ?string $studentId = null,
        ?string $enrollmentId = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'student'])
            ->forCoach($coach)
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        return match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate($perPage),
            'all' => $query->orderByDesc('scheduled_at')->paginate($perPage),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate($perPage),
        };
    }
}
