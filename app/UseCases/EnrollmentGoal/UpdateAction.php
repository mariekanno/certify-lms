<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        EnrollmentGoal $goal,
        array $validated,
    ): EnrollmentGoal {
        DB::transaction(function () use ($goal, $validated): void {
            $goal->update($validated);
        });

        return $goal->refresh();
    }
}
