<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(array $validated, User $admin): Plan
    {
        return DB::transaction(function () use ($validated, $admin): Plan {
            return Plan::create([
                ...$validated,
                'status' => PlanStatus::Draft->value,
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
            ]);
        });
    }
}
