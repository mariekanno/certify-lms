<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DestroyAvatarAction
{
    public function __invoke(User $user): void
    {
        $path = $this->toStoragePath($user->avatar_url);

        DB::transaction(function () use ($user) {
            $user->update([
                'avatar_url' => null,
            ]);
        });

        if ($path !== null) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function toStoragePath(?string $avatarUrl): ?string
    {
        if ($avatarUrl === null) {
            return null;
        }

        if (! str_starts_with($avatarUrl, '/storage/')) {
            return null;
        }

        return substr($avatarUrl, strlen('/storage/'));
    }
}
