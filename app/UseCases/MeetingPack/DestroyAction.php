<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __invoke(MeetingPack $plan): void
    {
        if ($plan->status === MeetingPackStatus::Published) {
            throw new HttpResponseException(
                response('公開中の面談パックは削除できません。', 409)
            );
        }

        DB::transaction(fn () => $plan->delete());
    }
}
