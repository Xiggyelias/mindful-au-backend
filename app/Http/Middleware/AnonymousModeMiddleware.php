<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnonymousModeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->profile && $user->profile->anonymous_mode) {
            // If user is in anonymous mode and not admin/counselor, hide their identity
            if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
                // Modify response to anonymize user data
                $response = $next($request);
                
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                    
                    if (isset($data['user'])) {
                        $data['user']['profile']['full_name'] = 'Anonymous #' . substr($user->id, 0, 4);
                        $data['user']['email'] = 'anonymous@africau.edu';
                    }
                    
                    $response->setData($data);
                }
                
                return $response;
            }
        }

        return $next($request);
    }
}








