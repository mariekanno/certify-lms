<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.webhook_secret' => $this->webhookSecret,
        ]);
    }

    public function test_completed_checkout_marks_payment_completed_and_adds_quota(): void
    {
        $user = User::factory()->create([
            'max_meetings' => 2,
        ]);

        $meetingPack = MeetingPack::factory()->create([
            'meeting_count' => 1,
            'price' => 3000,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => 'cs_test_completed',
            'stripe_payment_intent_id' => null,
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
        ]);

        $payload = $this->completedPayload(
            sessionId: $payment->stripe_checkout_session_id,
            paymentIntentId: 'pi_test_completed',
        );

        $response = $this->postStripeWebhook($payload);

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('pi_test_completed', $payment->stripe_payment_intent_id);
        $this->assertNotNull($payment->completed_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 1,
        ]);
    }

    public function test_duplicate_webhook_does_not_add_quota_twice(): void
    {
        $user = User::factory()->create([
            'max_meetings' => 2,
        ]);

        $meetingPack = MeetingPack::factory()->create([
            'meeting_count' => 1,
            'price' => 3000,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => 'cs_test_duplicate',
            'stripe_payment_intent_id' => null,
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
        ]);

        $payload = $this->completedPayload(
            sessionId: $payment->stripe_checkout_session_id,
            paymentIntentId: 'pi_test_duplicate',
        );

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased)
                ->count(),
        );
    }

    public function test_invalid_signature_returns_400(): void
    {
        $payload = $this->completedPayload(
            sessionId: 'cs_test_invalid_signature',
            paymentIntentId: 'pi_test_invalid_signature',
        );

        $response = $this
            ->withHeader('Stripe-Signature', 'invalid-signature')
            ->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(400);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_missing_signature_returns_400(): void
    {
        $payload = $this->completedPayload(
            sessionId: 'cs_test_missing_signature',
            paymentIntentId: 'pi_test_missing_signature',
        );

        $response = $this->postJson('/webhooks/stripe', $payload);

        $response->assertStatus(400);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unexpected_event_is_accepted_without_changing_quota(): void
    {
        $payload = [
            'id' => 'evt_test_unexpected',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                ],
            ],
        ];

        $response = $this->postStripeWebhook($payload);

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unknown_checkout_session_is_safely_ignored(): void
    {
        $payload = $this->completedPayload(
            sessionId: 'cs_test_not_found',
            paymentIntentId: 'pi_test_not_found',
        );

        $response = $this->postStripeWebhook($payload);

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    private function completedPayload(
        string $sessionId,
        string $paymentIntentId,
    ): array {
        return [
            'id' => 'evt_'.$sessionId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_intent' => $paymentIntentId,
                ],
            ],
        ];
    }

    private function postStripeWebhook(array $payload)
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $timestamp = time();

        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$json,
            $this->webhookSecret,
        );

        return $this->call(
            'POST',
            '/webhooks/stripe',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $json,
        );
    }

    public function test_expired_checkout_marks_pending_payment_failed(): void
    {
        $user = User::factory()->create();

        $meetingPack = MeetingPack::factory()->create([
            'meeting_count' => 1,
            'price' => 3000,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => 'cs_test_expired',
            'stripe_payment_intent_id' => null,
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
            'completed_at' => null,
            'failed_at' => null,
        ]);

        $payload = [
            'id' => 'evt_test_expired',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $payment->stripe_checkout_session_id,
                    'object' => 'checkout.session',
                ],
            ],
        ];

        $response = $this->postStripeWebhook($payload);

        $response
            ->assertOk()
            ->assertJson([
                'received' => true,
            ]);

        $payment->refresh();

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertNotNull($payment->failed_at);
        $this->assertNull($payment->completed_at);

        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_expired_checkout_does_not_change_completed_payment(): void
    {
        $user = User::factory()->create();

        $meetingPack = MeetingPack::factory()->create([
            'meeting_count' => 1,
            'price' => 3000,
        ]);

        $completedAt = now()->subMinute();

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => 'cs_test_completed_then_expired',
            'stripe_payment_intent_id' => 'pi_test_completed_then_expired',
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'status' => PaymentStatus::Completed,
            'completed_at' => $completedAt,
            'failed_at' => null,
        ]);

        $payload = [
            'id' => 'evt_test_completed_then_expired',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $payment->stripe_checkout_session_id,
                    'object' => 'checkout.session',
                ],
            ],
        ];

        $this->postStripeWebhook($payload)->assertOk();

        $payment->refresh();

        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertNotNull($payment->completed_at);
        $this->assertNull($payment->failed_at);
    }
}
