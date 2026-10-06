<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCredential;
use Carbon\CarbonInterface;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

class GoogleCalendarService
{
    public function clientFor(GoogleCredential $credential): GoogleClient
    {
        $client = $this->makeClient();

        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));

        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
            'expires_in' => $credential->token_expires_at?->diffInSeconds(now()),
            'created' => now()->timestamp,
        ]);

        if ($credential->token_expires_at?->isPast()) {
            $this->refreshAccessToken($client, $credential);
        }

        return $client;
    }

    /**
     * 指定期間のGoogle Calendar上のBusy時間帯を取得する。
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function busyPeriods(
        GoogleCredential $credential,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        $client = $this->clientFor($credential);

        $calendar = $this->makeCalendar($client);

        $request = new FreeBusyRequest([
            'timeMin' => $start->toRfc3339String(),
            'timeMax' => $end->toRfc3339String(),
            'timeZone' => (string) config('app.timezone'),
            'items' => [
                new FreeBusyRequestItem([
                    'id' => $credential->calendar_id,
                ]),
            ],
        ]);

        $response = $calendar->freebusy->query($request);

        $calendarBusy = $response->getCalendars()[$credential->calendar_id] ?? null;

        if ($calendarBusy === null) {
            return [];
        }

        return collect($calendarBusy->getBusy())
            ->map(fn ($period) => [
                'start' => $period->getStart(),
                'end' => $period->getEnd(),
            ])
            ->values()
            ->all();
    }

    private function refreshAccessToken(
        GoogleClient $client,
        GoogleCredential $credential,
    ): void {
        if ($credential->refresh_token === null) {
            throw new RuntimeException('Google refresh token is not available.');
        }

        $token = $client->fetchAccessTokenWithRefreshToken(
            $credential->refresh_token,
        );

        if (isset($token['error']) || ! isset($token['access_token'])) {
            throw new RuntimeException('Failed to refresh Google access token.');
        }

        $credential->access_token = $token['access_token'];

        if (isset($token['refresh_token'])) {
            $credential->refresh_token = $token['refresh_token'];
        }

        $credential->token_expires_at = isset($token['expires_in'])
            ? now()->addSeconds((int) $token['expires_in'])
            : null;

        $credential->save();

        $client->setAccessToken($token);
    }

    /**
     * Google Calendar に面談イベントを作成する。
     */
    public function createEvent(
        GoogleCredential $credential,
        CarbonInterface $start,
        CarbonInterface $end,
        string $summary,
        ?string $description = null,
    ): string {
        $client = $this->clientFor($credential);
        $calendar = $this->makeCalendar($client);

        $event = new Event([
            'summary' => $summary,
            'description' => $description,
            'start' => new EventDateTime([
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => (string) config('app.timezone'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => (string) config('app.timezone'),
            ]),
        ]);

        $created = $calendar->events->insert(
            $credential->calendar_id,
            $event,
        );

        return (string) $created->getId();
    }

    /**
     * Google Calendar から面談イベントを削除する。
     */
    public function deleteEvent(
        GoogleCredential $credential,
        string $eventId,
    ): void {
        $client = $this->clientFor($credential);
        $calendar = $this->makeCalendar($client);

        try {
            $calendar->events->delete(
                $credential->calendar_id,
                $eventId,
            );
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return;
            }

            throw $e;
        }
    }

    protected function makeClient(): GoogleClient
    {
        return new GoogleClient;
    }

    protected function makeCalendar(GoogleClient $client): Calendar
    {
        return new Calendar($client);
    }
}
