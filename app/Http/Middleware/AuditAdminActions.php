<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminActions
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // Avoid noisy recursion when mutating logs directly.
        if (str_starts_with($request->path(), 'api/activity-logs')) {
            return $next($request);
        }

        if (!$this->routeRequiresAdmin($request)) {
            return $next($request);
        }

        $response = $next($request);

        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return $response;
        }

        if (!SystemSettings::getBool('audit_logging', true)) {
            return $response;
        }

        try {
            ActivityLog::query()->create([
                'user_id' => $user->id,
                'action' => sprintf('admin.%s.%s', strtolower($request->method()), str_replace('/', '.', $request->path())),
                'description' => sprintf('%s %s', $request->method(), $request->path()),
                'type' => 'system',
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'metadata' => [
                    'status' => $response->getStatusCode(),
                ],
            ]);
        } catch (\Throwable) {
            // Do not break request flow if audit logging fails.
        }

        return $response;
    }

    private function routeRequiresAdmin(Request $request): bool
    {
        $route = $request->route();
        if (!$route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
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
