<?php

namespace App\Http\Controllers;

use App\Models\CounselingCall;
use App\Support\CallCoordinator;
use App\Support\CallSignalBroadcaster;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CounselorIncomingCallController extends Controller
{
    private const POLL_MAX_AGE_MINUTES = 30;

    public function __construct(private readonly CallCoordinator $calls) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $calls = CounselingCall::query()
            ->where('counselor_id', $user->id)
            ->where('caller_role', CounselingCall::CALLER_STUDENT)
            ->where('status', CounselingCall::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('created_at', '>=', now()->subMinutes(self::POLL_MAX_AGE_MINUTES))
            ->with(['appointment', 'student.profile'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $data = $calls->map(function (CounselingCall $call): array {
            $apt = $call->appointment;
            $student = $call->student;
            $profile = $student?->profile;
            $fullName = trim((string) (optional($profile)->full_name ?? ''));
            $isAnonymous = (bool) ($apt?->is_anonymous);

            $studentLabel = $isAnonymous
                ? 'Anonymous User'
                : (
                    $fullName !== ''
                    ? $fullName
                    : (
                        $student?->email
                        ? strstr((string) $student->email, '@', true) ?: (string) $student->email
                        : 'Student'
                    )
                );

            $publicStudentId = $isAnonymous ? 0 : (int) $call->student_id;

            // Anonymous students expose no photo — the overlay falls back to the shield icon.
            $avatarUrl = $isAnonymous ? '' : trim((string) (optional($profile)->avatar_url ?? ''));

            $scheduledAt = $apt?->scheduled_at;
            $scheduledIso = $scheduledAt instanceof Carbon
                ? $scheduledAt->toIso8601String()
                : ($scheduledAt ? (string) $scheduledAt : null);

            return [
                'id' => (int) $call->id,
                'appointment_id' => (int) $call->appointment_id,
                'student_id' => $publicStudentId,
                'student_name' => $studentLabel,
                'student_avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
                'is_anonymous' => $isAnonymous,
                'call_type' => (string) $call->call_type,
                'status' => (string) $call->status,
                'scheduled_at' => $scheduledIso,
                'created_at' => $call->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, CounselingCall $counselingCall): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((int) $counselingCall->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((string) $counselingCall->caller_role !== CounselingCall::CALLER_STUDENT) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        try {
            $outcome = $this->calls->withUsersLocked(
                [(int) $counselingCall->student_id, (int) $counselingCall->counselor_id],
                function () use ($counselingCall, $validated) {
                    $counselingCall->refresh();

                    if ($counselingCall->status !== CounselingCall::STATUS_PENDING) {
                        return ['type' => 'not_pending'];
                    }

                    if ($counselingCall->isExpired()) {
                        $counselingCall->update(['status' => CounselingCall::STATUS_MISSED]);

                        return ['type' => 'expired', 'call' => $counselingCall];
                    }

                    $counselingCall->update([
                        'status' => $validated['status'],
                        'connected_at' => $validated['status'] === 'accepted' ? now() : null,
                    ]);

                    return ['type' => 'updated', 'call' => $counselingCall];
                }
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'The call system is busy. Please try again in a moment.',
            ], 503);
        }

        if ($outcome['type'] === 'not_pending') {
            return response()->json(['message' => 'Call is no longer pending.'], 422);
        }

        if ($outcome['type'] === 'expired') {
            $this->calls->notifyMissed($outcome['call']);

            return response()->json(['message' => 'This call has expired.'], 410);
        }

        if ($validated['status'] === 'declined') {
            $this->calls->notifyDeclined($outcome['call']);
        } else {
            $this->calls->signalAccepted($outcome['call']);
        }

        return response()->json([
            'id' => (int) $counselingCall->id,
            'appointment_id' => (int) $counselingCall->appointment_id,
            'status' => (string) $counselingCall->status,
            'state' => $validated['status'] === 'accepted'
                ? CallSignalBroadcaster::STATE_CONNECTED
                : CallSignalBroadcaster::STATE_ENDED,
        ]);
    }
}
