<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        Enrollment $enrollment,
        array $validated,
    ): EnrollmentGoal {
        return DB::transaction(function () use ($enrollment, $validated) {
            return $enrollment->goals()->create($validated);
        });
    }
}
