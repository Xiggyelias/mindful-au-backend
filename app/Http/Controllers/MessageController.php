<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Message;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\PeerAssignment;
use App\Models\User;
use App\Support\ChatMessageData;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    private const DELETE_TOMBSTONE = 'This message was deleted.';
    private const TYPING_STATE_TTL_SECONDS = 5;
    private const PRESENCE_TOUCH_INTERVAL_SECONDS = 15;
    private const ANONYMOUS_SESSION_TTL_HOURS = 24;

    protected $mlService;

    public function __construct(
        \App\Services\MentalHealthMlService $mlService,
        protected WebPushService $webPush,
    ) {
        $this->mlService = $mlService;
    }

    /**
     * Mark every inbound message in this thread as read for the current user.
     * Unread badges use seen_at IS NULL (equivalent to is_read = 0); this must run for the
     * whole session, not only the paginated messages slice, or badges stay nonzero when
     * older unread rows fall outside the default limit.
     */
    private function markInboundMessagesReadForViewer(int $sessionId, int $viewerId): void
    {
        if ($sessionId <= 0 || $viewerId <= 0) {
            return;
        }

        $seenAt = now();
        Message::query()
            ->where('session_id', $sessionId)
            ->where('recipient_id', $viewerId)
            ->whereNull('seen_at')
            ->update(['seen_at' => $seenAt]);
    }

    public function markInboundRead(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::query()->select([
            'id',
            'student_id',
            'counselor_id',
            'peer_counselor_id',
            'assigned_role',
            'status',
            'is_anonymous',
            'identity_revealed_at',
            'updated_at',
        ])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if (! $this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->maybeBumpAnonymousSessionActivity($session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        $this->markInboundMessagesReadForViewer((int) $session->id, (int) $user->id);

        return response()->json(null, 204);
    }

    public function index(Request $request, string $sessionId): JsonResponse
    {
                $session = CounselingSession::query()->select(['id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'status',
                'is_anonymous',
                'identity_revealed_at',
                'updated_at',
            ])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if (
            ! $this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Throttled: avoid writing counseling_sessions on every poll while keeping 24h TTL alive.
        $this->maybeBumpAnonymousSessionActivity($session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        $validated = $request->validate([
            'after_id' => 'nullable|integer|min:0',
            'before_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'mark_read' => 'sometimes|boolean',
        ]);

        $markRead = $request->boolean('mark_read', true);
        if ($markRead) {
            $this->markInboundMessagesReadForViewer((int) $session->id, (int) $user->id);
        }

        $this->touchPresenceIfStale($user);

        $limit = (int) ($validated['limit'] ?? 40);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $beforeId = (int) ($validated['before_id'] ?? 0);

        if ($afterId > 0 && $beforeId > 0) {
            return response()->json([
                'message' => 'Provide only one cursor: after_id or before_id.',
            ], 422);
        }

        $query = Message::query()
            ->where('session_id', $sessionId)
            ->with('chatFile')
            ->select([
                'id',
                'case_id',
                'session_id',
                'sender_id',
                'sender_role',
                'sender_name_snapshot',
                'recipient_id',
                'content',
                'message_type',
                'file_url',
                'has_file',
                'is_encrypted',
                'sent_as_anonymous',
                'seen_at',
                'created_at',
                'updated_at',
            ]);

        if ($afterId > 0) {
            $messages = $query
                ->where('id', '>', $afterId)
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();
        } elseif ($beforeId > 0) {
            $messages = $query
                ->where('id', '<', $beforeId)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        } else {
            $messages = $query
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        }

        // Bulk read marking runs before this query; a message can still land between that
        // UPDATE and the SELECT above. Patch seen_at for any inbound rows still null in this page.
        $viewerId = (int) $user->id;
        $unseenMessageIds = $messages
            ->filter(
                static fn (Message $message) =>
                    (int) $message->recipient_id === $viewerId && $message->seen_at === null
            )
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if (!empty($unseenMessageIds)) {
            $seenAt = now();
            Message::query()
                ->whereIn('id', $unseenMessageIds)
                ->whereNull('seen_at')
                ->update([
                    'seen_at' => $seenAt,
                ]);

            $seenLookup = array_flip($unseenMessageIds);
            $messages->transform(
                static function (Message $message) use ($viewerId, $seenAt, $seenLookup): Message {
                    if (
                        (int) $message->recipient_id === $viewerId
                        && isset($seenLookup[(int) $message->id])
                    ) {
                        $message->seen_at = $seenAt;
                    }

                    return $message;
                }
            );
        }

        $studentId = (int) $session->student_id;
        $messages->transform(
            function (Message $message) use ($studentId, $session, $viewerId): Message {
                if (
                    ! $this->shouldMaskStudentIdentityForRecipient(
                        $session,
                        $viewerId,
                        $message->sent_as_anonymous
                    )
                ) {
                    return $message;
                }
                // Keep participant IDs on WebCrypto envelopes so counselors/peers can
                // correlate sender with envelope.from / envelope.to (masking broke E2E).
                if (
                    $this->isHandshakeEnvelope(
                        (string) $message->message_type,
                        (string) $message->content
                    )
                ) {
                    return $message;
                }
                if ((int) $message->sender_id === $studentId) {
                    $message->sender_id = 0;
                    $message->sender_name_snapshot = $this->resolveAnonymousLabel($session);
                }
                if ((int) $message->recipient_id === $studentId) {
                    $message->recipient_id = 0;
                }

                return $message;
            }
        );

        return response()->json(ChatMessageData::collection($messages));
    }

    public function indexBySession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|min:1',
        ]);

        return $this->index($request, (string) $validated['session_id']);
    }

    /**
     * Lightweight poll for new unread inbound messages (does not mark as read).
     * Clients call with after_id=0 once to obtain a cursor, then with the last cursor
     * to receive only new messages.
     */
    public function incomingDigest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (
            ! $user->hasRole('counselor')
            && ! $user->hasRole('peer_counselor')
            && ! $user->hasRole('student')
            && ! $user->hasRole('admin')
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'after_id' => 'nullable|integer|min:0',
        ]);
        $afterId = (int) ($validated['after_id'] ?? 0);
        $viewerId = (int) $user->id;

        $sessionExists = function ($sub) use ($user): void {
            $sub->from('counseling_sessions as s')
                ->whereColumn('s.id', 'messages.session_id')
                ->where('s.session_type', 'chat')
                ->whereNotIn('s.status', ['completed', 'cancelled']);

            if ($user->hasRole('admin')) {
                return;
            }
            if ($user->hasRole('counselor')) {
                $sub->where('s.counselor_id', $user->id);
            } elseif ($user->hasRole('peer_counselor')) {
                $sub->where('s.peer_counselor_id', $user->id)
                    ->where('s.assigned_role', 'peer_counselor');
            } elseif ($user->hasRole('student')) {
                $sub->where('s.student_id', $user->id);
            } else {
                $sub->whereRaw('1 = 0');
            }
        };

        $cursorBase = Message::query()
            ->where('recipient_id', $viewerId)
            ->whereExists(function ($sub) use ($sessionExists): void {
                $sub->selectRaw('1');
                $sessionExists($sub);
            });

        if ($afterId === 0) {
            $maxId = (int) ((clone $cursorBase)->max('id'));

            return response()->json([
                'after_id' => $maxId,
                'messages' => [],
            ]);
        }

        $rows = Message::query()
            ->where('recipient_id', $viewerId)
            ->whereNull('seen_at')
            ->where('id', '>', $afterId)
            ->whereExists(function ($sub) use ($sessionExists): void {
                $sub->selectRaw('1');
                $sessionExists($sub);
            })
            ->orderBy('id')
            ->limit(40)
            ->get([
                'id',
                'case_id',
                'session_id',
                'sender_id',
                'sender_role',
                'sender_name_snapshot',
                'content',
                'message_type',
                'is_encrypted',
                'sent_as_anonymous',
                'created_at',
            ]);

        if ($rows->isEmpty()) {
            return response()->json([
                'after_id' => $afterId,
                'messages' => [],
            ]);
        }

        $maxRowId = (int) $rows->max('id');

        $sessionIds = $rows->pluck('session_id')->unique()->filter()->all();
        $sessions = CounselingSession::query()
            ->whereIn('id', $sessionIds)
            ->get([
                'id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'is_anonymous',
                'identity_revealed_at',
            ])
            ->keyBy('id');

        $senderIds = $rows->pluck('sender_id')->unique()->filter()->all();
        $senders = User::query()
            ->with('profile')
            ->whereIn('id', $senderIds)
            ->get()
            ->keyBy('id');

        $payload = [];

        foreach ($rows as $message) {
            /** @var Message $message */
            if ($this->isHandshakeEnvelope((string) $message->message_type, (string) $message->content)) {
                continue;
            }
            if ((string) $message->content === self::DELETE_TOMBSTONE) {
                continue;
            }

            $session = $sessions->get((int) $message->session_id);
            if ($session === null) {
                continue;
            }

            $isAssignedPeer = $this->isAssignedPeerCounselor($user, $session);
            if (! $this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeer)) {
                continue;
            }

            $senderId = (int) $message->sender_id;
            $sender = $senders->get($senderId);

            $senderName = trim((string) ($message->sender_name_snapshot ?? ''));
            if ($senderName === '') {
                $senderName = optional(optional($sender)->profile)->full_name
                ?: ($sender?->email ? Str::before((string) $sender->email, '@') : 'Someone');
            }

            if (
                (int) $session->student_id === $senderId
                && $this->shouldMaskStudentIdentityForRecipient($session, $viewerId, $message->sent_as_anonymous)
            ) {
                $senderName = $this->resolveAnonymousLabel($session);
            }

            $isEncrypted = (bool) $message->is_encrypted;
            $preview = $this->buildMessagePreview(
                (string) $message->message_type,
                (string) $message->content,
                $isEncrypted
            );

            $payload[] = [
                'id' => (int) $message->id,
                'case_id' => $message->case_id !== null ? (int) $message->case_id : (int) $message->session_id,
                'session_id' => (int) $message->session_id,
                'sender_label' => $senderName,
                'sender_role' => (string) ($message->sender_role ?: 'student'),
                'preview' => $preview,
                'message_id' => (int) $message->id,
                'is_encrypted' => $isEncrypted,
                'message_type' => (string) $message->message_type,
                'created_at' => $message->created_at instanceof Carbon
                    ? $message->created_at->toIso8601String()
                    : Carbon::parse((string) $message->created_at)->toIso8601String(),
            ];
        }

        return response()->json([
            'after_id' => max($afterId, $maxRowId),
            'messages' => $payload,
        ]);
    }

    public function store(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::query()
            ->select([
                'id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'status',
                'session_type',
                'is_anonymous',
                'anonymous_id',
                'identity_revealed_at',
                'updated_at',
            ])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (
            !$this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $this->touchPresenceIfStale($user);

        if (!$user->hasRole('admin') && in_array($session->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'This session is closed and cannot receive new messages.',
            ], 422);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:65535',
            'message_type' => 'sometimes|in:text,voice,file,ai',
            'file_url' => 'nullable|url|max:2048',
            'is_encrypted' => 'sometimes|boolean',
        ]);

        $messageType = (string) ($validated['message_type'] ?? 'text');
        $content = (string) $validated['content'];
        $isPeerDelegatedCase = $session->assigned_role === 'peer_counselor' && $session->peer_counselor_id;

        /* 
        if ($isPeerDelegatedCase && !$user->hasRole('admin') && $messageType !== 'text') {
            return response()->json([
                'message' => 'Peer delegated cases support text chat only.',
            ], 422);
        }
        */

        if ($isAssignedPeerCounselor) {
            if ($session->session_type !== 'chat') {
                return response()->json([
                    'message' => 'Peer counselors are limited to chat-only interaction.',
                ], 422);
            }

            $riskLevel = $this->latestRiskLevel($session);
            if ($riskLevel !== null && $riskLevel !== 'low') {
                return response()->json([
                    'message' => 'This case is no longer low-risk. Please escalate to counselor immediately.',
                    'risk_level' => $riskLevel,
                ], 422);
            }

            if ($messageType !== 'text') {
                return response()->json([
                    'message' => 'Peer counselors can only send text messages in supervised chat.',
                ], 422);
            }
        }

        $isEncrypted = array_key_exists('is_encrypted', $validated)
            ? (bool) $validated['is_encrypted']
            : $this->inferEncryptionFlag($content);

        $recipientId = $this->resolveRecipientId($session, (int) $user->id);

        $message = Message::create([
            'session_id' => $sessionId,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'content' => $content,
            'message_type' => $messageType,
            'file_url' => $validated['file_url'] ?? null,
            'has_file' => !empty($validated['file_url']),
            'is_encrypted' => $isEncrypted,
            'sent_as_anonymous' => (bool) $session->is_anonymous,
            'seen_at' => null,
        ]);

        // ML Crisis Detection
        if ((int) $session->student_id === (int) $user->id && $messageType === 'text' && !$isEncrypted) {
            $crisisWords = $this->mlService->detectCrisisInText($content);
            if (!empty($crisisWords)) {
                try {
                    $this->triggerCrisisAlert($session, $user, $crisisWords);
                } catch (\Throwable $_) {
                    // Fail silently, don't block the message
                }
            }
        }

        $legacyBroadcastEnabled = filter_var(
            (string) env('CHAT_LEGACY_BROADCAST', false),
            FILTER_VALIDATE_BOOL
        );
        if ($legacyBroadcastEnabled) {
            try {
                // Optional legacy server-side broadcast. Client-driven realtime sync remains primary.
                event(new \App\Events\MessageSent($message));
            } catch (\Throwable $_) {
                // no-op
            }
        }

        try {
            $this->notifyMessageRecipient($session, $user->id, $validated, $isEncrypted, $message);
        } catch (\Throwable $_) {
            // no-op
        }

        return response()->json(ChatMessageData::make($message), 201);
    }

    /**
     * Student-only: verify keyword hints from the client (matched on plaintext before encryption)
     * and raise the same staff notifications as plaintext message crisis detection.
     */
    public function reportCrisisSignal(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::query()
            ->select([
                'id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'status',
                'session_type',
                'is_anonymous',
                'anonymous_id',
                'identity_revealed_at',
                'updated_at',
            ])
            ->findOrFail($sessionId);
        $user = $request->user();

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if ((int) $session->student_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'keywords' => 'required|array|min:1|max:25',
            'keywords.*' => 'string|max:200',
        ]);

        $joined = implode(' ', $validated['keywords']);
        $matches = $this->mlService->detectCrisisInText($joined);

        if ($matches === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No crisis keywords verified',
            ], 422);
        }

        $this->triggerCrisisAlert($session, $user, $matches);

        return response()->json([
            'ok' => true,
            'matched' => $matches,
        ]);
    }

    public function destroy(Request $request, string $sessionId, string $messageId): JsonResponse
    {
        $session = CounselingSession::query()
            ->select(['id', 'student_id', 'counselor_id', 'peer_counselor_id', 'assigned_role'])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (
            !$this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $this->touchPresenceIfStale($user);

        $message = Message::query()
            ->where('session_id', $sessionId)
            ->find($messageId);

        if (!$message) {
            return response()->json(['message' => 'Message not found'], 404);
        }

        if (! $this->viewerCanDeleteMessage($user, $session, $message, $isAssignedPeerCounselor)) {
            return response()->json([
                'message' => 'You can only delete messages you sent.',
            ], 403);
        }

        $deletedMessageId = (int) $message->id;
        $this->tombstoneMessageForEveryone($message);
        $this->deleteMessageNotifications($deletedMessageId);
        $message->refresh()->loadMissing('chatFile');

        $legacyBroadcastEnabled = filter_var(
            (string) env('CHAT_LEGACY_BROADCAST', false),
            FILTER_VALIDATE_BOOL
        );
        if ($legacyBroadcastEnabled) {
            try {
                event(new \App\Events\MessageSent($message));
            } catch (\Throwable $_) {
                // no-op
            }
        }

        return response()->json([
            'ok' => true,
            'id' => $deletedMessageId,
            'message' => ChatMessageData::make($message),
        ]);
    }

    public function setTyping(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::query()
            ->select(['id', 'student_id', 'counselor_id', 'peer_counselor_id', 'assigned_role'])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (
            !$this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $this->touchPresenceIfStale($user);

        $typingParticipantIds = $this->resolveActiveThreadParticipantIds($session);
        if (
            !$user->hasRole('admin')
            && !in_array((int) $user->id, $typingParticipantIds, true)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        $isTyping = (bool) $validated['is_typing'];
        $cacheKey = $this->typingCacheKey((int) $session->id, (int) $user->id);

        if ($isTyping) {
            Cache::put($cacheKey, true, now()->addSeconds(self::TYPING_STATE_TTL_SECONDS));
        } else {
            Cache::forget($cacheKey);
        }

        return response()->json([
            'ok' => true,
            'is_typing' => $isTyping,
        ]);
    }

    public function typingStatus(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::query()
            ->select([
                'id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'status',
                'is_anonymous',
                'identity_revealed_at',
                'updated_at',
            ])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (
            !$this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $this->touchPresenceIfStale($user);

        $typingParticipantIds = $this->resolveActiveThreadParticipantIds($session);
        $viewerId = (int) $user->id;
        $typingUserId = null;

        foreach ($typingParticipantIds as $participantId) {
            if ($participantId === $viewerId) {
                continue;
            }

            if (Cache::has($this->typingCacheKey((int) $session->id, $participantId))) {
                $typingUserId = $participantId;
                break;
            }
        }

        $displayTypingUserId = $typingUserId;
        if (
            $typingUserId !== null
            && $this->shouldMaskStudentIdentityForRecipient($session, $viewerId)
            && (int) $typingUserId === (int) $session->student_id
        ) {
            $displayTypingUserId = 0;
        }

        return response()->json([
            'is_typing' => $typingUserId !== null,
            'user_id' => $displayTypingUserId,
        ]);
    }

    private function inferEncryptionFlag(string $content): bool
    {
        $trimmed = trim($content);
        if ($trimmed === '' || str_starts_with($trimmed, '{')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9+\/=]+$/', $trimmed) === 1
            && strlen($trimmed) >= 40;
    }

    private function notifyMessageRecipient(
        CounselingSession $session,
        int $senderId,
        array $validated,
        bool $isEncrypted,
        Message $message
    ): void {
        $recipientIds = $this->resolveNotificationRecipientIds($session, $senderId);
        if ($recipientIds === []) {
            return;
        }

        $messageType = (string) ($validated['message_type'] ?? 'text');
        $content = (string) ($validated['content'] ?? '');

        if ($this->isHandshakeEnvelope($messageType, $content)) {
            return;
        }

        $sender = User::with('profile')->find($senderId);

        $preview = $this->buildMessagePreview($messageType, $content, $isEncrypted);

        foreach ($recipientIds as $recipientId) {
            if ($recipientId === $senderId) {
                continue;
            }

            $senderName = optional(optional($sender)->profile)->full_name
                ?: ($sender?->email ? Str::before($sender->email, '@') : 'Someone');

            $isAnonymousToRecipient = false;
            if (
                (int) $session->student_id === (int) $senderId
                && $this->shouldMaskStudentIdentityForRecipient($session, (int) $recipientId, $message->sent_as_anonymous)
            ) {
                $senderName = $this->resolveAnonymousLabel($session);
                $isAnonymousToRecipient = true;
            }

            $pushTitle = $isAnonymousToRecipient ? 'New anonymous message' : 'New message';
            $pushBody = sprintf('%s: %s', $senderName, $preview);

            Notification::create([
                'user_id' => $recipientId,
                'title' => 'New message',
                'message' => $pushBody,
                'meta' => [
                    'chat_session_id' => (int) $session->id,
                    'chat_message_id' => (int) $message->id,
                    'is_encrypted' => $isEncrypted,
                    'message_type' => $messageType,
                ],
                'type' => 'info',
            ]);

            $this->webPush->sendToUser(
                (int) $recipientId,
                $pushTitle,
                $pushBody,
                $this->chatUrlForUserId((int) $recipientId, (int) $session->id),
                [
                    'tag' => 'cms-chat-'.(int) $session->id.'-msg-'.(int) $message->id,
                    'urgency' => 'high',
                ]
            );
        }
    }

    private function chatUrlForUserId(int $userId, int $sessionId): string
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return '/';
        }

        if ($user->hasRole('admin')) {
            return '/admin/alerts';
        }

        if ($user->hasRole('counselor')) {
            return '/counselor/messages?session='.$sessionId;
        }

        if ($user->hasRole('peer_counselor')) {
            return '/peer/chats?session='.$sessionId;
        }

        if ($user->hasRole('student')) {
            return '/student/chat?session='.$sessionId;
        }

        return '/';
    }

    private function isHandshakeEnvelope(string $messageType, string $content): bool
    {
        if ($messageType !== 'text') {
            return false;
        }

        $trimmed = trim($content);
        if ($trimmed === '' || !str_starts_with($trimmed, '{')) {
            return false;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return false;
        }

        return ($decoded['__e2e'] ?? null) === 'v1'
            && in_array($decoded['kind'] ?? null, ['pub', 'key'], true);
    }

    private function buildMessagePreview(string $messageType, string $content, bool $isEncrypted): string
    {
        if ($messageType === 'file') {
            return 'sent an attachment';
        }

        if ($messageType === 'voice') {
            return 'sent a voice note';
        }

        if ($isEncrypted) {
            return 'sent a secure message';
        }

        $trimmed = trim($content);
        if ($trimmed === '') {
            return 'sent a message';
        }

        return Str::limit($trimmed, 80);
    }

    /**
     * Compare participant ids numerically — DB drivers may return string IDs; strict PHP
     * comparison with int user ids falsely denied counselors/students messaging access (403).
     */
    private function viewerCanAccessMessagingThread(
        User $user,
        CounselingSession $session,
        bool $isAssignedPeerCounselor
    ): bool {
        if ($user->hasRole('admin')) {
            return true;
        }

        $viewerId = (int) $user->id;

        return (int) $session->student_id === $viewerId
            || (int) $session->counselor_id === $viewerId
            || $isAssignedPeerCounselor;
    }

    /**
     * Students may delete only their own messages. Session counselor or assigned peer
     * counselor may delete any message in the thread (moderation). Admins may delete any.
     */
    private function viewerCanDeleteMessage(
        User $user,
        CounselingSession $session,
        Message $message,
        bool $isAssignedPeerCounselor
    ): bool {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ((int) $message->sender_id === (int) $user->id) {
            return true;
        }

        if ($user->hasRole('counselor') && (int) $session->counselor_id === (int) $user->id) {
            return true;
        }

        if ($isAssignedPeerCounselor) {
            return true;
        }

        return false;
    }

    private function isAssignedPeerCounselor(User $user, CounselingSession $session): bool
    {
        if (! $user->hasRole('peer_counselor')) {
            return false;
        }

        if (
            (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor'
        ) {
            return true;
        }

        return PeerAssignment::query()
            ->where('session_id', (int) $session->id)
            ->where('peer_counselor_id', (int) $user->id)
            ->whereIn('status', ['active', 'escalated'])
            ->exists();
    }

    private function latestRiskLevel(CounselingSession $session): ?string
    {
        $sessionDiagnostic = AiDiagnostic::query()
            ->where('session_id', $session->id)
            ->whereNotNull('risk_level')
            ->latest('id')
            ->value('risk_level');

        if ($sessionDiagnostic) {
            return strtolower((string) $sessionDiagnostic);
        }

        $studentDiagnostic = AiDiagnostic::query()
            ->where('student_id', $session->student_id)
            ->whereNotNull('risk_level')
            ->latest('id')
            ->value('risk_level');

        if ($studentDiagnostic) {
            return strtolower((string) $studentDiagnostic);
        }

        return null;
    }

    private function shouldMaskStudentIdentityForRecipient(
        CounselingSession $session,
        int $recipientId,
        ?bool $sentAsAnonymousSnapshot = null,
    ): bool {
        $effectiveAnonymous = $sentAsAnonymousSnapshot ?? (bool) $session->is_anonymous;

        if (! $effectiveAnonymous) {
            return false;
        }

        if ($recipientId === (int) $session->student_id) {
            return false;
        }

        $recipient = User::with('roles')->find($recipientId);
        if (!$recipient) {
            return true;
        }

        if (
            $session->identity_revealed_at !== null
            && (
                $recipient->hasRole('admin')
                || ($recipient->hasRole('counselor') && $recipientId === (int) $session->counselor_id)
            )
        ) {
            return false;
        }

        return true;
    }

    private function resolveAnonymousLabel(CounselingSession $_session): string
    {
        return 'Anonymous User';
    }

    private function isAnonymousSessionExpired(CounselingSession $session): bool
    {
        if (!$session->is_anonymous) {
            return false;
        }

        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', self::ANONYMOUS_SESSION_TTL_HOURS));
        $updatedAt = $session->updated_at instanceof \DateTimeInterface
            ? Carbon::instance($session->updated_at)
            : now();

        if ($updatedAt->greaterThanOrEqualTo(now()->subHours($ttlHours))) {
            return false;
        }

        if (in_array((string) $session->status, ['pending', 'active'], true)) {
            $session->forceFill([
                'status' => 'cancelled',
                'ended_at' => now(),
            ])->saveQuietly();
        }

        return true;
    }

    private function resolveRecipientId(CounselingSession $session, int $senderId): ?int
    {
        $recipientId = null;
        $casePeerCounselorId = (int) $session->peer_counselor_id;
        if ($casePeerCounselorId <= 0) {
            $casePeerCounselorId = (int) (PeerAssignment::query()
                ->where('session_id', (int) $session->id)
                ->whereNotNull('peer_counselor_id')
                ->whereIn('status', ['active', 'escalated'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->value('peer_counselor_id') ?? 0);
        }

        if ((int) $session->student_id === $senderId) {
            if ($casePeerCounselorId > 0) {
                $recipientId = $casePeerCounselorId;
            } else {
                $recipientId = $session->counselor_id ? (int) $session->counselor_id : null;
            }
        } elseif ($casePeerCounselorId === $senderId) {
            $recipientId = (int) $session->student_id;
        } elseif ((int) $session->counselor_id === $senderId) {
            $recipientId = (int) $session->student_id;
        }

        return $recipientId && $recipientId > 0 ? $recipientId : null;
    }

    /**
     * @return int[]
     */
    private function resolveNotificationRecipientIds(CounselingSession $session, int $senderId): array
    {
        $participantIds = $this->resolveActiveThreadParticipantIds($session);

        return array_values(array_filter(
            $participantIds,
            static fn (int $participantId): bool => $participantId > 0 && $participantId !== $senderId
        ));
    }

    /**
     * @return int[]
     */
    private function resolveActiveThreadParticipantIds(CounselingSession $session): array
    {
        $studentId = (int) $session->student_id;
        $counselorId = (int) $session->counselor_id;
        $peerCounselorId = (int) $session->peer_counselor_id;
        if ($peerCounselorId <= 0) {
            $peerCounselorId = (int) (PeerAssignment::query()
                ->where('session_id', (int) $session->id)
                ->whereNotNull('peer_counselor_id')
                ->whereIn('status', ['active', 'escalated'])
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->value('peer_counselor_id') ?? 0);
        }
        $isDelegatedPeerThread = $peerCounselorId > 0
            && (
                $session->assigned_role === 'peer_counselor'
                || PeerAssignment::query()
                    ->where('session_id', (int) $session->id)
                    ->where('peer_counselor_id', $peerCounselorId)
                    ->whereIn('status', ['active', 'escalated'])
                    ->exists()
            );

        $participantIds = $isDelegatedPeerThread
            ? [$studentId, $peerCounselorId, $counselorId]
            : [$studentId, $counselorId];

        return array_values(
            array_unique(
                array_filter(
                    $participantIds,
                    static fn (int $value): bool => $value > 0
                )
            )
        );
    }

    private function typingCacheKey(int $sessionId, int $userId): string
    {
        return sprintf('session:%d:typing:%d', $sessionId, $userId);
    }

    /**
     * Refresh anonymous chat session activity for TTL without updating on every HTTP poll.
     */
    private function maybeBumpAnonymousSessionActivity(CounselingSession $session): void
    {
        if (
            ! $session->is_anonymous
            || ! in_array((string) $session->status, ['pending', 'active'], true)
        ) {
            return;
        }

        $minSeconds = max(30, (int) env('ANONYMOUS_SESSION_ACTIVITY_TOUCH_SECONDS', 60));
        $updatedAt = $session->updated_at instanceof \DateTimeInterface
            ? Carbon::instance($session->updated_at)
            : null;

        if (
            $updatedAt instanceof Carbon
            && $updatedAt->greaterThanOrEqualTo(now()->subSeconds($minSeconds))
        ) {
            return;
        }

        CounselingSession::query()->whereKey((int) $session->id)->update(['updated_at' => now()]);
        $session->setAttribute('updated_at', now());
    }

    private function touchPresenceIfStale(User $user): void
    {
        $lastSeenAt = null;
        $rawLastSeenAt = $user->last_seen_at;

        if ($rawLastSeenAt instanceof \DateTimeInterface) {
            $lastSeenAt = Carbon::instance($rawLastSeenAt);
        } elseif (is_string($rawLastSeenAt) && trim($rawLastSeenAt) !== '') {
            try {
                $lastSeenAt = Carbon::parse($rawLastSeenAt);
            } catch (\Throwable) {
                $lastSeenAt = null;
            }
        }

        if (
            $lastSeenAt instanceof Carbon
            && $lastSeenAt->greaterThanOrEqualTo(now()->subSeconds(self::PRESENCE_TOUCH_INTERVAL_SECONDS))
        ) {
            return;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function deleteAttachmentFiles(Message $message): void
    {
        $message->loadMissing('chatFile');

        if ($message->chatFile) {
            $message->chatFile->deleteStoredFile();
            $message->chatFile->delete();
        }

        $fileUrl = trim((string) $message->file_url);
        if ($fileUrl === '') {
            return;
        }

        if (str_starts_with($fileUrl, 'private://')) {
            $path = Str::after($fileUrl, 'private://');
            if ($path !== '' && !str_contains($path, '..') && str_starts_with($path, 'voice-notes/')) {
                Storage::disk('local')->delete($path);
            }

            return;
        }

        $urlPath = parse_url($fileUrl, PHP_URL_PATH);
        if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/')) {
            return;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');
        if ($path === '' || str_contains($path, '..')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function tombstoneMessageForEveryone(Message $message): void
    {
        if ((string) $message->content === self::DELETE_TOMBSTONE) {
            return;
        }

        $this->deleteAttachmentFiles($message);
        $message->forceFill([
            'content' => self::DELETE_TOMBSTONE,
            'message_type' => 'text',
            'file_url' => null,
            'has_file' => false,
            'is_encrypted' => false,
        ])->save();

        Message::query()
            ->whereKey((int) $message->id)
            ->update(['updated_at' => now()]);

        CounselingSession::query()
            ->whereKey((int) $message->session_id)
            ->update(['updated_at' => now()]);
    }

    private function deleteMessageNotifications(int $messageId): void
    {
        if ($messageId <= 0) {
            return;
        }

        try {
            Notification::query()
                ->where('meta->chat_message_id', $messageId)
                ->orWhere('meta->message_id', $messageId)
                ->delete();
        } catch (\Throwable) {
            // Some SQLite versions do not support JSON path deletes consistently.
        }

        Notification::query()
            ->whereNotNull('meta')
            ->get(['id', 'meta'])
            ->filter(function (Notification $notification) use ($messageId): bool {
                $meta = is_array($notification->meta) ? $notification->meta : [];
                return (int) ($meta['chat_message_id'] ?? $meta['message_id'] ?? 0) === $messageId;
            })
            ->each(static fn (Notification $notification): ?bool => $notification->delete());
    }

    private function triggerCrisisAlert(CounselingSession $session, User $student, array $words): void
    {
        $wordList = implode(', ', $words);
        $studentName = $student->profile?->full_name ?: $student->email;
        $anonymousLabel = $this->resolveAnonymousLabel($session);
        $counselorId = $session->counselor_id;
        $peerCounselorId = $session->peer_counselor_id;
        $adminIds = User::whereHas('roles', function($q) {
            $q->where('role', 'admin')->where('approved', true);
        })->pluck('id')->all();
        
        $recipients = array_unique(array_filter(array_merge(
            $counselorId ? [$counselorId] : [],
            $peerCounselorId ? [$peerCounselorId] : [],
            $adminIds
        )));

        foreach ($recipients as $recipientId) {
            $isCounselorOrPeer = (int)$recipientId === (int)$session->counselor_id 
                || (int)$recipientId === (int)$session->peer_counselor_id;
            $viewerName = $isCounselorOrPeer && !$session->is_anonymous ? $studentName : $anonymousLabel;

            Notification::create([
                'user_id' => $recipientId,
                'title' => '🚨 Crisis Alert: Chat Trigger',
                'message' => sprintf(
                    'Student (%s) sent a message containing high-risk terms: %s. Please review the session immediately.',
                    $viewerName,
                    $wordList
                ),
                'type' => 'error', // High priority
            ]);

            $this->webPush->sendToUser(
                (int) $recipientId,
                'Emergency: crisis keywords detected',
                sprintf(
                    '%s — terms: %s. Open the session immediately.',
                    $viewerName,
                    $wordList
                ),
                $this->chatUrlForUserId((int) $recipientId, (int) $session->id),
                [
                    'tag' => 'cms-crisis-'.(int) $session->id.'-'.time(),
                    'urgency' => 'high',
                    'requireInteraction' => true,
                ]
            );
        }
    }

}
