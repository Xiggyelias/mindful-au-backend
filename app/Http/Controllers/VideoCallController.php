<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VideoCallController extends Controller
{
    private const MIN_DURATION_MINUTES = 15;
    private const MAX_DURATION_MINUTES = 120;
    private const DEFAULT_DURATION_MINUTES = 60;
    private const JOIN_EARLY_MINUTES = 15;
    private const JOIN_LATE_GRACE_MINUTES = 0;

    public function authorizeCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
        ]);

        $user = $request->user();
        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (!$this->isParticipant($appointment, (int) $user->id)) {
            return response()->json(['message' => 'Unauthorized for this video call.'], 403);
        }

        if (!$this->isVideoEnabledAppointment((string) ($appointment->notes ?? ''))) {
            return response()->json(['message' => 'This appointment is not configured for video calls.'], 422);
        }

        if (!in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            return response()->json([
                'message' => 'Only scheduled or confirmed appointments can start a video call.',
            ], 422);
        }

        $window = $this->getWindow($appointment);
        if (!$window['can_start']) {
            return response()->json([
                'message' => $window['message'],
                'window' => $window,
            ], 422);
        }

        $session = DB::transaction(function () use ($appointment) {
            $session = CounselingSession::query()
                ->where('student_id', $appointment->student_id)
                ->where('counselor_id', $appointment->counselor_id)
                ->where('session_type', 'video')
                ->whereIn('status', ['pending', 'active'])
                ->where('created_at', '>=', now()->subDay())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (!$session) {
                $session = CounselingSession::create([
                    'student_id' => $appointment->student_id,
                    'counselor_id' => $appointment->counselor_id,
                    'session_type' => 'video',
                    'status' => 'active',
                    'started_at' => now(),
                    'notes' => "Video appointment #{$appointment->id}",
                ]);
            } elseif ($session->status !== 'active') {
                $session->update([
                    'status' => 'active',
                    'started_at' => $session->started_at ?? now(),
                ]);
            } elseif (!$session->started_at) {
                $session->update(['started_at' => now()]);
            }

            return $session->fresh();
        });

        $authorizedDurationMinutes = max(
            1,
            min(
                (int) $window['duration_minutes'],
                (int) floor(((int) $window['ends_in_seconds']) / 60)
            )
        );

        return response()->json([
            'appointment_id' => (int) $appointment->id,
            'session_id' => (int) $session->id,
            'channel' => "video-call-{$appointment->id}",
            'max_duration_minutes' => $authorizedDurationMinutes,
            'window' => $window,
        ]);
    }

    public function end(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
        ]);

        $user = $request->user();
        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (!$this->isParticipant($appointment, (int) $user->id)) {
            return response()->json(['message' => 'Unauthorized for this video call.'], 403);
        }

        $session = CounselingSession::query()
            ->where('student_id', $appointment->student_id)
            ->where('counselor_id', $appointment->counselor_id)
            ->where('session_type', 'video')
            ->whereIn('status', ['pending', 'active'])
            ->latest('id')
            ->first();

        if ($session) {
            $session->update([
                'status' => 'completed',
                'started_at' => $session->started_at ?? now(),
                'ended_at' => now(),
            ]);

            return response()->json([
                'message' => 'Video call ended.',
                'session_id' => (int) $session->id,
                'status' => $session->status,
            ]);
        }

        $latestSession = CounselingSession::query()
            ->where('student_id', $appointment->student_id)
            ->where('counselor_id', $appointment->counselor_id)
            ->where('session_type', 'video')
            ->latest('id')
            ->first();

        return response()->json([
            'message' => 'No active video session found. Call already ended.',
            'session_id' => $latestSession ? (int) $latestSession->id : null,
            'status' => $latestSession?->status,
        ]);
    }

    private function isParticipant(Appointment $appointment, int $userId): bool
    {
        return (int) $appointment->student_id === $userId
            || (int) $appointment->counselor_id === $userId;
    }

    private function isVideoEnabledAppointment(string $notes): bool
    {
        $normalized = Str::lower(trim($notes));
        return !Str::startsWith($normalized, 'physical');
    }

    private function normalizeDurationMinutes(?int $durationMinutes): int
    {
        if (!$durationMinutes) {
            return self::DEFAULT_DURATION_MINUTES;
        }

        return max(
            self::MIN_DURATION_MINUTES,
            min(self::MAX_DURATION_MINUTES, (int) round($durationMinutes))
        );
    }

    private function getWindow(Appointment $appointment): array
    {
        $scheduledAt = Carbon::parse($appointment->scheduled_at);
        $durationMinutes = $this->normalizeDurationMinutes($appointment->duration_minutes);

        $opensAt = $scheduledAt->copy()->subMinutes(self::JOIN_EARLY_MINUTES);
        $closesAt = $scheduledAt->copy()->addMinutes($durationMinutes + self::JOIN_LATE_GRACE_MINUTES);
        $now = now();
        $secondsUntilClose = $now->diffInSeconds($closesAt, false);

        if ($now->lt($opensAt)) {
            return [
                'can_start' => false,
                'message' => sprintf(
                    'Call is locked until %d minutes before the scheduled time.',
                    self::JOIN_EARLY_MINUTES
                ),
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'opens_at' => $opensAt->toIso8601String(),
                'closes_at' => $closesAt->toIso8601String(),
                'duration_minutes' => $durationMinutes,
                'starts_in_seconds' => $now->diffInSeconds($opensAt),
                'ends_in_seconds' => $now->diffInSeconds($closesAt),
            ];
        }

        if ($now->greaterThanOrEqualTo($closesAt) || $secondsUntilClose < 60) {
            return [
                'can_start' => false,
                'message' => 'This call window has closed.',
                'scheduled_at' => $scheduledAt->toIso8601String(),
                'opens_at' => $opensAt->toIso8601String(),
                'closes_at' => $closesAt->toIso8601String(),
                'duration_minutes' => $durationMinutes,
                'starts_in_seconds' => 0,
                'ends_in_seconds' => 0,
            ];
        }

        return [
            'can_start' => true,
            'message' => 'Call is ready.',
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'opens_at' => $opensAt->toIso8601String(),
            'closes_at' => $closesAt->toIso8601String(),
            'duration_minutes' => $durationMinutes,
            'starts_in_seconds' => 0,
            'ends_in_seconds' => $secondsUntilClose,
        ];
    }
}
