<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return response()->json([
                'message' => 'Webhook secret is not configured.',
            ], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json([
                'message' => 'Invalid Stripe webhook signature.',
            ], 400);
        }

        $session = $event->data->object;

        if ($event->type === 'checkout.session.expired') {
            $payment = Payment::query()
                ->where('stripe_checkout_session_id', $session->id)
                ->first();

            if (! $payment) {
                return response()->json(['received' => true]);
            }

            DB::transaction(function () use ($payment): void {
                $payment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($payment->status !== PaymentStatus::Pending) {
                    return;
                }

                $payment->update([
                    'status' => PaymentStatus::Failed,
                    'failed_at' => now(),
                ]);
            });

            return response()->json(['received' => true]);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['received' => true]);
        }

        $session = $event->data->object;

        $payment = Payment::query()
            ->where('stripe_checkout_session_id', $session->id)
            ->first();

        if (! $payment) {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment, $session): void {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Completed) {
                return;
            }

            $payment->update([
                'stripe_payment_intent_id' => is_string($session->payment_intent)
                    ? $session->payment_intent
                    : null,
                'status' => PaymentStatus::Completed,
                'completed_at' => now(),
                'failed_at' => null,
            ]);

            MeetingQuotaTransaction::query()->firstOrCreate(
                [
                    'related_payment_id' => $payment->id,
                    'type' => MeetingQuotaTransactionType::Purchased,
                ],
                [
                    'user_id' => $payment->user_id,
                    'amount' => $payment->quantity,
                    'occurred_at' => now(),
                    'note' => '追加面談購入',
                ],
            );
        });

        return response()->json(['received' => true]);
    }
}
