<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(array $validated, User $admin): MeetingPack
    {
        return DB::transaction(function () use ($validated, $admin): MeetingPack {
            return MeetingPack::create([
                ...$validated,
                'status' => MeetingPackStatus::Draft->value,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);
        });
    }
}
