<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    /**
     * 回答を投稿できるか。
     *
     * student / coach のみ。
     * coach は担当資格のみ。
     * admin は回答不可。
     */
    public function create(User $user, QaThread $thread): bool
    {
        if ($user->status !== UserStatus::InProgress) {
            return false;
        }

        if (! in_array($user->role, [
            UserRole::Student,
            UserRole::Coach,
        ], true)) {
            return false;
        }

        $thread->loadMissing('certification.coaches');

        if ($thread->certification === null
            || $thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Student) {
            return true;
        }

        return $thread->certification
            ->coaches
            ->contains('id', $user->id);
    }

    /**
     * 回答を編集できるか。
     * 投稿者本人のみ。
     */
    public function update(User $user, QaReply $reply): bool
    {
        return $user->status === UserStatus::InProgress
            && in_array($user->role, [
                UserRole::Student,
                UserRole::Coach,
            ], true)
            && $reply->user_id === $user->id;
    }

    /**
     * 回答を削除できるか。
     *
     * admin はモデレーション削除可能。
     * student / coach は回答投稿者本人のみ。
     */
    public function delete(User $user, QaReply $reply): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->status === UserStatus::InProgress
            && in_array($user->role, [
                UserRole::Student,
                UserRole::Coach,
            ], true)
            && $reply->user_id === $user->id;
    }
}
