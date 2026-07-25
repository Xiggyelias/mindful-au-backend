<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\CounselingCall;
use App\Models\Notification;
use App\Services\WebPushService;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        $locks = array_map(
            fn (int $id) => Cache::lock("call-user-lock:{$id}", self::LOCK_TTL_SECONDS),
            $ids
        );

        return $this->acquireChain($locks, 0, $callback);
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
     * Marks any stale (past expires_at, still pending) call rows touching the given users
     * as missed. Must be called from within withUsersLocked() covering the same user ids.
     * Returns the rows that were swept so the caller can notify outside the lock.
     */
    public function sweepExpired(int ...$userIds): Collection
    {
        $stale = CounselingCall::query()
            ->where('status', CounselingCall::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where(function ($query) use ($userIds) {
                foreach ($userIds as $id) {
                    $query->orWhere('student_id', $id)->orWhere('counselor_id', $id);
                }
            })
            ->get();

        if ($stale->isNotEmpty()) {
            CounselingCall::query()
                ->whereIn('id', $stale->pluck('id'))
                ->update(['status' => CounselingCall::STATUS_MISSED]);
        }

        return $stale;
    }

    /**
     * The active call row (if any) that makes either user unavailable to start or accept
     * a new one. Must be called from within withUsersLocked() covering both user ids.
     */
    public function findActiveConflict(int $userA, int $userB): ?CounselingCall
    {
        return CounselingCall::query()
            ->where(function ($query) use ($userA, $userB) {
                $query->where('student_id', $userA)
                    ->orWhere('counselor_id', $userA)
                    ->orWhere('student_id', $userB)
                    ->orWhere('counselor_id', $userB);
            })
            ->whereIn('status', CounselingCall::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();
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
    }

    /** Notify the caller that the callee explicitly declined. */
    public function notifyDeclined(CounselingCall $call): void
    {
        $isStudentCaller = $call->caller_role === CounselingCall::CALLER_STUDENT;
        $callerUserId = $this->callerId($call);
        $notifyRoute = $isStudentCaller ? '/student/video-call' : '/counselor/video';

        $this->pushAndNotify($callerUserId, 'Call declined', 'Your call was declined.', $notifyRoute, $call, requireInteraction: false);

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
    }

    /** Notify the callee that the caller cancelled before they answered. */
    public function notifyCancelled(CounselingCall $call): void
    {
        $isStudentCaller = $call->caller_role === CounselingCall::CALLER_STUDENT;
        $notifyUserId = $this->calleeId($call);
        $notifyRoute = $isStudentCaller ? '/counselor/video' : '/student/video-call';

        $this->pushAndNotify($notifyUserId, 'Call cancelled', 'The call was cancelled.', $notifyRoute, $call, requireInteraction: false);

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
