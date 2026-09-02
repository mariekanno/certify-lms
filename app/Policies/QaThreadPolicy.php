<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    /**
     * 質問掲示板一覧を閲覧できるか。
     */
    public function viewAny(User $user): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return in_array($user->role, [
            UserRole::Student,
            UserRole::Coach,
        ], true)
            && $user->status === UserStatus::InProgress;
    }

    /**
     * 質問スレッド詳細を閲覧できるか。
     */
    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->status !== UserStatus::InProgress) {
            return false;
        }

        $thread->loadMissing('certification.coaches');

        if ($thread->certification === null
            || $thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,

            UserRole::Coach => $thread->certification
                ->coaches
                ->contains('id', $user->id),

            default => false,
        };
    }

    /**
     * 質問を新規投稿できるか。
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress;
    }

    /**
     * 質問を編集できるか。
     * 投稿者本人のみ。
     */
    public function update(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $thread->user_id === $user->id;
    }

    /**
     * 質問を削除できるか。
     *
     * admin はモデレーション削除可。
     * student は投稿者本人のみ。
     * 回答が付いている場合の削除制限は Controller 側で判定する。
     */
    public function delete(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $thread->user_id === $user->id;
    }

    /**
     * 質問を解決済みにできるか。
     */
    public function resolve(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $thread->user_id === $user->id;
    }

    /**
     * 質問を未解決に戻せるか。
     */
    public function unresolve(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $thread->user_id === $user->id;
    }
}
