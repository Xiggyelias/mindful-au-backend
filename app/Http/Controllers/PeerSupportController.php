<?php

namespace App\Http\Controllers;

use App\Models\CounselingSession;
use App\Models\Escalation;
use App\Models\Message;
use App\Models\PeerAssignment;
use App\Models\Profile;
use App\Services\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PeerSupportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('peer_counselor')) {
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

        $assignedSessionIds = PeerAssignment::query()
            ->where('peer_counselor_id', $user->id)
            ->pluck('session_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $directSessionIds = CounselingSession::query()
            ->where('peer_counselor_id', $user->id)
            ->where('session_type', 'chat')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $chatHistoryCount = count(array_unique(array_filter(array_merge($assignedSessionIds, $directSessionIds))));

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
                $displayName = $this->resolveStudentLabelForPeer($session);

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
        if (! $user->hasRole('peer_counselor')) {
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
                $studentLabel = $session
                    ? $this->resolveStudentLabelForPeer($session)
                    : 'Anonymous User';

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

    private function resolveStudentLabelForPeer(CounselingSession $session): string
    {
        $studentProfile = $session->student?->profile;
        $liveProfileAnon = $studentProfile !== null ? (bool) $studentProfile->anonymous_mode : null;
        $isAnonymous = $liveProfileAnon !== null ? $liveProfileAnon : (bool) $session->is_anonymous;

        if ($isAnonymous && $session->identity_revealed_at === null) {
            return 'Anonymous User';
        }

        return $studentProfile?->full_name
            ?: ($session->student?->email ?: "Student_#{$session->student_id}");
    }

    public function setAvailability(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('peer_counselor')) {
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

    public function allEscalations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Escalation::query()
            ->with(['session.student.profile', 'escalatedByUser.profile', 'escalatedToUser.profile'])
            ->latest('id');

        if (! $user->hasRole('admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('escalated_to', $user->id)
                    ->orWhere('escalated_by', $user->id);
            });
        }

        $rows = $query->limit(200)->get()->map(function (Escalation $escalation) {
            $session = $escalation->session;
            $student = $session?->student;
            $studentName = $student?->profile?->full_name ?: ($student?->email ?: "Student #{$session?->student_id}");

            $escalatedByName = $escalation->escalatedByUser?->profile?->full_name
                ?: ($escalation->escalatedByUser?->email ?: 'Peer Counselor');

            $metadata = is_array($escalation->metadata) ? $escalation->metadata : [];
            $resolvedAt = $metadata['resolved_at'] ?? null;

            return [
                'id' => $escalation->id,
                'session_id' => $escalation->session_id,
                'student_id' => $session?->student_id,
                'student_name' => $studentName,
                'escalated_by_id' => $escalation->escalated_by,
                'escalated_by_name' => $escalatedByName,
                'escalated_to' => $escalation->escalatedToUser?->profile?->full_name ?: $escalation->escalatedToUser?->email,
                'escalation_type' => $escalation->escalation_type,
                'severity' => $escalation->severity,
                'reason' => $escalation->reason,
                'resolved' => ! empty($resolvedAt),
                'resolved_at' => $resolvedAt,
                'created_at' => $escalation->created_at,
            ];
        });

        return response()->json($rows);
    }

    public function resolveEscalation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $escalation = Escalation::findOrFail($id);
        $metadata = is_array($escalation->metadata) ? $escalation->metadata : [];
        $metadata['resolved_at'] = now()->toISOString();
        $metadata['resolved_by'] = $user->id;
        $escalation->metadata = $metadata;
        $escalation->save();

        return response()->json([
            'message' => 'Escalation marked as resolved.',
            'escalation' => $escalation,
        ]);
    }

    /**
     * Mint a token for the Peer Spatial Audio Heart-to-Heart Lounge.
     */
    public function spatialLoungeToken(Request $request, LiveKitService $livekit): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Unauthorized. Access restricted to peer counselors only.'], 403);
        }

        $validated = $request->validate([
            'room' => 'sometimes|string|max:100',
            'name' => 'sometimes|string|max:100',
            'avatar' => 'sometimes|string|max:50',
        ]);

        $roomName = $validated['room'] ?? 'peer-spatial-lounge';
        $alias = $validated['name'] ?? ($user->profile?->full_name ?: ($user->email ? explode('@', $user->email)[0] : 'Peer '.$user->id));
        $role = 'peer_counselor';

        if (! $livekit->isConfigured()) {
            return response()->json([
                'success' => true,
                'mode' => 'simulated',
                'url' => null,
                'token' => null,
                'room' => $roomName,
                'user' => [
                    'id' => (int) $user->id,
                    'name' => $alias,
                    'role' => $role,
                    'avatar' => $validated['avatar'] ?? 'dino-green',
                ],
            ]);
        }

        $token = $livekit->mintLoungeToken(
            $roomName,
            (int) $user->id,
            $alias,
            $role,
            7200
        );

        return response()->json([
            'success' => true,
            'mode' => 'livekit',
            'url' => $livekit->url(),
            'token' => $token,
            'room' => $roomName,
            'user' => [
                'id' => (int) $user->id,
                'name' => $alias,
                'role' => $role,
                'avatar' => $validated['avatar'] ?? 'dino-green',
            ],
        ]);
    }

    /**
     * Peer Counselor requests a private 1-on-1 Spatial Lounge session with their assigned student.
     */
    public function requestSessionSpatialLounge(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = CounselingSession::with(['student.profile', 'peerCounselor.profile'])->findOrFail($id);

        if ((int) $session->peer_counselor_id !== (int) $user->id || $session->assigned_role !== 'peer_counselor') {
            return response()->json(['message' => 'Unauthorized. Only the assigned peer counselor can request the spatial lounge.'], 403);
        }

        if (in_array((string) $session->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Cannot request spatial lounge for closed sessions.'], 422);
        }

        // Cache the active spatial lounge request for this session
        Cache::put("spatial_lounge_session_{$session->id}", [
            'active' => true,
            'session_id' => (int) $session->id,
            'requested_by' => (int) $user->id,
            'requested_at' => now()->toIso8601String(),
        ], now()->addHours(6));

        // Create an in-chat invitation message
        $inviteText = '🌿 Heart-to-Heart Spatial Lounge opened. Click "Join Spatial Lounge" to talk in our private 2D audio meadow.';
        Message::create([
            'session_id' => $session->id,
            'case_id' => $session->id,
            'sender_id' => $user->id,
            'sender_role' => 'peer_counselor',
            'content' => $inviteText,
        ]);

        return response()->json([
            'success' => true,
            'active' => true,
            'session_id' => (int) $session->id,
            'room' => "spatial-session-{$session->id}",
            'message' => 'Spatial lounge requested successfully.',
        ]);
    }

    /**
     * Get Spatial Lounge active status for a specific assigned session.
     */
    public function sessionSpatialLoungeStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = CounselingSession::findOrFail($id);

        $isPeer = (int) $session->peer_counselor_id === (int) $user->id && $session->assigned_role === 'peer_counselor';
        $isStudent = (int) $session->student_id === (int) $user->id;

        if (! $isPeer && ! $isStudent) {
            return response()->json(['message' => 'Unauthorized for this session.'], 403);
        }

        $loungeState = Cache::get("spatial_lounge_session_{$session->id}");

        return response()->json([
            'session_id' => (int) $session->id,
            'active' => ! empty($loungeState['active']),
            'requested_at' => $loungeState['requested_at'] ?? null,
            'is_peer_assigned' => $session->assigned_role === 'peer_counselor' && (int) $session->peer_counselor_id > 0,
            'can_request' => $isPeer && ! in_array((string) $session->status, ['completed', 'cancelled'], true),
        ]);
    }

    /**
     * Mint a token for the private 1-on-1 Spatial Lounge between the assigned peer counselor and student.
     */
    public function sessionSpatialLoungeToken(Request $request, string $id, LiveKitService $livekit): JsonResponse
    {
        $user = $request->user();
        $session = CounselingSession::with(['student.profile', 'peerCounselor.profile'])->findOrFail($id);

        $isPeer = (int) $session->peer_counselor_id === (int) $user->id && $session->assigned_role === 'peer_counselor';
        $isStudent = (int) $session->student_id === (int) $user->id;

        if (! $isPeer && ! $isStudent) {
            return response()->json(['message' => 'Unauthorized. This spatial lounge is exclusively for the assigned peer counselor and student.'], 403);
        }

        $roomName = "spatial-session-{$session->id}";
        $role = $isPeer ? 'peer_counselor' : 'student';

        $peerUser = $session->peerCounselor;
        $studentUser = $session->student;

        $peerName = $peerUser?->profile?->full_name ?: ($peerUser?->email ? explode('@', $peerUser->email)[0] : 'Peer Counselor');
        $studentName = $session->is_anonymous
            ? 'Anonymous Student'
            : ($studentUser?->profile?->full_name ?: ($studentUser?->email ? explode('@', $studentUser->email)[0] : 'Student'));

        $myName = $isPeer ? $peerName : $studentName;
        $partnerName = $isPeer ? $studentName : $peerName;
        $partnerId = $isPeer ? (int) $session->student_id : (int) $session->peer_counselor_id;
        $partnerRole = $isPeer ? 'student' : 'peer_counselor';

        $token = null;
        if ($livekit->isConfigured()) {
            $token = $livekit->mintLoungeToken($roomName, (int) $user->id, $myName, $role, 7200);
        }

        return response()->json([
            'success' => true,
            'mode' => $livekit->isConfigured() ? 'livekit' : 'simulated',
            'url' => $livekit->isConfigured() ? $livekit->url() : null,
            'token' => $token,
            'room' => $roomName,
            'session_id' => (int) $session->id,
            'user' => [
                'id' => (int) $user->id,
                'name' => $myName,
                'role' => $role,
                'avatar' => $isPeer ? 'dino-blue' : 'dino-green',
                'initial_x' => $isPeer ? 400 : 600,
                'initial_y' => 320,
            ],
            'assigned_partner' => [
                'id' => $partnerId,
                'name' => $partnerName,
                'role' => $partnerRole,
                'avatar' => $isPeer ? 'dino-green' : 'dino-blue',
                'initial_x' => $isPeer ? 600 : 400,
                'initial_y' => 320,
            ],
        ]);
    }
}
