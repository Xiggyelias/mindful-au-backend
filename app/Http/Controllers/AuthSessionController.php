<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TokenSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthSessionController extends Controller
{
    public function __construct(private readonly TokenSessionService $tokenSessionService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        return response()->json([
            'sessions' => $this->tokenSessionService->listSessions($user),
        ]);
    }

    public function destroy(Request $request, int $sessionId): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $deleted = $this->tokenSessionService->revokeSession($user, $sessionId);
        if (! $deleted) {
            return response()->json([
                'message' => 'Unable to revoke that session.',
            ], 422);
        }

        return response()->json([
            'message' => 'Session revoked.',
            'sessions' => $this->tokenSessionService->listSessions($user),
        ]);
    }

    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! $user->currentAccessToken()) {
            return response()->json([
                'message' => 'Current token context was not available for this request.',
            ], 422);
        }

        $deletedCount = $this->tokenSessionService->logoutOtherDevices($user);

        return response()->json([
            'message' => 'Other sessions logged out successfully.',
            'deleted_count' => $deletedCount,
            'sessions' => $this->tokenSessionService->listSessions($user),
        ]);
    }
}
