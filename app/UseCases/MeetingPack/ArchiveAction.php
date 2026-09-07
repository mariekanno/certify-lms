<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class ArchiveAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Published) {
            throw new HttpResponseException(
                response('公開中の面談パックのみアーカイブできます。', 409)
            );
        }

        return DB::transaction(function () use ($plan): MeetingPack {
            $plan->update([
                'status' => MeetingPackStatus::Archived->value,
            ]);

            return $plan->refresh();
        });
    }
}
