<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Message;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\PeerAssignment;
use App\Models\User;
use App\Support\ChatMessageData;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    private const TYPING_STATE_TTL_SECONDS = 5;
    private const PRESENCE_TOUCH_INTERVAL_SECONDS = 15;
    private const ANONYMOUS_SESSION_TTL_HOURS = 24;

    protected $mlService;

    public function __construct(\App\Services\MentalHealthMlService $mlService)
    {
        $this->mlService = $mlService;
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

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (
            !$this->viewerCanAccessMessagingThread($user, $session, $isAssignedPeerCounselor)
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $this->touchPresenceIfStale($user);

        $validated = $request->validate([
            'after_id' => 'nullable|integer|min:0',
            'before_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:30',
        ]);

        $limit = (int) ($validated['limit'] ?? 30);
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
                'session_id',
                'sender_id',
                'recipient_id',
                'content',
                'message_type',
                'file_url',
                'has_file',
                'is_encrypted',
                'seen_at',
                'created_at',
                'updated_at',
            ]);

        // Delegated peer-support threads should show only messages from the active
        // peer assignment window, so counselor and peer histories stay separated.
        $isDelegatedPeerThread = $session->assigned_role === 'peer_counselor'
            && (int) $session->peer_counselor_id > 0;
        $isParticipantViewer = (int) $session->student_id === (int) $user->id
            || (int) $session->counselor_id === (int) $user->id
            || $isAssignedPeerCounselor;

        if (!$user->hasRole('admin') && $isDelegatedPeerThread && $isParticipantViewer) {
            $targetPeerId = (int) $session->peer_counselor_id;
            $assignedAt = PeerAssignment::query()
                ->where('session_id', $session->id)
                ->where('peer_counselor_id', $targetPeerId)
                ->where('status', 'active')
                ->latest('assigned_at')
                ->value('assigned_at');

            if ($assignedAt) {
                $query->where('created_at', '>=', $assignedAt);
            }
        }

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

        $maskStudentIdentityForViewer = $this->shouldMaskStudentIdentityForRecipient($session, $viewerId);
        if ($maskStudentIdentityForViewer) {
            $studentId = (int) $session->student_id;
            $messages->transform(
                function (Message $message) use ($studentId): Message {
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
                    }
                    if ((int) $message->recipient_id === $studentId) {
                        $message->recipient_id = 0;
                    }

                    return $message;
                }
            );
        }

        return response()->json(ChatMessageData::collection($messages));
    }

    public function indexBySession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|min:1',
        ]);

        return $this->index($request, (string) $validated['session_id']);
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

        $isDelegatedPeerThread = $session->assigned_role === 'peer_counselor'
            && (int) $session->peer_counselor_id > 0;
        $isSessionCounselor = (int) $session->counselor_id === (int) $user->id;
        if (!$user->hasRole('admin') && $isDelegatedPeerThread && $isSessionCounselor) {
            return response()->json([
                'message' => 'This case is delegated to a peer counselor. Use your counselor chat thread to message the student.',
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

            /*
            if ($messageType !== 'text') {
                return response()->json([
                    'message' => 'Peer counselors can only send text messages in supervised chat.',
                ], 422);
            }
            */
        }

        $isEncrypted = array_key_exists('is_encrypted', $validated)
            ? (bool) $validated['is_encrypted']
            : $this->inferEncryptionFlag($content);

        $recipientId = $this->resolveRecipientId($session, (int) $user->id);

        $requiresEncryption = SystemSettings::getBool('data_encryption', true);
        if (
            $requiresEncryption
            && $messageType === 'text'
            && !$isEncrypted
            && !$this->isHandshakeEnvelope($messageType, $content)
        ) {
            return response()->json([
                'message' => 'Secure messaging is required right now. Please refresh and retry.',
            ], 422);
        }

        $message = Message::create([
            'session_id' => $sessionId,
            'sender_id' => $user->id,
            'recipient_id' => $recipientId,
            'content' => $content,
            'message_type' => $messageType,
            'file_url' => $validated['file_url'] ?? null,
            'has_file' => !empty($validated['file_url']),
            'is_encrypted' => $isEncrypted,
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
            $this->notifyMessageRecipient($session, $user->id, $validated, $isEncrypted);
        } catch (\Throwable $_) {
            // no-op
        }

        return response()->json(ChatMessageData::make($message), 201);
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

        if (
            !$user->hasRole('admin')
            && (int) $message->sender_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You can only delete messages you sent.',
            ], 403);
        }

        $deletedMessageId = (int) $message->id;
        $this->deleteAttachmentFiles($message);
        $message->delete();

        return response()->json([
            'ok' => true,
            'id' => $deletedMessageId,
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
        bool $isEncrypted
    ): void {
        $recipientId = $this->resolveRecipientId($session, $senderId);

        if (!$recipientId || $recipientId === $senderId) {
            return;
        }

        $messageType = (string) ($validated['message_type'] ?? 'text');
        $content = (string) ($validated['content'] ?? '');

        if ($this->isHandshakeEnvelope($messageType, $content)) {
            return;
        }

        $sender = User::with('profile')->find($senderId);

        $senderName = optional(optional($sender)->profile)->full_name
            ?: ($sender?->email ? Str::before($sender->email, '@') : 'Someone');

        if (
            (int) $session->student_id === (int) $senderId
            && $this->shouldMaskStudentIdentityForRecipient($session, (int) $recipientId)
        ) {
            $senderName = $this->resolveAnonymousLabel($session);
        }

        Notification::create([
            'user_id' => $recipientId,
            'title' => 'New message',
            'message' => sprintf(
                '%s: %s',
                $senderName,
                $this->buildMessagePreview($messageType, $content, $isEncrypted)
            ),
            'type' => 'info',
        ]);
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

    private function isAssignedPeerCounselor(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('peer_counselor')
            && (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor';
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
        int $recipientId
    ): bool {
        if (!$session->is_anonymous) {
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

    private function resolveAnonymousLabel(CounselingSession $session): string
    {
        $value = trim((string) ($session->anonymous_id ?? ''));
        if ($value !== '') {
            return $value;
        }

        return 'User_' . str_pad((string) ((int) $session->id % 10000), 4, '0', STR_PAD_LEFT);
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

        if ((int) $session->student_id === $senderId) {
            if ($session->assigned_role === 'peer_counselor' && $session->peer_counselor_id) {
                $recipientId = (int) $session->peer_counselor_id;
            } else {
                $recipientId = $session->counselor_id ? (int) $session->counselor_id : null;
            }
        } elseif ((int) $session->peer_counselor_id === $senderId) {
            $recipientId = (int) $session->student_id;
        } elseif ((int) $session->counselor_id === $senderId) {
            $recipientId = (int) $session->student_id;
        }

        return $recipientId && $recipientId > 0 ? $recipientId : null;
    }

    /**
     * @return int[]
     */
    private function resolveActiveThreadParticipantIds(CounselingSession $session): array
    {
        $studentId = (int) $session->student_id;
        $counselorId = (int) $session->counselor_id;
        $peerCounselorId = (int) $session->peer_counselor_id;
        $isDelegatedPeerThread = $session->assigned_role === 'peer_counselor' && $peerCounselorId > 0;

        $participantIds = $isDelegatedPeerThread
            ? [$studentId, $peerCounselorId]
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

    private function triggerCrisisAlert(CounselingSession $session, User $student, array $words): void
    {
        $wordList = implode(', ', $words);
        $studentName = $student->profile?->full_name ?: $student->email;
        $anonymousLabel = $this->resolveAnonymousLabel($session);
        
        $counselorId = $session->counselor_id;
        $adminIds = User::whereHas('roles', function($q) { $q->where('role', 'admin'); })->pluck('id')->all();
        
        $recipients = array_unique(array_merge(
            $counselorId ? [$counselorId] : [],
            $adminIds
        ));

        foreach ($recipients as $recipientId) {
            $isCounselorForSession = (int)$recipientId === (int)$session->counselor_id;
            $viewerName = $isCounselorForSession && !$session->is_anonymous ? $studentName : $anonymousLabel;

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
        }
    }

}
