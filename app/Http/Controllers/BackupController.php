<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class BackupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(200, $limit));

        $runs = BackupRun::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json($runs);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $exitCode = Artisan::call('system:backup:verify', [
            '--notify' => true,
        ]);

        return response()->json([
            'message' => 'Backup verification command executed.',
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
        ]);
    }

    public function drill(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'path' => 'sometimes|nullable|string|max:255',
        ]);

        $path = trim((string) ($validated['path'] ?? ''));
        $exitCode = $path !== ''
            ? Artisan::call('system:backup:drill', ['path' => $path])
            : Artisan::call('system:backup:drill');

        return response()->json([
            'message' => 'Backup restore drill command executed.',
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
        ]);
    }
}
