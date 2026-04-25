<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\TokenSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackDeviceSession
{
    public function __construct(private readonly TokenSessionService $tokenSessionService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && !$this->tokenSessionService->isRequestBoundToToken($request, $user)) {
            return response()->json([
                'message' => 'This session is not valid for the current device. Please sign in again.',
            ], 401);
        }

        if ($user instanceof User) {
            $this->tokenSessionService->touchCurrentToken($request, $user);
        }

        return $next($request);
    }
}
