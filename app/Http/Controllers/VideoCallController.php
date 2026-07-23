<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Appointment;
use App\Models\CounselingCall;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Services\WebPushService;
use App\Support\AnalyticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VideoCallController extends Controller
{
    private const MIN_DURATION_MINUTES = 15;

    private const MAX_DURATION_MINUTES = 120;

    private const DEFAULT_DURATION_MINUTES = 60;

    private const JOIN_EARLY_MINUTES = 15;

    private const JOIN_LATE_GRACE_MINUTES = 15;

    public function __construct(
        private readonly WebPushService $webPush,
    ) {}

    public function authorizeCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
            'call_type' => 'sometimes|in:video,audio',
        ]);

        $user = $request->user();
        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (! $this->isParticipant($appointment, (int) $user->id)) {
            Log::warning('[VideoCall] User not participant', ['user_id' => $user->id, 'appointment_id' => $appointment->id]);

            return response()->json(['message' => 'Unauthorized for this video call.'], 403);
        }

        if (! $this->isVideoEnabledAppointment((string) ($appointment->notes ?? ''))) {
            return response()->json(['message' => 'This appointment is not configured for video calls.'], 422);
        }

        if (! in_array($appointment->status, ['scheduled', 'confirmed'], true)) {
            Log::info('[VideoCall] Appointment status invalid', ['status' => $appointment->status, 'id' => $appointment->id]);

            return response()->json([
                'message' => 'Only scheduled or confirmed appointments can start a video call. Current status: '.$appointment->status,
            ], 422);
        }

        $window = $this->getWindow($appointment);
        if (! $window['can_start']) {
            Log::info('[VideoCall] Window closed', ['window' => $window, 'id' => $appointment->id]);

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
                ->where('is_anonymous', (bool) $appointment->is_anonymous)
                ->whereIn('status', ['pending', 'active'])
                ->where('created_at', '>=', now()->subDay())
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $session) {
                $isAnonymous = (bool) $appointment->is_anonymous;
                $session = CounselingSession::create([
                    'student_id' => $appointment->student_id,
                    'counselor_id' => $appointment->counselor_id,
                    'session_type' => 'video',
                    'status' => 'active',
                    'started_at' => now(),
                    'notes' => "Video appointment #{$appointment->id}",
                    'is_anonymous' => $isAnonymous,
                    'anonymous_id' => $isAnonymous
                        ? ($appointment->anonymous_id ?: 'User_'.str_pad((string) ((int) $appointment->id % 10000), 4, '0', STR_PAD_LEFT))
                        : null,
                ]);
            } elseif ($session->status !== 'active') {
                $session->update([
                    'status' => 'active',
                    'started_at' => $session->started_at ?? now(),
                    'notes' => "Video appointment #{$appointment->id}",
                ]);
            } elseif (! $session->started_at) {
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

        $isStudent = (int) $appointment->student_id === (int) $user->id && $user->hasRole('student');
        $isCounselor = $user->hasRole('counselor') && (int) $appointment->counselor_id === (int) $user->id;

        if ($isStudent || $isCounselor) {
            $callTypeResult = $this->resolveCallType(
                (string) ($validated['call_type'] ?? 'video'),
                $appointment
            );

            if ($callTypeResult === null) {
                return response()->json([
                    'message' => 'This appointment is booked as audio-only.',
                ], 422);
            }

            $callerRole = $isStudent ? CounselingCall::CALLER_STUDENT : CounselingCall::CALLER_COUNSELOR;

            $conflict = DB::transaction(function () use ($appointment, $callTypeResult, $callerRole) {
                $existingPending = CounselingCall::query()
                    ->where('appointment_id', $appointment->id)
                    ->where('status', CounselingCall::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();

                // The other participant already rang this appointment and is waiting on an
                // answer — don't let the callee place their own outgoing call on top of it
                // (that would silently cancel the incoming ring and flip who's "calling" who).
                // They should accept or decline the existing invite instead.
                if ($existingPending && $existingPending->caller_role !== $callerRole) {
                    return true;
                }

                CounselingCall::query()
                    ->where('appointment_id', $appointment->id)
                    ->where('status', CounselingCall::STATUS_PENDING)
                    ->lockForUpdate()
                    ->delete();

                CounselingCall::create([
                    'appointment_id' => $appointment->id,
                    'student_id' => $appointment->student_id,
                    'counselor_id' => $appointment->counselor_id,
                    'status' => CounselingCall::STATUS_PENDING,
                    'call_type' => $callTypeResult,
                    'caller_role' => $callerRole,
                ]);

                return false;
            });

            if ($conflict) {
                return response()->json([
                    'message' => 'The other participant is already calling you on this appointment. Accept or decline that call first.',
                ], 409);
            }

            $isAudio = $callTypeResult === 'audio';
            $notifyUserId = $isStudent ? (int) $appointment->counselor_id : (int) $appointment->student_id;
            $notifyRoute = $isStudent ? '/counselor/video' : '/student/video-call';
            $notifyBody = $isStudent
                ? sprintf('A student is calling you for %s.', $isAudio ? 'an audio session' : 'a video session')
                : sprintf('Your counselor is calling you for %s.', $isAudio ? 'an audio session' : 'a video session');

            try {
                $this->webPush->sendToUser(
                    $notifyUserId,
                    $isAudio ? 'Incoming audio call' : 'Incoming video call',
                    $notifyBody,
                    $notifyRoute,
                    [
                        'tag' => 'cms-call-apt-'.(int) $appointment->id,
                        'urgency' => 'high',
                        'requireInteraction' => true,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[VideoCall] web push failed', [
                    'appointment_id' => $appointment->id,
                    'caller_role' => $callerRole,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $notification = Notification::create([
                    'user_id' => $notifyUserId,
                    'title' => $isAudio ? 'Incoming audio call' : 'Incoming video call',
                    'message' => $notifyBody,
                    'type' => 'warning',
                    'meta' => [
                        'kind' => 'incoming_call',
                        'appointment_id' => (int) $appointment->id,
                        'call_type' => $callTypeResult,
                        'caller_role' => $callerRole,
                    ],
                ]);
                event(new NotificationCreated($notification));
            } catch (\Throwable $e) {
                Log::warning('[VideoCall] in-app notification failed', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

        if (! $this->isParticipant($appointment, (int) $user->id)) {
            return response()->json(['message' => 'Unauthorized for this video call.'], 403);
        }

        $result = DB::transaction(function () use ($appointment) {
            $lockedAppointment = Appointment::query()
                ->with(['student.profile', 'counselor.profile'])
                ->lockForUpdate()
                ->findOrFail($appointment->id);

            // Clean up any pending counseling calls and notify callee of missed call
            $pendingCall = CounselingCall::query()
                ->where('appointment_id', $lockedAppointment->id)
                ->where('status', CounselingCall::STATUS_PENDING)
                ->first();

            if ($pendingCall) {
                $pendingCall->update(['status' => 'declined']);
                $isStudentCaller = $pendingCall->caller_role === CounselingCall::CALLER_STUDENT;
                $notifyUserId = $isStudentCaller ? (int) $lockedAppointment->counselor_id : (int) $lockedAppointment->student_id;
                $notifyBody = $isStudentCaller ? 'Missed audio/video call from student.' : 'Missed audio/video call from counselor.';

                try {
                    $this->webPush->sendToUser(
                        $notifyUserId,
                        'Missed call',
                        $notifyBody,
                        $isStudentCaller ? '/counselor/video' : '/student/video-call',
                        [
                            'tag' => 'cms-call-apt-'.(int) $lockedAppointment->id,
                            'urgency' => 'high',
                            'requireInteraction' => false,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[VideoCall] web push for missed call failed', ['error' => $e->getMessage()]);
                }
            }

            $session = CounselingSession::query()
                ->where('student_id', $lockedAppointment->student_id)
                ->where('counselor_id', $lockedAppointment->counselor_id)
                ->where('session_type', 'video')
                ->where('is_anonymous', (bool) $lockedAppointment->is_anonymous)
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

                if ($this->shouldMarkAppointmentCompleted($lockedAppointment, $session->fresh(), true)) {
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
                ->where('is_anonymous', (bool) $lockedAppointment->is_anonymous)
                ->latest('id')
                ->first();

            if ($this->shouldMarkAppointmentCompleted($lockedAppointment, $latestSession, false)) {
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

        return ! Str::startsWith($normalized, 'physical');
    }

    /**
     * Resolve the effective call type for an appointment, enforcing anonymous→audio
     * and audio-only booking rules.
     *
     * Returns the resolved call type string ('video'|'audio'), or null if the
     * requested type conflicts with an audio-only booking (caller should 422).
     */
    private function resolveCallType(string $requested, Appointment $appointment): ?string
    {
        if (! in_array($requested, ['video', 'audio'], true)) {
            $requested = 'video';
        }

        // Anonymous appointments are always audio-only.
        if ($appointment->is_anonymous) {
            return 'audio';
        }

        $booked = (string) ($appointment->call_type ?? '');

        if ($booked === 'audio' && $requested === 'video') {
            // Signal to the caller that they should return a 422.
            return null;
        }

        if ($booked === 'audio') {
            return 'audio';
        }

        return $requested;
    }

    private function shouldMarkAppointmentCompleted(
        Appointment $appointment,
        ?CounselingSession $session,
        bool $allowInferenceWithoutAppointmentNote = false
    ): bool {
        if (! $session) {
            return false;
        }

        if (in_array((string) $appointment->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ((string) $session->status !== 'completed') {
            return false;
        }

        if (! $this->counselingSessionMatchesAppointmentParticipants($appointment, $session)) {
            return false;
        }

        if (! $session->started_at && ! $session->ended_at) {
            return false;
        }

        $sessionNotes = trim((string) ($session->notes ?? ''));

        if ($this->counselingSessionNotesReferenceAppointment($appointment, $sessionNotes)) {
            return true;
        }

        if (! $allowInferenceWithoutAppointmentNote) {
            return false;
        }

        if ($sessionNotes !== '' && $this->counselingSessionNotesExplicitlyReferToAnotherAppointmentId($appointment, $sessionNotes)) {
            return false;
        }

        return true;
    }

    private function counselingSessionMatchesAppointmentParticipants(
        Appointment $appointment,
        CounselingSession $session
    ): bool {
        return (int) $session->student_id === (int) $appointment->student_id
            && (int) $session->counselor_id === (int) $appointment->counselor_id
            && (string) $session->session_type === 'video'
            && (bool) $session->is_anonymous === (bool) $appointment->is_anonymous;
    }

    private function counselingSessionNotesReferenceAppointment(Appointment $appointment, string $sessionNotes): bool
    {
        if ($sessionNotes === '') {
            return false;
        }

        $id = (int) $appointment->id;
        $idPattern = preg_quote((string) $id, '/');

        return (bool) preg_match('/video\s+appointment\s*#\s*'.$idPattern.'(?!\d)/iu', $sessionNotes)
            || (bool) preg_match('/appointment\s*#\s*'.$idPattern.'(?!\d)/iu', $sessionNotes);
    }

    private function counselingSessionNotesExplicitlyReferToAnotherAppointmentId(
        Appointment $appointment,
        string $sessionNotes
    ): bool {
        $myId = (int) $appointment->id;
        $pattern = '/(?:video\s+)?appointment\s*#\s*(\d+)(?!\d)/iu';

        if (! preg_match_all($pattern, $sessionNotes, $matches)) {
            return false;
        }

        foreach ($matches[1] as $digits) {
            if ((int) $digits !== $myId) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDurationMinutes(?int $durationMinutes): int
    {
        if (! $durationMinutes) {
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
        if (! $appointment->student_id) {
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
            'meta' => [
                'appointment_id' => (int) $appointment->id,
                'path' => '/student/appointments',
            ],
        ]);
    }

    private function formatAppointmentTime(mixed $scheduledAt): string
    {
        if (! $scheduledAt) {
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
        AnalyticsCache::clear();
    }
}
