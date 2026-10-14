<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\Appointment;
use App\Models\CounselingCall;
use App\Models\Notification;
use App\Services\WebPushService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "is this user available to start/receive a call" and for
 * serializing concurrent call-start attempts so two people pressing Call at the same
 * instant can never create two call sessions between the same pair (or leave either of
 * them double-booked with a third party).
 *
 * Concurrency model: Cache::lock() (works with the array/file/redis/database/memcached
 * drivers — whatever CACHE_STORE is configured) keyed per user id, acquired in ascending
 * id order for both participants before any read-then-write decision is made. This fully
 * serializes every call-start/cancel/accept touching a given user, not just requests
 * between the same two users — so a callee can't end up "busy" twice via two different
 * simultaneous callers either.
 */
class CallCoordinator
{
    /** How long an unanswered call rings before it's considered missed. */
    public const RING_TTL_SECONDS = 45;

    private const LOCK_TTL_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly WebPushService $webPush,
        private readonly CallSignalBroadcaster $signals,
    ) {}

    /** The user id of whoever placed the call. */
    public function callerId(CounselingCall $call): int
    {
        return $call->caller_role === CounselingCall::CALLER_STUDENT
            ? (int) $call->student_id
            : (int) $call->counselor_id;
    }

    /** The user id of whoever is being rung. */
    public function calleeId(CounselingCall $call): int
    {
        return $call->caller_role === CounselingCall::CALLER_STUDENT
            ? (int) $call->counselor_id
            : (int) $call->student_id;
    }

    /**
     * Ring the callee. The caller is already showing CALLING locally — this is what puts
     * the callee into RINGING, and it must never be sent back to the caller.
     */
    public function signalRinging(CounselingCall $call): void
    {
        $calleeId = $this->calleeId($call);

        Log::info('[Call] Recipient identified', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'caller_role' => (string) $call->caller_role,
            'caller_user_id' => $this->callerId($call),
            'recipient_user_id' => $calleeId,
            'caller_state' => 'CALLING',
            'recipient_state' => CallSignalBroadcaster::STATE_RINGING,
        ]);

        $this->signals->send(
            $calleeId,
            $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_RINGING),
            'ringing'
        );
    }

    /** Tell the caller their call was answered — both sides go CONNECTED. */
    public function signalAccepted(CounselingCall $call): void
    {
        Log::info('[Call] Call accepted', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'accepted_by_user_id' => $this->calleeId($call),
            'caller_user_id' => $this->callerId($call),
            'state' => CallSignalBroadcaster::STATE_CONNECTED,
        ]);

        $this->signals->send(
            $this->callerId($call),
            $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_CONNECTED),
            'accepted'
        );

        $this->clearCallNotifications((int) $call->appointment_id);
    }

    /**
     * Runs $callback with every given user id's call-state locked against concurrent
     * mutation. Locks are acquired in a fixed (ascending id) order regardless of call
     * argument order, so two requests locking the same pair never deadlock each other.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException if a lock can't be acquired in time.
     */
    public function withUsersLocked(array $userIds, Closure $callback): mixed
    {
        $ids = collect($userIds)->filter()->unique()->sort()->values()->all();
        try {
            $locks = array_map(
                fn (int $id) => Cache::lock("call-user-lock:{$id}", self::LOCK_TTL_SECONDS),
                $ids
            );

            return $this->acquireChain($locks, 0, $callback);
        } catch (\BadMethodCallException) {
            return $callback();
        }
    }

    private function acquireChain(array $locks, int $index, Closure $callback): mixed
    {
        if (! array_key_exists($index, $locks)) {
            return $callback();
        }

        return $locks[$index]->block(self::LOCK_WAIT_SECONDS, function () use ($locks, $index, $callback) {
            return $this->acquireChain($locks, $index + 1, $callback);
        });
    }

    /**
     * Marks any stale call rows (expired pending, closed appointment accepted, or past max duration)
     * touching the given users as missed or ended.
     * Must be called from within withUsersLocked() covering the same user ids.
     * Returns the rows that were swept so the caller can notify outside the lock.
     */
    public function sweepExpired(int ...$userIds): Collection
    {
        $hasExpiresAt = Schema::hasColumn('calls', 'expires_at');

        // 1. Sweep stale PENDING calls (timed-out rings)
        $stalePending = CounselingCall::query()
            ->where('status', CounselingCall::STATUS_PENDING)
            ->where(function ($query) use ($hasExpiresAt) {
                if ($hasExpiresAt) {
                    $query->where('expires_at', '<=', now())
                        ->orWhere(function ($q) {
                            $q->whereNull('expires_at')
                                ->where('created_at', '<=', now()->subSeconds(self::RING_TTL_SECONDS));
                        });
                } else {
                    $query->where('created_at', '<=', now()->subSeconds(self::RING_TTL_SECONDS));
                }
            })
            ->where(function ($query) use ($userIds) {
                foreach ($userIds as $id) {
                    $query->orWhere('student_id', $id)->orWhere('counselor_id', $id);
                }
            })
            ->get();

        if ($stalePending->isNotEmpty()) {
            CounselingCall::query()
                ->whereIn('id', $stalePending->pluck('id'))
                ->update(['status' => CounselingCall::STATUS_MISSED]);
        }

        // 2. Sweep stale ACCEPTED calls
        // An accepted call from a completed/cancelled appointment, a closed appointment window,
        // or one that connected > 2 hours ago is dead and must not block new calls.
        $staleAccepted = CounselingCall::query()
            ->where('status', CounselingCall::STATUS_ACCEPTED)
            ->where(function ($query) use ($userIds) {
                foreach ($userIds as $id) {
                    $query->orWhere('student_id', $id)->orWhere('counselor_id', $id);
                }
            })
            ->with('appointment')
            ->get()
            ->filter(function (CounselingCall $call) {
                if (! $call->appointment || in_array($call->appointment->status, ['completed', 'cancelled'], true)) {
                    return true;
                }
                if (! AppointmentCallWindow::isOpen($call->appointment)) {
                    return true;
                }
                $started = $call->connected_at ?? $call->created_at;
                if ($started && $started->lt(now()->subHours(2))) {
                    return true;
                }
                return false;
            });

        if ($staleAccepted->isNotEmpty()) {
            CounselingCall::query()
                ->whereIn('id', $staleAccepted->pluck('id'))
                ->update(['status' => CounselingCall::STATUS_ENDED]);
        }

        return $stalePending;
    }

    /**
     * The active call row (if any) that makes either user unavailable to start or accept
     * a new one. Must be called from within withUsersLocked() covering both user ids.
     */
    public function findActiveConflict(int $userA, int $userB, ?Appointment $targetAppointment = null): ?CounselingCall
    {
        $calls = CounselingCall::query()
            ->where(function ($query) use ($userA, $userB) {
                $query->where('student_id', $userA)
                    ->orWhere('counselor_id', $userA)
                    ->orWhere('student_id', $userB)
                    ->orWhere('counselor_id', $userB);
            })
            ->whereIn('status', CounselingCall::ACTIVE_STATUSES)
            ->with('appointment')
            ->orderByDesc('id')
            ->get();

        $isTargetEmergency = $targetAppointment && (
            (bool) ($targetAppointment->is_emergency ?? false) ||
            AppointmentCallWindow::isEmergency($targetAppointment)
        );

        foreach ($calls as $call) {
            // 1. Pending and past expiration: sweep it
            if ($call->status === CounselingCall::STATUS_PENDING && $call->isExpired()) {
                $call->update(['status' => CounselingCall::STATUS_MISSED]);
                continue;
            }

            // 2. Same participants on a different appointment:
            // If this prior call is between the exact same student and counselor,
            // they are now moving to / opening a new appointment together.
            // Any prior call between them is finished and must not block them.
            if ($targetAppointment && (int) $call->appointment_id !== (int) $targetAppointment->id) {
                $isSamePair = (int) $call->student_id === (int) $targetAppointment->student_id
                    && (int) $call->counselor_id === (int) $targetAppointment->counselor_id;
                if ($isSamePair) {
                    $call->update([
                        'status' => $call->status === CounselingCall::STATUS_PENDING
                            ? CounselingCall::STATUS_CANCELLED
                            : CounselingCall::STATUS_ENDED,
                    ]);
                    continue;
                }
            }

            // 3. Accepted and appointment finished / closed / stale: sweep it
            if ($call->status === CounselingCall::STATUS_ACCEPTED) {
                $apt = $call->appointment;
                if (! $apt || in_array($apt->status, ['completed', 'cancelled'], true) || ! AppointmentCallWindow::isOpen($apt)) {
                    $call->update(['status' => CounselingCall::STATUS_ENDED]);
                    continue;
                }
                $started = $call->connected_at ?? $call->created_at;
                if ($started && $started->lt(now()->subHours(2))) {
                    $call->update(['status' => CounselingCall::STATUS_ENDED]);
                    continue;
                }
            }

            // 4. Emergency priority override:
            // Priority crisis sessions must NEVER be blocked by any lingering calls on other appointments,
            // regardless of whether the old appointment was also an emergency or regular session.
            if ($isTargetEmergency && $targetAppointment && (int) $call->appointment_id !== (int) $targetAppointment->id) {
                $call->update([
                    'status' => $call->status === CounselingCall::STATUS_PENDING
                        ? CounselingCall::STATUS_CANCELLED
                        : CounselingCall::STATUS_ENDED,
                ]);
                continue;
            }

            return $call;
        }

        return null;
    }

    /** Notify the callee that an unanswered call from them timed out with no response. */
    public function notifyMissed(CounselingCall $call): void
    {
        $isStudentCaller = $call->caller_role === CounselingCall::CALLER_STUDENT;
        $notifyUserId = $this->calleeId($call);
        $notifyBody = $isStudentCaller ? 'Missed audio/video call from student.' : 'Missed audio/video call from counselor.';
        $notifyRoute = $isStudentCaller ? '/counselor/video' : '/student/video-call';

        $this->pushAndNotify($notifyUserId, 'Missed call', $notifyBody, $notifyRoute, $call, requireInteraction: false);

        Log::info('[Call] Call missed', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'caller_user_id' => $this->callerId($call),
            'recipient_user_id' => $notifyUserId,
            'state' => CallSignalBroadcaster::STATE_ENDED,
        ]);

        // Both sides must tear down: the callee's ring stops, the caller's ringback stops.
        $payload = $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_ENDED);
        $this->signals->send($notifyUserId, $payload, 'missed');
        $this->signals->send($this->callerId($call), $payload, 'missed');

        // Clear transient ringing overhead notifications before recording the missed call record
        $this->clearCallNotifications((int) $call->appointment_id);

        $this->pushAndNotify($notifyUserId, 'Missed call', $notifyBody, $notifyRoute, $call, requireInteraction: false);
    }

    /** Notify the caller that the callee explicitly declined. */
    public function notifyDeclined(CounselingCall $call): void
    {
        $isStudentCaller = $call->caller_role === CounselingCall::CALLER_STUDENT;
        $callerUserId = $this->callerId($call);
        $notifyRoute = $isStudentCaller ? '/student/video-call' : '/counselor/video';

        Log::info('[Call] Call declined', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'declined_by_user_id' => $this->calleeId($call),
            'caller_user_id' => $callerUserId,
            'state' => CallSignalBroadcaster::STATE_ENDED,
        ]);

        $this->signals->send(
            $callerUserId,
            $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_ENDED),
            'declined'
        );

        $this->clearCallNotifications((int) $call->appointment_id);

        $this->pushAndNotify($callerUserId, 'Call declined', 'Your call was declined.', $notifyRoute, $call, requireInteraction: false);
    }

    /** Notify the callee that the caller cancelled before they answered. */
    public function notifyCancelled(CounselingCall $call): void
    {
        $isStudentCaller = $call->caller_role === CounselingCall::CALLER_STUDENT;
        $notifyUserId = $this->calleeId($call);
        $notifyRoute = $isStudentCaller ? '/counselor/video' : '/student/video-call';

        Log::info('[Call] Call cancelled by caller', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'caller_user_id' => $this->callerId($call),
            'recipient_user_id' => $notifyUserId,
            'state' => CallSignalBroadcaster::STATE_ENDED,
        ]);

        // Stops the ring on the recipient's device — without this their overlay keeps
        // ringing for the full auto-dismiss window after the caller has hung up.
        $this->signals->send(
            $notifyUserId,
            $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_ENDED),
            'cancelled'
        );

        $this->clearCallNotifications((int) $call->appointment_id);

        $this->pushAndNotify($notifyUserId, 'Call cancelled', 'The call was cancelled.', $notifyRoute, $call, requireInteraction: false);
    }

    /** Tell both participants the call has ended and stop all rings. */
    public function notifyEnded(CounselingCall $call): void
    {
        Log::info('[Call] Call ended', [
            'call_id' => (int) $call->id,
            'appointment_id' => (int) $call->appointment_id,
            'caller_user_id' => $this->callerId($call),
            'recipient_user_id' => $this->calleeId($call),
            'state' => CallSignalBroadcaster::STATE_ENDED,
        ]);

        $payload = $this->signals->payloadFor($call, CallSignalBroadcaster::STATE_ENDED);
        $this->signals->send((int) $call->student_id, $payload, 'ended');
        $this->signals->send((int) $call->counselor_id, $payload, 'ended');

        $this->clearCallNotifications((int) $call->appointment_id);
    }

    /** Clear and remove transient incoming-call notifications for this appointment so overhead banners dismiss immediately. */
    public function clearCallNotifications(int $appointmentId): void
    {
        try {
            Notification::query()
                ->where(function ($query) use ($appointmentId) {
                    $query->whereJsonContains('meta->appointment_id', $appointmentId)
                        ->orWhere('meta->appointment_id', $appointmentId);
                })
                ->where(function ($query) {
                    $query->whereJsonContains('meta->kind', 'incoming_call')
                        ->orWhere('meta->kind', 'incoming_call')
                        ->orWhereIn('title', ['Incoming video call', 'Incoming audio call']);
                })
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('[CallCoordinator] Failed to clear call notifications', [
                'appointment_id' => $appointmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function pushAndNotify(
        int $userId,
        string $title,
        string $body,
        string $route,
        CounselingCall $call,
        bool $requireInteraction
    ): void {
        try {
            $this->webPush->sendToUser($userId, $title, $body, $route, [
                'tag' => 'cms-call-apt-'.(int) $call->appointment_id,
                'urgency' => 'normal',
                'requireInteraction' => $requireInteraction,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[VideoCall] web push failed', ['appointment_id' => $call->appointment_id, 'error' => $e->getMessage()]);
        }

        try {
            $notification = Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $body,
                'type' => 'info',
                'meta' => [
                    'kind' => 'call_update',
                    'appointment_id' => (int) $call->appointment_id,
                    'status' => $call->status,
                ],
            ]);
            event(new NotificationCreated($notification));
        } catch (\Throwable $e) {
            Log::warning('[VideoCall] in-app notification failed', ['appointment_id' => $call->appointment_id, 'error' => $e->getMessage()]);
        }
    }
}
