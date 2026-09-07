<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    /**
     * @param array<string, mixed> $validated
     */
    public function __invoke(
        MeetingPack $plan,
        array $validated,
        User $admin,
    ): MeetingPack {
        return DB::transaction(function () use ($plan, $validated, $admin): MeetingPack {
            $plan->update([
                ...$validated,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->refresh();
        });
    }
}
