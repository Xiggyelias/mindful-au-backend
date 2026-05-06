<?php

namespace App\Http\Controllers;

use App\Models\CounselingCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CounselorIncomingCallController extends Controller
{
    private const POLL_MAX_AGE_MINUTES = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $calls = CounselingCall::query()
            ->where('counselor_id', $user->id)
            ->where('status', CounselingCall::STATUS_PENDING)
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

            $scheduledAt = $apt?->scheduled_at;
            $scheduledIso = $scheduledAt instanceof Carbon
                ? $scheduledAt->toIso8601String()
                : ($scheduledAt ? (string) $scheduledAt : null);

            return [
                'id' => (int) $call->id,
                'appointment_id' => (int) $call->appointment_id,
                'student_id' => $publicStudentId,
                'student_name' => $studentLabel,
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
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((int) $counselingCall->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        if ($counselingCall->status !== CounselingCall::STATUS_PENDING) {
            return response()->json(['message' => 'Call is no longer pending.'], 422);
        }

        $counselingCall->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'id' => (int) $counselingCall->id,
            'appointment_id' => (int) $counselingCall->appointment_id,
            'status' => (string) $counselingCall->status,
        ]);
    }
}
