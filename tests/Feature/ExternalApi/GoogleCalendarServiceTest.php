<?php

declare(strict_types=1);

namespace Tests\Feature\ExternalApi;

use App\Models\GoogleCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\Resource\Events;
use Google\Service\Calendar\Resource\Freebusy;
use Google\Service\Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_access_token_is_refreshed(): void
    {
        $credential = $this->credential([
            'access_token' => 'old-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $client = Mockery::mock(GoogleClient::class)->makePartial();

        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('refresh-token')
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);

        $service = $this->serviceWith($client);

        $service->clientFor($credential);

        $credential->refresh();

        $this->assertSame('new-access-token', $credential->access_token);
        $this->assertSame('new-refresh-token', $credential->refresh_token);
        $this->assertTrue($credential->token_expires_at->isFuture());
    }

    public function test_expired_access_token_without_refresh_token_throws(): void
    {
        $credential = $this->credential([
            'refresh_token' => null,
            'token_expires_at' => now()->subMinute(),
        ]);

        $client = Mockery::mock(GoogleClient::class)->makePartial();

        $service = $this->serviceWith($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Google refresh token is not available.',
        );

        $service->clientFor($credential);
    }

    public function test_refresh_failure_throws(): void
    {
        $credential = $this->credential([
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subMinute(),
        ]);

        $client = Mockery::mock(GoogleClient::class)->makePartial();

        $client->shouldReceive('fetchAccessTokenWithRefreshToken')
            ->once()
            ->with('refresh-token')
            ->andReturn([
                'error' => 'invalid_grant',
            ]);

        $service = $this->serviceWith($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to refresh Google access token.',
        );

        $service->clientFor($credential);
    }

    public function test_busy_periods_returns_calendar_busy_ranges(): void
    {
        $credential = $this->credential();

        $period1 = Mockery::mock();
        $period1->shouldReceive('getStart')
            ->once()
            ->andReturn('2026-10-06T10:00:00+09:00');
        $period1->shouldReceive('getEnd')
            ->once()
            ->andReturn('2026-10-06T11:00:00+09:00');

        $period2 = Mockery::mock();
        $period2->shouldReceive('getStart')
            ->once()
            ->andReturn('2026-10-06T14:00:00+09:00');
        $period2->shouldReceive('getEnd')
            ->once()
            ->andReturn('2026-10-06T15:00:00+09:00');

        $calendarBusy = Mockery::mock();
        $calendarBusy->shouldReceive('getBusy')
            ->once()
            ->andReturn([$period1, $period2]);

        $response = Mockery::mock();
        $response->shouldReceive('getCalendars')
            ->once()
            ->andReturn([
                'primary' => $calendarBusy,
            ]);

        $freebusy = Mockery::mock(Freebusy::class);
        $freebusy->shouldReceive('query')
            ->once()
            ->andReturn($response);

        $calendar = Mockery::mock(Calendar::class);
        $calendar->freebusy = $freebusy;

        $service = $this->serviceWith(
            new GoogleClient,
            $calendar,
        );

        $result = $service->busyPeriods(
            $credential,
            Carbon::parse('2026-10-06 09:00:00'),
            Carbon::parse('2026-10-06 18:00:00'),
        );

        $this->assertSame([
            [
                'start' => '2026-10-06T10:00:00+09:00',
                'end' => '2026-10-06T11:00:00+09:00',
            ],
            [
                'start' => '2026-10-06T14:00:00+09:00',
                'end' => '2026-10-06T15:00:00+09:00',
            ],
        ], $result);
    }

    public function test_create_event_returns_created_event_id(): void
    {
        $credential = $this->credential();

        $events = Mockery::mock(Events::class);

        $events->shouldReceive('insert')
            ->once()
            ->with(
                'primary',
                Mockery::type(Event::class),
            )
            ->andReturn(
                new Event([
                    'id' => 'google-event-123',
                ]),
            );

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $events;

        $service = $this->serviceWith(
            new GoogleClient,
            $calendar,
        );

        $eventId = $service->createEvent(
            $credential,
            Carbon::parse('2026-10-06 10:00:00'),
            Carbon::parse('2026-10-06 11:00:00'),
            '面談',
            'テスト面談',
        );

        $this->assertSame('google-event-123', $eventId);
    }

    public function test_delete_event_calls_google_calendar(): void
    {
        $credential = $this->credential();

        $events = Mockery::mock(Events::class);

        $events->shouldReceive('delete')
            ->once()
            ->with(
                'primary',
                'google-event-123',
            );

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $events;

        $service = $this->serviceWith(
            new GoogleClient,
            $calendar,
        );

        $service->deleteEvent(
            $credential,
            'google-event-123',
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function credential(array $overrides = []): GoogleCredential
    {
        $coach = User::factory()->coach()->create();

        return GoogleCredential::query()->create(array_merge([
            'user_id' => $coach->id,
            'calendar_id' => 'primary',
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ], $overrides));
    }

    private function serviceWith(
        GoogleClient $client,
        ?Calendar $calendar = null,
    ): GoogleCalendarService {
        return new class($client, $calendar) extends GoogleCalendarService
        {
            public function __construct(
                private readonly GoogleClient $testClient,
                private readonly ?Calendar $testCalendar,
            ) {}

            protected function makeClient(): GoogleClient
            {
                return $this->testClient;
            }

            protected function makeCalendar(
                GoogleClient $client,
            ): Calendar {
                if ($this->testCalendar === null) {
                    throw new RuntimeException(
                        'Test calendar was not configured.',
                    );
                }

                return $this->testCalendar;
            }
        };
    }

    public function test_delete_event_ignores_already_deleted_event(): void
    {
        $credential = $this->credential();

        $events = Mockery::mock(Events::class);

        $events->shouldReceive('delete')
            ->once()
            ->with(
                'primary',
                'already-deleted-event',
            )
            ->andThrow(
                new Exception(
                    'Not Found',
                    404,
                ),
            );

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $events;

        $service = $this->serviceWith(
            new GoogleClient,
            $calendar,
        );

        $service->deleteEvent(
            $credential,
            'already-deleted-event',
        );

        $this->addToAssertionCount(1);
    }

    public function test_delete_event_rethrows_non_404_error(): void
    {
        $credential = $this->credential();

        $events = Mockery::mock(Events::class);

        $events->shouldReceive('delete')
            ->once()
            ->with(
                'primary',
                'google-event-500',
            )
            ->andThrow(
                new Exception(
                    'Internal Server Error',
                    500,
                ),
            );

        $calendar = Mockery::mock(Calendar::class);
        $calendar->events = $events;

        $service = $this->serviceWith(
            new GoogleClient,
            $calendar,
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);

        $service->deleteEvent(
            $credential,
            'google-event-500',
        );
    }
}
