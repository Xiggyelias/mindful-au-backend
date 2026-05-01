<?php

namespace App\Http\Controllers;

use App\Models\PanicLog;
use App\Models\Notification;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PanicLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PanicLog::with(['student', 'resolver']);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('counselor') || $user->hasRole('admin')) {
            // Counselors and admins can see all panic logs
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $logs = $query->latest()->get();

        return response()->json($logs);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can create panic logs'], 403);
        }

        $validated = $request->validate([
            'location' => 'nullable|string',
        ]);

        $panicLog = PanicLog::create([
            'student_id' => $request->user()->id,
            'location' => $validated['location'] ?? null,
            'resolved' => false,
        ]);

        if (SystemSettings::getBool('panic_alerts', true)) {
            $user = $request->user();
            $user->loadMissing('profile');
            $studentName = $user->profile?->full_name ?? $user->email;
            $rawLocation = $validated['location'] ?? null;
            
            $locationDisplay = 'Location not provided';
            if ($rawLocation) {
                // If it looks like coordinates (lat, lng), provide a map link in the message if possible
                // or just keep it as a clear string for the counselor to see.
                $locationDisplay = $rawLocation;
                if (preg_match('/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/', $rawLocation)) {
                    $locationDisplay .= " (https://www.google.com/maps/search/?api=1&query=" . urlencode($rawLocation) . ")";
                }
            }

            $alertMessage = sprintf(
                "EMERGENCY: %s triggered the panic button. Location: %s. Please respond immediately.",
                $studentName,
                $locationDisplay
            );

            // Create notifications for all approved counselors and admins.
            $counselors = \App\Models\User::whereHas('roles', function($query) {
                $query->whereIn('role', ['counselor', 'admin'])->where('approved', true);
            })->get();

            foreach ($counselors as $counselor) {
                Notification::create([
                    'user_id' => $counselor->id,
                    'title' => 'Panic Button Triggered!',
                    'message' => $alertMessage,
                    'type' => 'panic',
                ]);
            }
        }

        return response()->json($panicLog, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $panicLog = PanicLog::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'resolved' => 'sometimes|boolean',
        ]);

        if (isset($validated['resolved']) && $validated['resolved'] && !$panicLog->resolved) {
            $panicLog->update([
                'resolved' => true,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
        } else {
            $panicLog->update($validated);
        }

        return response()->json($panicLog->load(['student', 'resolver']));
    }
}








