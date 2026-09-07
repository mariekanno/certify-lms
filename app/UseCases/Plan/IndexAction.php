<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    public function __invoke(array $filters): LengthAwarePaginator
    {
        return Plan::query()
            ->withCount('users')
            ->when(
                $filters['keyword'],
                fn ($query, $keyword) => $query->where('name', 'like', '%'.$keyword.'%')
            )
            ->when(
                $filters['status'],
                fn ($query, $status) => $query->where(
                    'status',
                    PlanStatus::from($status)
                )
            )
            ->ordered()
            ->paginate(15)
            ->withQueryString();
    }
}
