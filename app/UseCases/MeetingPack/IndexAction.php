<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexAction
{
    /**
     * @param array{
     *     keyword: string|null,
     *     status: string|null
     * } $filters
     *
     * @return LengthAwarePaginator<MeetingPack>
     */
    public function __invoke(array $filters): LengthAwarePaginator
    {
        return MeetingPack::query()
            ->when(
                $filters['keyword'],
                fn ($query, $keyword) => $query
                    ->where('name', 'like', '%'.$keyword.'%')
            )
            ->when(
                $filters['status'],
                fn ($query, $status) => $query
                    ->where('status', MeetingPackStatus::from($status))
            )
            ->ordered()
            ->paginate(15)
            ->withQueryString();
    }
}
