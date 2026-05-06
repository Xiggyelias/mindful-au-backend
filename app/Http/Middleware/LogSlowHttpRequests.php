<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs API requests whose server-side handling exceeds a threshold (default 1000 ms).
 * Enable tuning via SLOW_REQUEST_LOG_MS in .env.
 */
class LogSlowHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $threshold = max(100, (int) env('SLOW_REQUEST_LOG_MS', 1000));
        $ms = (int) round((microtime(true) - $start) * 1000);

        if ($ms >= $threshold && $request->is('api/*')) {
            Log::warning('Slow HTTP request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => $ms,
                'user_id' => $request->user()?->id,
            ]);
        }

        return $response;
    }
}
