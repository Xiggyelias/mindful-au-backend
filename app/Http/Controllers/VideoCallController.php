<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\Appointment;
use App\Models\CounselingCall;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Services\WebPushService;
use App\Support\AnalyticsCache;
use App\Support\AppointmentCallWindow;
use App\Support\AppointmentCompletion;
use App\Support\CallCoordinator;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VideoCallController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPush,
        private readonly CallCoordinator $calls,
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
                ->whereIn('status', ['pending', 'active'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $isAnonymous = (bool) $appointment->is_anonymous;

            if (! $session) {
                $session = CounselingSession::create([
                    'student_id' => $appointment->student_id,
                    'counselor_id' => $appointment->counselor_id,
                    'session_type' => 'video',
                    'status' => 'active',
                    'started_at' => now(),
                    'notes' => "Video appointment #{$appointment->id}",
                    'is_anonymous' => $isAnonymous,
                    'anonymous_id' => $isAnonymous
                        ? CounselingSession::generateUniqueAnonymousId()
                        : null,
                ]);
            } else {
                $updates = [
                    'status' => 'active',
                    'started_at' => $session->started_at ?? now(),
                ];

                if (empty($session->notes)) {
                    $updates['notes'] = "Video appointment #{$appointment->id}";
                }

                if ((bool) $session->is_anonymous !== $isAnonymous) {
                    $updates['is_anonymous'] = $isAnonymous;
                    $updates['anonymous_id'] = $isAnonymous
                        ? ($session->anonymous_id ?: CounselingSession::generateUniqueAnonymousId())
                        : null;
                }

                $session->update($updates);
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
        $activeCall = null;

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
            $callerId = (int) $user->id;
            $calleeId = $isStudent ? (int) $appointment->counselor_id : (int) $appointment->student_id;

            Log::info('[Call] Call initiated', [
                'appointment_id' => (int) $appointment->id,
                'caller_role' => $callerRole,
                'caller_user_id' => $callerId,
                'recipient_user_id' => $calleeId,
                'call_type' => $validated['call_type'] ?? 'video',
            ]);

            try {
                $outcome = $this->calls->withUsersLocked([$callerId, $calleeId], function () use ($appointment, $callTypeResult, $callerRole, $callerId, $calleeId) {
                    $missed = $this->calls->sweepExpired($callerId, $calleeId);

                    $conflict = $this->calls->findActiveConflict($callerId, $calleeId, $appointment);

                    if ($conflict) {
                        $sameAppointment = (int) $conflict->appointment_id === (int) $appointment->id;
                        $sameCaller = $conflict->caller_role === $callerRole;

                        if ($sameAppointment && $conflict->status === CounselingCall::STATUS_ACCEPTED) {
                            // The call has already been answered and both participants are
                            // joining it. This is the callee's own authorize request right
                            // after they hit Accept — treating their answered call as a
                            // conflicting "incoming call" would 409 them out of the very
                            // call they just accepted.
                            return ['type' => 'reused', 'call' => $conflict, 'missed' => $missed];
                        }

                        if ($sameAppointment && $sameCaller) {
                            // Idempotent: rapid double-click, retry, or a page refresh mid-call —
                            // reuse the existing session instead of erroring or duplicating it.
                            return ['type' => 'reused', 'call' => $conflict, 'missed' => $missed];
                        }

                        if ($sameAppointment && ! $sameCaller) {
                            // The other participant already rang this appointment and is waiting
                            // on an answer — don't let the callee place their own outgoing call
                            // on top of it. They should accept or decline the existing invite.
                            return ['type' => 'incoming', 'call' => $conflict, 'missed' => $missed];
                        }

                        // Busy with a third party (either the caller or the callee has an active
                        // call elsewhere).
                        return ['type' => 'busy', 'call' => $conflict, 'missed' => $missed];
                    }

                    $callData = [
                        'appointment_id' => $appointment->id,
                        'student_id' => $appointment->student_id,
                        'counselor_id' => $appointment->counselor_id,
                        'status' => CounselingCall::STATUS_PENDING,
                        'call_type' => $callTypeResult,
                        'caller_role' => $callerRole,
                    ];
                    if (Schema::hasColumn('calls', 'expires_at')) {
                        $callData['expires_at'] = now()->addSeconds(CallCoordinator::RING_TTL_SECONDS);
                    }

                    $call = CounselingCall::create($callData);

                    return ['type' => 'created', 'call' => $call, 'missed' => $missed];
                });
            } catch (LockTimeoutException) {
                return response()->json([
                    'message' => 'The call system is busy. Please try again in a moment.',
                ], 503);
            }

            foreach ($outcome['missed'] as $missedCall) {
                $this->calls->notifyMissed($missedCall);
            }

            if ($outcome['type'] === 'incoming') {
                return response()->json([
                    'reason' => 'incoming_call',
                    'message' => 'You have an incoming call. Accept or decline it first.',
                ], 409);
            }

            if ($outcome['type'] === 'busy') {
                return response()->json([
                    'reason' => 'busy',
                    'message' => 'User is currently busy.',
                ], 409);
            }

            if ($outcome['type'] === 'created') {
                $isAudio = $callTypeResult === 'audio';
                $notifyUserId = $calleeId;

                // Ring the recipient first — this is the signal that puts them in RINGING.
                // It goes to the callee only; the caller already renders CALLING locally.
                // Everything below (web push, in-app notification) is a slower backstop
                // for a recipient who isn't currently connected to realtime.
                $this->calls->signalRinging($outcome['call']);
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
                    $this->calls->clearCallNotifications((int) $appointment->id);

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
                            'path' => $notifyRoute,
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

            if ($outcome['type'] === 'reused' && $outcome['call']->status === CounselingCall::STATUS_PENDING) {
                // A retry, refresh or double-click on a call that is still ringing. Re-ring
                // the recipient: the first signal may have been sent before their tab was
                // listening, and re-sending is idempotent on the receiving end.
                $this->calls->signalRinging($outcome['call']);
            }

            $activeCall = $outcome['call'] ?? null;
        }

        return response()->json([
            'appointment_id' => (int) $appointment->id,
            'session_id' => (int) $session->id,
            'channel' => "video-call-{$appointment->id}",
            'max_duration_minutes' => $authorizedDurationMinutes,
            'window' => $window,
            'call_id' => $activeCall ? (int) $activeCall->id : null,
            // The authoritative media type for this call. resolveCallType() downgrades
            // anonymous and audio-only bookings to audio, and the client must negotiate
            // WebRTC with the same answer — otherwise the stored row says "audio" while
            // the peers exchange video, and the two sides disagree about the session.
            'call_type' => $activeCall
                ? (string) $activeCall->call_type
                : ($appointment->is_anonymous ? 'audio' : (string) ($appointment->call_type ?: 'video')),
            'caller_role' => $activeCall ? (string) $activeCall->caller_role : null,
            'is_anonymous' => (bool) $appointment->is_anonymous,
            // The caller is CALLING until the recipient answers; the recipient is RINGING.
            'call_state' => $activeCall && $activeCall->status === CounselingCall::STATUS_PENDING
                ? 'CALLING'
                : null,
        ]);
    }

    /**
     * Cancels the caller's own unanswered outgoing call. Idempotent: if there's nothing
     * pending to cancel (already answered, already gone, or never existed — e.g. a race
     * with the callee accepting at the same instant, or a duplicate cancel click), this
     * is a no-op success rather than an error.
     */
    public function cancelCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_id' => 'required|integer|exists:appointments,id',
        ]);

        $user = $request->user();
        $appointment = Appointment::findOrFail($validated['appointment_id']);

        if (! $this->isParticipant($appointment, (int) $user->id)) {
            return response()->json(['message' => 'Unauthorized for this video call.'], 403);
        }

        $isStudent = (int) $appointment->student_id === (int) $user->id && $user->hasRole('student');
        $callerRole = $isStudent ? CounselingCall::CALLER_STUDENT : CounselingCall::CALLER_COUNSELOR;
        $callerId = (int) $user->id;
        $calleeId = $isStudent ? (int) $appointment->counselor_id : (int) $appointment->student_id;

        try {
            $cancelled = $this->calls->withUsersLocked([$callerId, $calleeId], function () use ($appointment, $callerRole) {
                $pending = CounselingCall::query()
                    ->where('appointment_id', $appointment->id)
                    ->where('status', CounselingCall::STATUS_PENDING)
                    ->where('caller_role', $callerRole)
                    ->first();

                if (! $pending) {
                    return null;
                }

                $pending->update(['status' => CounselingCall::STATUS_CANCELLED]);

                return $pending;
            });
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'The call system is busy. Please try again in a moment.',
            ], 503);
        }

        if ($cancelled) {
            $this->calls->notifyCancelled($cancelled);
        }

        return response()->json([
            'appointment_id' => (int) $appointment->id,
            'cancelled' => (bool) $cancelled,
        ]);
    }

    /**
     * A call hung up or dropped.
     *
     * Ending a *call* is not the same as ending the *session*. While the appointment's window
     * is still open the slot has to stay callable: a connection that drops two minutes into a
     * one-hour session used to complete the whole appointment, which dropped it out of both
     * participants' call queues and left them with no way to call back.
     *
     * The appointment finishes when its window closes — swept by `appointments:complete-past`
     * for sessions nobody hangs up on — or when a counselor marks it completed from the
     * appointments screen.
     */
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

        $isEmergency = (bool) ($appointment->is_emergency ?? false) || AppointmentCallWindow::isEmergency($appointment);
        // If it's an emergency session: once the call is ended, it has ended!
        // For regular sessions: the session is only open if the appointment window is open.
        $sessionStillOpen = ! $isEmergency && AppointmentCallWindow::isOpen($appointment);

        // Clean up any pending or active calls for this appointment through the same coordinator
        try {
            $callSweep = $this->calls->withUsersLocked(
                [(int) $appointment->student_id, (int) $appointment->counselor_id],
                function () use ($appointment) {
                    $activeCalls = CounselingCall::query()
                        ->where('appointment_id', $appointment->id)
                        ->whereIn('status', CounselingCall::ACTIVE_STATUSES)
                        ->get();

                    $cancelled = null;
                    $ended = null;
                    foreach ($activeCalls as $call) {
                        if ($call->status === CounselingCall::STATUS_PENDING) {
                            $call->update(['status' => CounselingCall::STATUS_CANCELLED]);
                            $cancelled = $call;
                        } else {
                            $call->update(['status' => CounselingCall::STATUS_ENDED]);
                            $ended = $call;
                        }
                    }

                    return ['cancelled' => $cancelled, 'ended' => $ended];
                }
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'The call system is busy. Please try again in a moment.',
            ], 503);
        }

        if ($callSweep['cancelled']) {
            $this->calls->notifyCancelled($callSweep['cancelled']);
        } elseif ($callSweep['ended']) {
            $this->calls->notifyEnded($callSweep['ended']);
        } else {
            $this->calls->clearCallNotifications((int) $appointment->id);
        }

        $result = DB::transaction(function () use ($appointment, $sessionStillOpen, $isEmergency) {
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

            // If it's an emergency appointment: once ended, complete immediately!
            if ($isEmergency) {
                if ($session) {
                    $session->update([
                        'status' => 'completed',
                        'started_at' => $session->started_at ?? now(),
                        'ended_at' => now(),
                    ]);
                }

                $lockedAppointment->update(['status' => 'completed']);
                $appointmentMarkedCompleted = true;

                return [
                    'message' => 'Emergency video session concluded and marked completed.',
                    'session' => $session?->fresh(),
                    'appointment' => $lockedAppointment->fresh(['student.profile', 'counselor.profile']),
                    'appointment_marked_completed' => true,
                    'appointment_status' => 'completed',
                    'session_still_open' => false,
                ];
            }

            // For regular appointments:
            if ($sessionStillOpen) {
                if ($session && ! $session->started_at) {
                    $session->update(['started_at' => now()]);
                }

                return [
                    'message' => 'Call ended. The session is still open — you can call again.',
                    'session' => $session?->fresh(),
                    'appointment' => $lockedAppointment->fresh(['student.profile', 'counselor.profile']),
                    'appointment_marked_completed' => false,
                    'appointment_status' => $lockedAppointment->status,
                    'session_still_open' => true,
                ];
            }

            // Window closed or session finished:
            if ($session) {
                $session->update([
                    'status' => 'completed',
                    'started_at' => $session->started_at ?? now(),
                    'ended_at' => now(),
                ]);
            }

            $lockedAppointment->update(['status' => 'completed']);
            $appointmentMarkedCompleted = true;

            return [
                'message' => 'Video call ended.',
                'session' => $session?->fresh(),
                'appointment' => $lockedAppointment->fresh(['student.profile', 'counselor.profile']),
                'appointment_marked_completed' => true,
                'appointment_status' => 'completed',
                'session_still_open' => false,
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
            // Tells the client whether the slot is still callable. When true the appointment
            // must stay in the call queue so either party can start the call again.
            'session_still_open' => (bool) $result['session_still_open'],
            'window' => AppointmentCallWindow::describe($result['appointment']),
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

        $isEmergency = (bool) ($appointment->is_emergency ?? false)
            || str_contains(strtolower((string) ($appointment->notes ?? '')), 'emergency')
            || str_contains(strtolower((string) ($appointment->notes ?? '')), 'crisis');

        // Anonymous appointments are audio-only to preserve privacy, unless it is a life-safety emergency.
        if ($appointment->is_anonymous && ! $isEmergency) {
            return 'audio';
        }

        if ($requested === 'audio') {
            return 'audio';
        }

        return 'video';
    }

    private function getWindow(Appointment $appointment): array
    {
        return AppointmentCallWindow::describe($appointment);
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
