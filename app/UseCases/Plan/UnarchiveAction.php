<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class UnarchiveAction
{
    public function __invoke(Plan $plan): Plan
    {
        if ($plan->status !== PlanStatus::Archived) {
            throw new PlanInvalidTransitionException(
                'アーカイブ済みのプランのみ下書きに戻せます。'
            );
        }

        DB::transaction(function () use ($plan): void {
            $plan->update([
                'status' => PlanStatus::Draft->value,
            ]);
        });

        return $plan->refresh();
    }
}
