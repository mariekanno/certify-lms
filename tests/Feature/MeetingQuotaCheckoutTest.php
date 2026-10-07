<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\MeetingQuotaCheckoutController;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Tests\TestCase;

class MeetingQuotaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_student_can_view_only_published_meeting_packs(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::InProgress,
        ]);

        $publishedPack = MeetingPack::factory()->create([
            'name' => '公開パック',
            'status' => MeetingPackStatus::Published,
        ]);

        $unpublishedPack = MeetingPack::factory()->create([
            'name' => '非公開パック',
            'status' => MeetingPackStatus::Draft,
        ]);

        $response = $this
            ->actingAs($student)
            ->get('/meeting-quota/checkout');

        $response
            ->assertOk()
            ->assertSee($publishedPack->name)
            ->assertDontSee($unpublishedPack->name);
    }

    public function test_unpublished_meeting_pack_cannot_be_purchased_directly(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::InProgress,
        ]);

        $unpublishedPack = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Draft,
        ]);

        $response = $this
            ->actingAs($student)
            ->postJson('/meeting-quota/checkout', [
                'meeting_pack_id' => $unpublishedPack->id,
            ]);

        $response->assertUnprocessable();
    }

    public function test_non_student_cannot_access_checkout(): void
    {
        $coach = User::factory()->create([
            'role' => 'coach',
            'status' => UserStatus::InProgress,
        ]);

        $response = $this
            ->actingAs($coach)
            ->get('/meeting-quota/checkout');

        $response->assertForbidden();
    }

    public function test_non_in_progress_student_cannot_access_checkout(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::Graduated,
        ]);

        $response = $this
            ->actingAs($student)
            ->get('/meeting-quota/checkout');

        $response->assertForbidden();
    }

    public function test_checkout_uses_dashboard_cancel_url_and_creates_pending_payment(): void
    {
        config([
            'services.stripe.secret' => 'sk_test_dummy',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'status' => UserStatus::InProgress,
        ]);

        $meetingPack = MeetingPack::factory()->create([
            'status' => MeetingPackStatus::Published,
            'price' => 3000,
            'meeting_count' => 1,
        ]);

        $captured = new \stdClass;
        $captured->payload = null;

        $this->app->bind(
            MeetingQuotaCheckoutController::class,
            function () use ($captured) {
                return new class($captured) extends MeetingQuotaCheckoutController
                {
                    public function __construct(
                        private \stdClass $captured,
                    ) {}

                    protected function stripeClient(string $secret): StripeClient
                    {
                        return new class($secret, $this->captured) extends StripeClient
                        {
                            public function __construct(
                                string $secret,
                                private \stdClass $captured,
                            ) {
                                parent::__construct($secret);
                            }

                            public function getService($name)
                            {
                                if ($name !== 'checkout') {
                                    return parent::getService($name);
                                }

                                return new class($this->captured)
                                {
                                    public object $sessions;

                                    public function __construct(\stdClass $captured)
                                    {
                                        $this->sessions = new class($captured)
                                        {
                                            public function __construct(
                                                private \stdClass $captured,
                                            ) {}

                                            public function create(array $payload): object
                                            {
                                                $this->captured->payload = $payload;

                                                return (object) [
                                                    'id' => 'cs_test_checkout',
                                                    'url' => 'https://checkout.stripe.test/session',
                                                ];
                                            }
                                        };
                                    }
                                };
                            }
                        };
                    }
                };
            },
        );

        $response = $this
            ->actingAs($student)
            ->post('/meeting-quota/checkout', [
                'meeting_pack_id' => $meetingPack->id,
            ]);

        $response->assertRedirect('https://checkout.stripe.test/session');

        $this->assertSame(
            url('/dashboard'),
            $captured->payload['cancel_url'],
        );

        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => 'cs_test_checkout',
            'status' => PaymentStatus::Pending->value,
        ]);
    }
}
