<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateProfileAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(User $user, array $validated): void
    {
        DB::transaction(function () use ($user, $validated) {
            $data = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            if ($user->role === UserRole::Coach) {
                $data['meeting_url'] = $validated['meeting_url'] ?? null;
            }

            $user->update($data);
        });
    }
}