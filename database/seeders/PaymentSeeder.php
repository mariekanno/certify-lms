<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()
            ->where('email', 'student@certify-lms.test')
            ->first();

        $demoStudent = User::query()
            ->where('email', 'student-noquota@certify-lms.test')
            ->first();

        $meetingPack = MeetingPack::query()
            ->published()
            ->ordered()
            ->first();

        if (! $student || ! $meetingPack) {
            return;
        }

        Payment::query()->updateOrCreate(
            [
                'stripe_checkout_session_id' => 'cs_test_seed_completed',
            ],
            [
                'user_id' => $student->id,
                'meeting_pack_id' => $meetingPack->id,
                'stripe_payment_intent_id' => 'pi_test_seed_completed',
                'amount' => $meetingPack->price,
                'quantity' => $meetingPack->meeting_count,
                'currency' => 'jpy',
                'status' => PaymentStatus::Completed,
                'completed_at' => now()->subDays(2),
                'failed_at' => null,
            ],
        );

        Payment::query()->updateOrCreate(
            [
                'stripe_checkout_session_id' => 'cs_test_seed_pending',
            ],
            [
                'user_id' => $student->id,
                'meeting_pack_id' => $meetingPack->id,
                'stripe_payment_intent_id' => null,
                'amount' => $meetingPack->price,
                'quantity' => $meetingPack->meeting_count,
                'currency' => 'jpy',
                'status' => PaymentStatus::Pending,
                'completed_at' => null,
                'failed_at' => null,
            ],
        );

        if ($demoStudent) {
            Payment::query()->updateOrCreate(
                [
                    'stripe_checkout_session_id' => 'cs_test_seed_failed',
                ],
                [
                    'user_id' => $demoStudent->id,
                    'meeting_pack_id' => $meetingPack->id,
                    'stripe_payment_intent_id' => null,
                    'amount' => $meetingPack->price,
                    'quantity' => $meetingPack->meeting_count,
                    'currency' => 'jpy',
                    'status' => PaymentStatus::Failed,
                    'completed_at' => null,
                    'failed_at' => now()->subDay(),
                ],
            );
        }
    }
}
