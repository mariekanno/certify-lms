<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\StripeClient;

class MeetingQuotaCheckoutController extends Controller
{
    /**
     * 追加面談パック選択画面を表示する。
     */
    public function index(): View
    {
        $plans = MeetingPack::query()
            ->published()
            ->ordered()
            ->get();

        return view('meeting-quota.checkout-select', [
            'plans' => $plans,
        ]);
    }

    /**
     * Stripe Checkout Sessionを作成して決済画面へ遷移する。
     */
    public function create(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meeting_pack_id' => ['required', 'ulid', 'exists:meeting_packs,id'],
        ]);

        $user = $request->user();

        $meetingPack = MeetingPack::query()
            ->published()
            ->findOrFail($validated['meeting_pack_id']);

        $stripeSecret = (string) config('services.stripe.secret');

        abort_if($stripeSecret === '', 503, 'Stripe決済は現在利用できません。');

        $stripe = new StripeClient($stripeSecret);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $user->email,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'jpy',
                        'unit_amount' => $meetingPack->price,
                        'product_data' => [
                            'name' => $meetingPack->name,
                            'description' => $meetingPack->description ?? '',
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => route(
                'meeting-quota.checkout.success',
                ['session_id' => '{CHECKOUT_SESSION_ID}'],
            ),
            'cancel_url' => route('meeting-quota.checkout.select'),
            'metadata' => [
                'user_id' => $user->id,
                'meeting_pack_id' => $meetingPack->id,
                'meeting_count' => (string) $meetingPack->meeting_count,
                'amount' => (string) $meetingPack->price,
            ],
        ]);

        Payment::query()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_intent_id' => null,
            'amount' => $meetingPack->price,
            'quantity' => $meetingPack->meeting_count,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
        ]);

        return redirect()->away($session->url);
    }

    /**
     * Stripe決済後の完了画面を表示する。
     */
    public function success(Request $request): View
    {
        $sessionId = $request->string('session_id')->toString();

        $payment = null;

        if ($sessionId !== '') {
            $payment = Payment::query()
                ->with('meetingPack')
                ->where('user_id', $request->user()->id)
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();
        }

        return view('meeting-quota.success', [
            'payment' => $payment,
        ]);
    }
}
