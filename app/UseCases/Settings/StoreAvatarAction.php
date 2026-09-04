<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreAvatarAction
{
    public function __invoke(User $user, UploadedFile $file): void
    {
        $ulid = (string) Str::ulid();

        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => throw new RuntimeException('対応していない画像形式です。'),
        };

        $filename = "{$ulid}.{$extension}";
        $path = "avatars/{$filename}";

        $oldPath = $this->toStoragePath($user->avatar_url);

        $storedPath = Storage::disk('public')->putFileAs(
            'avatars',
            $file,
            $filename,
        );

        if ($storedPath === false) {
            throw new RuntimeException('アイコン画像の保存に失敗しました。');
        }

        try {
            DB::transaction(function () use ($user, $path) {
                $user->update([
                    'avatar_url' => '/storage/'.$path,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
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