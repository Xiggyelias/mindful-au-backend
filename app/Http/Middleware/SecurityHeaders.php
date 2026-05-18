<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const API_NO_STORE_CACHE_CONTROL = 'no-store, no-cache, must-revalidate, max-age=0, private';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        $response->headers->set('X-Download-Options', 'noopen');
        $response->headers->set('X-Frame-Options', (string) env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'));
        $response->headers->set('Referrer-Policy', (string) env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Origin-Agent-Cluster', '?1');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Chat file downloads are served from the API subdomain and loaded by the
        // frontend subdomain — allow cross-origin access for that route only.
        $corp = $request->is('api/chat/files/*/content')
            ? 'cross-origin'
            : 'same-origin';
        $response->headers->set('Cross-Origin-Resource-Policy', $corp);

        if ($request->is('api/*')) {
            // Avoid sensitive API payloads being cached by browsers/intermediaries.
            $response->headers->set('Cache-Control', self::API_NO_STORE_CACHE_CONTROL);
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        $permissionsPolicy = trim((string) env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'));
        if ($permissionsPolicy !== '') {
            $response->headers->set('Permissions-Policy', $permissionsPolicy);
        }

        $contentSecurityPolicy = trim((string) env(
            'SECURITY_CONTENT_SECURITY_POLICY',
            "default-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        ));
        if ($contentSecurityPolicy !== '') {
            $response->headers->set('Content-Security-Policy', $contentSecurityPolicy);
        }

        $shouldSetHsts = $request->isSecure()
            || filter_var(env('SECURITY_FORCE_HSTS', false), FILTER_VALIDATE_BOOL);

        if ($shouldSetHsts) {
            $maxAge = max(0, (int) env('SECURITY_HSTS_MAX_AGE', 31536000));
            $hsts = "max-age={$maxAge}";

            if (filter_var(env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true), FILTER_VALIDATE_BOOL)) {
                $hsts .= '; includeSubDomains';
            }
            if (filter_var(env('SECURITY_HSTS_PRELOAD', false), FILTER_VALIDATE_BOOL)) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }
}
