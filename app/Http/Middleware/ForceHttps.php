<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldForceHttps()) {
            return $next($request);
        }

        // Allow local container health checks over HTTP.
        if ($request->is('health') || $request->is('live') || $request->is('api/health') || $request->is('api/ready')) {
            return $next($request);
        }

        if ($request->isSecure()) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'HTTPS is required for API access.',
            ], 426);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }

    private function shouldForceHttps(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        if (filter_var((string) env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return app()->environment('production');
    }
}
