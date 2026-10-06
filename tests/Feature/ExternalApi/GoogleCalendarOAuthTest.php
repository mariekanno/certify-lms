<?php

declare(strict_types=1);

namespace Tests\Feature\ExternalApi;

use App\Http\Controllers\Settings\GoogleCalendarController;
use App\Models\GoogleCredential;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_stores_oauth_state_and_redirect_path_in_session(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->get(route('settings.google-calendar.redirect', [
                'redirect_path' => '/settings/availability',
            ]));

        $response->assertRedirect();

        $this->assertNotNull(session('google_oauth_state'));
        $this->assertSame(
            '/settings/availability',
            session('google_oauth_redirect_path'),
        );
    }

    public function test_callback_fails_when_state_does_not_match(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->withSession([
                'google_oauth_state' => 'expected-state',
                'google_oauth_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'wrong-state',
                'code' => 'test-code',
            ]));

        $response
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'error',
                'Googleカレンダーとの連携に失敗しました。',
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_callback_fails_when_code_is_missing(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this
            ->actingAs($coach)
            ->withSession([
                'google_oauth_state' => 'test-state',
                'google_oauth_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'test-state',
            ]));

        $response
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'error',
                'Googleカレンダーとの連携に失敗しました。',
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_destroy_removes_google_credential(): void
    {
        $coach = User::factory()->coach()->create();

        GoogleCredential::query()->create([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $response = $this
            ->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'));

        $response
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'success',
                'Googleカレンダーとの連携を解除しました。',
            );

        $this->assertDatabaseCount('google_credentials', 0);
    }

    public function test_callback_saves_google_credential_on_success(): void
    {
        $coach = User::factory()->coach()->create();

        $client = Mockery::mock(GoogleClient::class)->makePartial();

        $client->shouldReceive('fetchAccessTokenWithAuthCode')
            ->once()
            ->with('test-code')
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);

        $this->app->bind(
            GoogleCalendarController::class,
            function () use ($client) {
                return new class($client) extends GoogleCalendarController
                {
                    public function __construct(
                        private readonly GoogleClient $testClient,
                    ) {}

                    protected function googleClient(): GoogleClient
                    {
                        return $this->testClient;
                    }
                };
            },
        );

        $response = $this
            ->actingAs($coach)
            ->withSession([
                'google_oauth_state' => 'test-state',
                'google_oauth_redirect_path' => '/settings/availability',
            ])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'test-state',
                'code' => 'test-code',
            ]));

        $response
            ->assertRedirect('/settings/availability')
            ->assertSessionHas(
                'success',
                'Googleカレンダーと連携しました。',
            );

        $this->assertDatabaseHas('google_credentials', [
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
        ]);
    }
}
