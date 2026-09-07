<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class PublishAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        if ($plan->status !== MeetingPackStatus::Draft) {
            throw new HttpResponseException(
                response('下書きの面談パックのみ公開できます。', 409)
            );
        }

        return DB::transaction(function () use ($plan): MeetingPack {
            $plan->update([
                'status' => MeetingPackStatus::Published->value,
            ]);

            return $plan->refresh();
        });
    }
}
