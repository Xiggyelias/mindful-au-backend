<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->routeRequiresAdmin($request)) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return $next($request);
        }

        if (!SystemSettings::getBool('session_timeout', false)) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) env('ADMIN_SESSION_TIMEOUT_MINUTES', 30));
        $cutoff = now()->subMinutes($timeoutMinutes);
        $lastUsedAtRaw = $user->last_seen_at ?? $token->last_used_at ?? $token->created_at;
        $lastUsedAt = null;

        if ($lastUsedAtRaw instanceof \DateTimeInterface) {
            $lastUsedAt = Carbon::instance($lastUsedAtRaw);
        } elseif (is_string($lastUsedAtRaw) && trim($lastUsedAtRaw) !== '') {
            try {
                $lastUsedAt = Carbon::parse($lastUsedAtRaw);
            } catch (\Throwable) {
                $lastUsedAt = null;
            }
        }

        if ($lastUsedAt instanceof CarbonInterface && $lastUsedAt->lt($cutoff)) {
            $token->delete();

            return response()->json([
                'message' => 'Session expired due to inactivity. Please sign in again.',
            ], 401);
        }
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function routeRequiresAdmin(Request $request): bool
    {
        $route = $request->route();
        if (!$route) {
            return false;
        }

        $middlewares = $route->gatherMiddleware();
        foreach ($middlewares as $middleware) {
            $value = (string) $middleware;
            if ($value === 'admin' || str_starts_with($value, 'admin:')) {
                return true;
            }

            if ($value === \App\Http\Middleware\AdminMiddleware::class) {
                return true;
            }
        }

        return false;
    }
}
