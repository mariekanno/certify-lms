<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        EnrollmentNote $note,
        array $validated,
    ): EnrollmentNote {
        return DB::transaction(function () use ($note, $validated) {
            $note->update([
                'body' => $validated['body'],
            ]);

            return $note->refresh();
        });
    }
}
