<?php

namespace App\Http\Middleware;

use App\Models\DataAccessLog;
use App\Models\User;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AuditDataAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $user = $request->user();
        if (! $user instanceof User) {
            return $response;
        }

        if (! $this->shouldLog($request)) {
            return $response;
        }

        if (! SystemSettings::getBool('audit_logging', true)) {
            return $response;
        }

        if (! Schema::hasTable('data_access_logs')) {
            return $response;
        }

        [$resourceType, $resourceId] = $this->extractPrimaryResource($request);

        try {
            DataAccessLog::query()->create([
                'user_id' => $user->id,
                'method' => strtoupper((string) $request->method()),
                'path' => (string) $request->path(),
                'status_code' => $response->getStatusCode(),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'metadata' => [
                    'route_name' => optional($request->route())->getName(),
                    'query' => $request->query(),
                ],
            ]);
        } catch (\Throwable) {
            // Keep request flow intact when audit logging fails.
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $path = ltrim((string) $request->path(), '/');

        if ($path === '' || str_starts_with($path, 'api/health') || str_starts_with($path, 'api/ready')) {
            return false;
        }

        if (str_starts_with($path, 'api/data-access-logs')) {
            return false;
        }

        if ($path === 'api/me/presence') {
            return false;
        }

        if (in_array(strtoupper((string) $request->method()), ['OPTIONS', 'HEAD'], true)) {
            return false;
        }

        return true;
    }

    private function extractPrimaryResource(Request $request): array
    {
        $route = $request->route();
        if (! $route) {
            return [null, null];
        }

        foreach ($route->parameters() as $key => $value) {
            if (is_scalar($value)) {
                return [(string) $key, (string) $value];
            }
            if (is_object($value) && method_exists($value, 'getKey')) {
                return [(string) $key, (string) $value->getKey()];
            }
        }

        return [null, null];
    }
}
