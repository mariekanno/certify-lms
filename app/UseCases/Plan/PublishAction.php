<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class PublishAction
{
    public function __invoke(Plan $plan): Plan
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw new PlanInvalidTransitionException(
                '下書きのプランのみ公開できます。'
            );
        }

        DB::transaction(function () use ($plan): void {
            $plan->update([
                'status' => PlanStatus::Published->value,
            ]);
        });

        return $plan->refresh();
    }
}
