<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

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
                $isAnonymous = (bool) (User::query()
                    ->whereKey($appointment->student_id)
                    ->with('profile:id,user_id,anonymous_mode')
                    ->first()?->profile?->anonymous_mode ?? false);
                $session = CounselingSession::create([
                    'student_id' => $appointment->student_id,
                    'counselor_id' => $appointment->counselor_id,
                    'session_type' => 'video',
                    'status' => 'active',
                    'started_at' => now(),
                    'notes' => "Video appointment #{$appointment->id}",
                    'is_anonymous' => $isAnonymous,
                    'anonymous_id' => $isAnonymous
                        ? ('User_' . str_pad((string) ((int) $appointment->student_id % 10000), 4, '0', STR_PAD_LEFT))
                        : null,
                ]);
            } elseif ($session->status !== 'active') {
                $session->update([
                    'status' => 'active',
                    'started_at' => $session->started_at ?? now(),
                    'notes' => "Video appointment #{$appointment->id}",
                ]);
            } elseif (!$session->started_at) {
                $session->update([
                    'started_at' => now(),
                    'notes' => "Video appointment #{$appointment->id}",
                ]);
            } elseif ((string) ($session->notes ?? '') !== "Video appointment #{$appointment->id}") {
                $session->update([
                    'notes' => "Video appointment #{$appointment->id}",
                ]);
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

        $result = DB::transaction(function () use ($appointment) {
            $lockedAppointment = Appointment::query()
                ->with(['student.profile', 'counselor.profile'])
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            $session = CounselingSession::query()
                ->where('student_id', $lockedAppointment->student_id)
                ->where('counselor_id', $lockedAppointment->counselor_id)
                ->where('session_type', 'video')
                ->whereIn('status', ['pending', 'active'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $appointmentMarkedCompleted = false;

            if ($session) {
                $session->update([
                    'status' => 'completed',
                    'started_at' => $session->started_at ?? now(),
                    'ended_at' => now(),
                ]);

                if ($this->shouldMarkAppointmentCompleted($lockedAppointment, $session->fresh())) {
                    $lockedAppointment->update([
                        'status' => 'completed',
                    ]);
                    $appointmentMarkedCompleted = true;
                }

                return [
                    'message' => 'Video call ended.',
                    'session' => $session->fresh(),
                    'appointment' => $lockedAppointment->fresh(['student.profile', 'counselor.profile']),
                    'appointment_marked_completed' => $appointmentMarkedCompleted,
                ];
            }

            $latestSession = CounselingSession::query()
                ->where('student_id', $lockedAppointment->student_id)
                ->where('counselor_id', $lockedAppointment->counselor_id)
                ->where('session_type', 'video')
                ->latest('id')
                ->first();

            if ($this->shouldMarkAppointmentCompleted($lockedAppointment, $latestSession)) {
                $lockedAppointment->update([
                    'status' => 'completed',
                ]);
                $appointmentMarkedCompleted = true;
            }

            return [
                'message' => 'No active video session found. Call already ended.',
                'session' => $latestSession,
                'appointment' => $lockedAppointment->fresh(['student.profile', 'counselor.profile']),
                'appointment_marked_completed' => $appointmentMarkedCompleted,
            ];
        });

        if ($result['appointment_marked_completed']) {
            $this->notifyStudentOnAppointmentCompleted($result['appointment']);
            $this->flushDashboardCaches();
        }

        return response()->json([
            'message' => $result['message'],
            'session_id' => $result['session'] ? (int) $result['session']->id : null,
            'status' => $result['session']?->status,
            'appointment_id' => (int) $result['appointment']->id,
            'appointment_status' => $result['appointment']->status,
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

    private function shouldMarkAppointmentCompleted(
        Appointment $appointment,
        ?CounselingSession $session
    ): bool {
        if (!$session) {
            return false;
        }

        if (in_array((string) $appointment->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ((string) $session->status !== 'completed') {
            return false;
        }

        if (!$session->started_at && !$session->ended_at) {
            return false;
        }

        $sessionNotes = trim((string) ($session->notes ?? ''));
        if ($sessionNotes !== '') {
            return Str::contains($sessionNotes, "Video appointment #{$appointment->id}");
        }

        return true;
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

    private function notifyStudentOnAppointmentCompleted(Appointment $appointment): void
    {
        if (!$appointment->student_id) {
            return;
        }

        $counselorName = optional($appointment->counselor?->profile)->full_name
            ?: ($appointment->counselor?->email ? Str::before($appointment->counselor?->email, '@') : 'your counselor');

        Notification::create([
            'user_id' => $appointment->student_id,
            'title' => 'Appointment Completed',
            'message' => sprintf(
                'Your appointment with %s scheduled for %s has been completed.',
                $counselorName,
                $this->formatAppointmentTime($appointment->scheduled_at)
            ),
            'type' => 'success',
        ]);
    }

    private function formatAppointmentTime(mixed $scheduledAt): string
    {
        if (!$scheduledAt) {
            return 'a recent session';
        }

        try {
            return Carbon::parse($scheduledAt)->format('M j, Y g:i A');
        } catch (\Throwable) {
            return 'a recent session';
        }
    }

    private function flushDashboardCaches(): void
    {
        Cache::forget('analytics:admin:overview:v1');
        Cache::forget('analytics:dashboard:v2');
    }
}
