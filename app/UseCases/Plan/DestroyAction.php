<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw new PlanNotDeletableException(
                '下書きのプランのみ削除できます。'
            );
        }

        if ($plan->users()->exists()) {
            throw new PlanNotDeletableException(
                '受講者が紐づいているプランは削除できません。'
            );
        }

        if ($plan->userPlanLogs()->exists()) {
            throw new PlanNotDeletableException(
                '利用履歴があるプランは削除できません。'
            );
        }

        DB::transaction(function () use ($plan): void {
            $plan->delete();
        });
    }
}