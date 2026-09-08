<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class ArchiveAction
{
    public function __invoke(Plan $plan): Plan
    {
        if ($plan->status !== PlanStatus::Published) {
            throw new PlanInvalidTransitionException(
                '公開中のプランのみアーカイブできます。'
            );
        }

        DB::transaction(function () use ($plan): void {
            $plan->update([
                'status' => PlanStatus::Archived->value,
            ]);
        });

        return $plan->refresh();
    }
}
