<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIntegrationSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = trim((string) env('ACADEMIC_RISK_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            return response()->json([
                'message' => 'Integration signature verification is not configured.',
            ], 503);
        }

        $provided = trim((string) $request->header('X-AUCMS-Signature', ''));
        if ($provided === '') {
            return response()->json(['message' => 'Missing webhook signature.'], 401);
        }

        if (str_starts_with($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $computed = hash_hmac('sha256', (string) $request->getContent(), $secret);
        if (!hash_equals($computed, $provided)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
