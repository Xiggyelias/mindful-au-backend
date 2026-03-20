<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CounselorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('counselor') && !$user->hasRole('admin'))) {
            return response()->json(['message' => 'Counselor access required'], 403);
        }

        return $next($request);
    }
}








