<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\PanicLog;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PanicLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PanicLog::with(['student', 'resolver']);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif (
            $user->hasRole('counselor')
            || $user->hasRole('admin')
            || $user->hasRole('peer_counselor')
        ) {
            // Counselors, admins and peer counselors can see all panic logs.
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
            'session_id' => 'nullable|integer|exists:counseling_sessions,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        // Combine optional notes / session reference into the location field so
        // they aren't silently dropped (PanicLog has no dedicated columns for
        // these yet). If/when columns are added, callers can use them directly.
        $locationParts = [];
        if (!empty($validated['location'])) {
            $locationParts[] = $validated['location'];
        }
        if (!empty($validated['session_id'])) {
            $locationParts[] = 'session:' . $validated['session_id'];
        }
        if (!empty($validated['notes'])) {
            $locationParts[] = 'notes: ' . $validated['notes'];
        }
        $combinedLocation = $locationParts !== [] ? implode(' | ', $locationParts) : null;

        $panicLog = PanicLog::create([
            'student_id' => $request->user()->id,
            'location' => $combinedLocation,
            'resolved' => false,
        ]);

        $recipientsNotified = 0;
        $recipientsFailed = 0;

        if (SystemSettings::getBool('panic_alerts', true)) {
            $student = $request->user();
            $student->loadMissing('profile');
            $studentName = $student->profile?->full_name ?? $student->email ?? ('Student #' . $student->id);

            $locationDisplay = 'Location not provided';
            if ($combinedLocation) {
                $locationDisplay = $combinedLocation;
                if (preg_match('/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/', trim($combinedLocation))) {
                    $locationDisplay .= ' (https://www.google.com/maps/search/?api=1&query='
                        . urlencode(trim($combinedLocation)) . ')';
                }
            }

            $alertMessage = sprintf(
                'EMERGENCY: %s triggered the panic button. Location: %s. Please respond immediately.',
                $studentName,
                $locationDisplay
            );

            // Professional staff only — peer counselors are intentionally excluded so
            // first-line peers are not alerted for crisis / panic workflows.
            // Admins remain included even if misconfigured approval flags mute counselors.
            $recipientIds = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where(function ($inner) {
                        $inner->where(function ($scoped) {
                            $scoped->where('role', 'counselor')
                                ->where('approved', true);
                        })->orWhere('role', 'admin');
                    });
                })
                ->pluck('id')
                ->unique()
                ->values();

            foreach ($recipientIds as $recipientId) {
                try {
                    $notification = Notification::create([
                        'user_id' => (int) $recipientId,
                        'title' => 'Panic Button Triggered!',
                        'message' => $alertMessage,
                        'type' => 'panic',
                        'read' => false,
                    ]);

                    // Real-time push so dashboards/toasts update without waiting
                    // for the 15-second polling interval.
                    try {
                        event(new NotificationCreated($notification));
                    } catch (\Throwable $broadcastException) {
                        // Broadcasting can fail if the broadcaster is misconfigured
                        // (e.g. no Pusher / Reverb in dev). The DB notification is
                        // already persisted, so polling will still surface it.
                        Log::warning('Panic notification broadcast failed', [
                            'panic_log_id' => $panicLog->id,
                            'recipient_id' => (int) $recipientId,
                            'error' => $broadcastException->getMessage(),
                        ]);
                    }

                    $recipientsNotified++;
                } catch (\Throwable $createException) {
                    $recipientsFailed++;
                    Log::error('Failed to create panic notification', [
                        'panic_log_id' => $panicLog->id,
                        'recipient_id' => (int) $recipientId,
                        'error' => $createException->getMessage(),
                    ]);
                }
            }

            Log::info('Panic alert dispatched', [
                'panic_log_id' => $panicLog->id,
                'student_id' => $student->id,
                'recipients_notified' => $recipientsNotified,
                'recipients_failed' => $recipientsFailed,
                'has_location' => $combinedLocation !== null,
            ]);
        } else {
            Log::warning('Panic alert created but panic_alerts setting is disabled', [
                'panic_log_id' => $panicLog->id,
                'student_id' => $request->user()->id,
            ]);
        }

        return response()->json([
            'panic_log' => $panicLog,
            'recipients_notified' => $recipientsNotified,
            'recipients_failed' => $recipientsFailed,
            'alerts_enabled' => SystemSettings::getBool('panic_alerts', true),
        ], 201);
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








