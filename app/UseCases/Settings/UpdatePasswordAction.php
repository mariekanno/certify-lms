<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdatePasswordAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(User $user, array $validated): void
    {
        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'password' => $validated['password'],
            ]);
        });
    }
}