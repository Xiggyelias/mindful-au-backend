<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $apiAuthPerMinute = max(60, (int) env('API_RATE_LIMIT_AUTH_PER_MINUTE', 240));
        $apiGuestPerMinute = max(10, (int) env('API_RATE_LIMIT_GUEST_PER_MINUTE', 60));
        $authLoginPerMinute = max(3, (int) env('AUTH_LOGIN_RATE_LIMIT_PER_MINUTE', 10));
        $authRegisterPerMinute = max(1, (int) env('AUTH_REGISTER_RATE_LIMIT_PER_MINUTE', 5));
        $oauthTicketExchangePerMinute = max(5, (int) env('OAUTH_TICKET_EXCHANGE_RATE_LIMIT_PER_MINUTE', 30));
        $messagesReadPerMinute = max(30, (int) env('MESSAGES_READ_RATE_LIMIT_PER_MINUTE', 120));
        $messagesWritePerMinute = max(10, (int) env('MESSAGES_WRITE_RATE_LIMIT_PER_MINUTE', 60));
        $presencePerMinute = max(10, (int) env('PRESENCE_RATE_LIMIT_PER_MINUTE', 30));
        $aiChatPerMinute = max(5, (int) env('AI_CHAT_RATE_LIMIT_PER_MINUTE', 20));
        $aiReadPerMinute = max(10, (int) env('AI_READ_RATE_LIMIT_PER_MINUTE', 60));
        $diagnosticsSubmitPerMinute = max(5, (int) env('DIAGNOSTICS_SUBMIT_RATE_LIMIT_PER_MINUTE', 20));

        if (app()->environment('testing')) {
            // Keep throttling behavior in production while preventing flaky 429s in automated tests.
            $apiAuthPerMinute = 100000;
            $apiGuestPerMinute = 100000;
            $authLoginPerMinute = 100000;
            $authRegisterPerMinute = 100000;
            $oauthTicketExchangePerMinute = 100000;
            $messagesReadPerMinute = 100000;
            $messagesWritePerMinute = 100000;
            $presencePerMinute = 100000;
            $aiChatPerMinute = 100000;
            $aiReadPerMinute = 100000;
            $diagnosticsSubmitPerMinute = 100000;
        }

        RateLimiter::for('api', function (Request $request) use ($apiAuthPerMinute, $apiGuestPerMinute) {
            $userId = $request->user()?->id;
            if ($userId) {
                return Limit::perMinute($apiAuthPerMinute)->by('auth:' . $userId);
            }

            return Limit::perMinute($apiGuestPerMinute)->by('guest:' . $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request) use ($authLoginPerMinute) {
            $email = strtolower((string) $request->input('email', ''));
            return Limit::perMinute($authLoginPerMinute)->by($request->ip() . '|' . $email);
        });

        RateLimiter::for('auth-register', function (Request $request) use ($authRegisterPerMinute) {
            return Limit::perMinute($authRegisterPerMinute)->by($request->ip());
        });

        RateLimiter::for('oauth-ticket-exchange', function (Request $request) use ($oauthTicketExchangePerMinute) {
            $ticket = trim((string) $request->input('ticket', ''));
            $ticketHash = $ticket !== '' ? substr(hash('sha256', $ticket), 0, 24) : 'no-ticket';

            return Limit::perMinute($oauthTicketExchangePerMinute)
                ->by($request->ip() . '|' . $ticketHash);
        });

        RateLimiter::for('messages-read', function (Request $request) use ($messagesReadPerMinute) {
            return Limit::perMinute($messagesReadPerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('messages-write', function (Request $request) use ($messagesWritePerMinute) {
            return Limit::perMinute($messagesWritePerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('presence', function (Request $request) use ($presencePerMinute) {
            return Limit::perMinute($presencePerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-chat', function (Request $request) use ($aiChatPerMinute) {
            return Limit::perMinute($aiChatPerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-read', function (Request $request) use ($aiReadPerMinute) {
            return Limit::perMinute($aiReadPerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('diagnostics-submit', function (Request $request) use ($diagnosticsSubmitPerMinute) {
            return Limit::perMinute($diagnosticsSubmitPerMinute)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
