<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateAction
{
    public function __invoke(Plan $plan, array $validated, User $admin): Plan
    {
        DB::transaction(function () use ($plan, $validated, $admin): void {
            $plan->update([
                ...$validated,
                'updated_by_user_id' => $admin->id,
            ]);
        });

        return $plan->refresh();
    }
}
