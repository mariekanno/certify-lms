<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        User $user,
        Enrollment $enrollment,
        array $validated,
    ): EnrollmentNote {
        return DB::transaction(function () use ($user, $enrollment, $validated) {
            return EnrollmentNote::create([
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);
        });
    }
}
