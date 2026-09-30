<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\GoogleCalendarCallbackRequest;
use App\Http\Requests\Settings\GoogleCalendarRedirectRequest;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    public function redirect(GoogleCalendarRedirectRequest $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));

        $request->session()->put('google_oauth_state', $state);
        $request->session()->put(
            'google_oauth_redirect_path',
            $request->validated('redirect_path') ?? '/settings/availability',
        );

        $client = $this->googleClient();
        $client->setState($state);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(GoogleCalendarCallbackRequest $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_oauth_state');
        $redirectPath = $request->session()->pull(
            'google_oauth_redirect_path',
            '/settings/availability',
        );

        if (
            ! is_string($expectedState)
            || ! hash_equals($expectedState, $request->string('state')->toString())
        ) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        if ($request->filled('error')) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        if (! $request->filled('code')) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        $client = $this->googleClient();

        try {
            $token = $client->fetchAccessTokenWithAuthCode(
                $request->string('code')->toString(),
            );
        } catch (\Throwable $e) {
            report($e);

            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        if (isset($token['error'])) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        if (! isset($token['access_token'])) {
            return redirect($redirectPath)
                ->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        $user = $request->user();

        $expiresIn = isset($token['expires_in'])
            ? (int) $token['expires_in']
            : null;

        $credential = $user->googleCredential()->firstOrNew();

        $credential->calendar_id = 'primary';
        $credential->access_token = $token['access_token'];
        $credential->token_expires_at = $expiresIn !== null
            ? now()->addSeconds($expiresIn)
            : null;
        $credential->connected_at = now();

        if (isset($token['refresh_token'])) {
            $credential->refresh_token = $token['refresh_token'];
        }

        $credential->save();

        return redirect($redirectPath)
            ->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()
            ?->googleCredential()
            ->delete();

        return redirect('/settings/availability')
            ->with('success', 'Googleカレンダーとの連携を解除しました。');
    }

    private function googleClient(): GoogleClient
    {
        $client = new GoogleClient;

        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));

        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope('https://www.googleapis.com/auth/calendar');

        return $client;
    }
}
