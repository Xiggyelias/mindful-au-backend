<?php

namespace App\Http\Controllers;

use App\Models\CounselingSession;
use App\Models\Escalation;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeerSupportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $profile = Profile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['peer_available' => true]
        );

        $activeChats = CounselingSession::query()
            ->where('peer_counselor_id', $user->id)
            ->where('assigned_role', 'peer_counselor')
            ->where('session_type', 'chat')
            ->whereIn('status', ['pending', 'active'])
            ->count();

        $chatHistoryCount = CounselingSession::query()
            ->where('peer_counselor_id', $user->id)
            ->where('session_type', 'chat')
            ->count();

        $escalatedCases = Escalation::query()
            ->where('escalated_by', $user->id)
            ->count();

        $urgentFlags = Escalation::query()
            ->where('escalated_by', $user->id)
            ->whereIn('escalation_type', ['urgent_flag', 'panic'])
            ->count();

        $recentSessions = CounselingSession::query()
            ->where('peer_counselor_id', $user->id)
            ->where('assigned_role', 'peer_counselor')
            ->where('session_type', 'chat')
            ->whereIn('status', ['pending', 'active'])
            ->with(['student.profile'])
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (CounselingSession $session) {
                $displayName = $session->is_anonymous
                    ? ($session->anonymous_id ?: "Student_#{$session->student_id}")
                    : ($session->student?->profile?->full_name ?: "Student_#{$session->student_id}");

                return [
                    'id' => $session->id,
                    'student_label' => $displayName,
                    'status' => $session->status,
                    'session_type' => $session->session_type,
                    'updated_at' => $session->updated_at,
                ];
            });

        return response()->json([
            'availability' => (bool) $profile->peer_available,
            'stats' => [
                'active_chats' => $activeChats,
                'chat_history_count' => $chatHistoryCount,
                'escalated_cases' => $escalatedCases,
                'urgent_flags' => $urgentFlags,
            ],
            'recent_sessions' => $recentSessions,
        ]);
    }

    public function escalations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rows = Escalation::query()
            ->where('escalated_by', $user->id)
            ->with(['session.student.profile', 'escalatedToUser.profile'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(function (Escalation $escalation) {
                $session = $escalation->session;
                $studentLabel = $session?->is_anonymous
                    ? ($session->anonymous_id ?: "Student_#{$session?->student_id}")
                    : ($session?->student?->profile?->full_name ?: "Student_#{$session?->student_id}");

                return [
                    'id' => $escalation->id,
                    'session_id' => $escalation->session_id,
                    'student_label' => $studentLabel,
                    'escalation_type' => $escalation->escalation_type,
                    'severity' => $escalation->severity,
                    'reason' => $escalation->reason,
                    'escalated_to' => $escalation->escalatedToUser?->profile?->full_name
                        ?: $escalation->escalatedToUser?->email,
                    'created_at' => $escalation->created_at,
                ];
            });

        return response()->json($rows);
    }

    public function setAvailability(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'available' => 'required|boolean',
        ]);

        $profile = Profile::query()->firstOrCreate(['user_id' => $user->id]);
        $profile->peer_available = (bool) $validated['available'];
        $profile->save();

        return response()->json([
            'message' => 'Availability updated',
            'available' => (bool) $profile->peer_available,
        ]);
    }
}
