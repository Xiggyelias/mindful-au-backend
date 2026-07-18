<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\ChatFile;
use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\Notification;
use App\Models\PeerAssignment;
use App\Models\User;
use App\Support\ChatAttachmentStorage;
use App\Support\ChatMessageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceNotesController extends Controller
{
    private const ANONYMOUS_SESSION_TTL_HOURS = 24;

    private function viewerCanAccessVoiceThread(User $user, CounselingSession $session): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        $uid = (int) $user->id;
        if ((int) $session->student_id === $uid || (int) $session->counselor_id === $uid) {
            return true;
        }

        return $this->isAssignedPeerCounselor($user, $session);
    }

    public function upload(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::findOrFail($sessionId);
        $user = $request->user();

        if (! $this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        if (! $user->hasRole('admin') && in_array((string) $session->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'This session is closed and cannot receive new voice notes.',
            ], 422);
        }

        if ($this->isAssignedPeerCounselor($user, $session)) {
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
        }

        $request->validate([
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimes:mp3,wav,m4a,aac,ogg,webm,weba,mp4',
                // audio/x-matroska and video/x-matroska: older libmagic detects
                // Chrome MediaRecorder WebM blobs as Matroska instead of WebM.
                'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/m4a,audio/aac,audio/x-aac,audio/ogg,audio/webm,video/webm,video/mp4,video/quicktime,audio/quicktime,audio/x-matroska,video/x-matroska,application/x-matroska',
            ],
        ]);

        $file = $request->file('audio');
        $directory = trim((string) config('chat.attachments.directory', 'uploads/chat_files'), '/');
        $extension = strtolower((string) (
            $file->getClientOriginalExtension()
            ?: $file->extension()
            ?: $file->guessExtension()
            ?: 'webm'
        ));
        $storedFileName = Str::uuid()->toString().'.'.$extension;
        $datedDirectory = trim($directory.'/voice-notes/'.now()->format('Y/m'), '/');
        $stored = ChatAttachmentStorage::store($file, $datedDirectory, $storedFileName);

        if ($stored === null) {
            return response()->json([
                'message' => 'Unable to store voice note.',
            ], 500);
        }

        $message = null;
        $chatFile = null;

        try {
            DB::beginTransaction();

            $message = Message::create([
                'session_id' => $sessionId,
                'sender_id' => $user->id,
                'recipient_id' => $this->resolveRecipientId($session, (int) $user->id),
                'content' => 'Voice note',
                'message_type' => 'voice',
                'file_url' => null,
                'has_file' => true,
                'is_encrypted' => false,
                'sent_as_anonymous' => (bool) $session->is_anonymous,
            ]);

            $chatFileData = [
                'message_id' => $message->id,
                'file_name' => $this->voiceFileName((string) $file->getClientOriginalName(), $extension),
                'file_path' => $stored['path'],
                'file_type' => strtolower((string) ($file->getMimeType() ?: 'audio/webm')),
                'file_size' => (int) $file->getSize(),
                'uploaded_at' => now(),
            ];

            if (ChatFile::hasDiskColumn()) {
                $chatFileData['disk'] = $stored['disk'];
            }

            $chatFile = ChatFile::create($chatFileData);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            Storage::disk($stored['disk'])->delete($stored['path']);
            throw $exception;
        }

        $message->setRelation('chatFile', $chatFile);

        $this->notifyRecipients($session, (int) $user->id, $message);

        return response()->json(ChatMessageData::make($message->load('sender.profile'), true), 201);
    }

    public function download(Request $request, string $messageId): JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();

        $session = $message->session;
        if (! $session) {
            return response()->json(['message' => 'Conversation no longer exists.'], 404);
        }
        if (! $this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        $message->loadMissing('chatFile');

        if ($message->message_type !== 'voice' || (! $message->file_url && ! $message->chatFile)) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        if ($message->chatFile) {
            if (! $message->chatFile->storedFileExists()) {
                return response()->json(['message' => 'File not found'], 404);
            }

            return response()->json([
                'stream_url' => url("/api/messages/{$messageId}/voice-note/stream"),
                'download_url' => $message->chatFile->signedUrl(true),
                'message' => $this->messagePayloadForViewer($message, $session, $user, true),
            ]);
        }

        $fileUrl = (string) $message->file_url;

        // New private-disk records use the 'private://' sentinel prefix.
        // Legacy records stored the public storage URL (/storage/voice-notes/…).
        if (str_starts_with($fileUrl, 'private://')) {
            $path = Str::after($fileUrl, 'private://');
            if (str_contains($path, '..') || ! str_starts_with($path, 'voice-notes/')) {
                return response()->json(['message' => 'Invalid voice note path'], 400);
            }
            if (! Storage::disk('local')->exists($path)) {
                return response()->json(['message' => 'File not found'], 404);
            }

            // For private files, return a stream URL (requires auth); never expose a direct URL.
            return response()->json([
                'stream_url' => url("/api/messages/{$messageId}/voice-note/stream"),
                'message' => $this->messagePayloadForViewer($message, $session, $user, true),
            ]);
        }

        // Legacy path: older voice rows stored a public storage URL instead of
        // a chat_files row or private:// pointer.
        $path = $this->legacyPublicVoicePath($fileUrl);
        if ($path === null) {
            return response()->json(['message' => 'Invalid voice note path'], 400);
        }

        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Legacy files: return stream_url so client uses the auth-gated endpoint.
        return response()->json([
            'stream_url' => url("/api/messages/{$messageId}/voice-note/stream"),
            // Kept for backwards compatibility with older client builds.
            'download_url' => asset('storage/'.$path),
            'message' => $this->messagePayloadForViewer($message, $session, $user, true),
        ]);
    }

    /**
     * Stream voice note audio directly through the controller so that
     * clients never receive a bare public-storage URL. Authentication is
     * enforced by the 'auth:sanctum' middleware on this route.
     */
    public function stream(Request $request, string $messageId): StreamedResponse|JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();

        $session = $message->session;
        if (! $session) {
            return response()->json(['message' => 'Conversation no longer exists.'], 404);
        }
        if (! $this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        $message->loadMissing('chatFile');

        if ($message->message_type !== 'voice' || (! $message->file_url && ! $message->chatFile)) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        $mimeType = null;

        if ($message->chatFile) {
            $locatedDisk = $message->chatFile->locateDisk();
            if ($locatedDisk === null) {
                return response()->json(['message' => 'File not found'], 404);
            }
            $disk = Storage::disk($locatedDisk);
            $path = (string) $message->chatFile->file_path;
            $mimeType = (string) $message->chatFile->file_type;
        } else {
            $fileUrl = (string) $message->file_url;

            if (str_starts_with($fileUrl, 'private://')) {
                $path = Str::after($fileUrl, 'private://');
                if (str_contains($path, '..') || ! str_starts_with($path, 'voice-notes/')) {
                    return response()->json(['message' => 'Invalid voice note path'], 400);
                }
                $disk = Storage::disk('local');
            } else {
                $path = $this->legacyPublicVoicePath($fileUrl);
                if ($path === null) {
                    return response()->json(['message' => 'Invalid voice note path'], 400);
                }
                $disk = Storage::disk('public');
            }
        }

        if (! $disk->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $mimeType = $this->voiceResponseContentType($mimeType ?: ($disk->mimeType($path) ?: 'audio/webm'));
        $totalSize = $disk->size($path);

        // Handle HTTP Range requests — required by all browsers for audio seeking/playback.
        $rangeHeader = $request->header('Range', '');
        $start = 0;
        $end = $totalSize - 1;
        $statusCode = 200;
        $rangeResponseHeader = null;

        if ($rangeHeader !== '' && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $m)) {
            $start = (int) $m[1];
            $end = ($m[2] !== '') ? (int) $m[2] : $totalSize - 1;
            $end = min($end, $totalSize - 1);
            $start = max(0, min($start, $end));
            $statusCode = 206;
            $rangeResponseHeader = "bytes {$start}-{$end}/{$totalSize}";
        }

        $length = $end - $start + 1;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => $length,
            'Content-Disposition' => 'inline',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($rangeResponseHeader !== null) {
            $headers['Content-Range'] = $rangeResponseHeader;
        }

        return response()->stream(function () use ($disk, $path, $start, $length) {
            $stream = $disk->readStream($path);
            if (! $stream) {
                return;
            }
            try {
                if ($start > 0) {
                    // Remote streams (S3) are not seekable — fseek would fail
                    // silently and we'd serve bytes from offset 0 under a 206
                    // Content-Range, corrupting playback. Read and discard.
                    $meta = stream_get_meta_data($stream);
                    if (! empty($meta['seekable'])) {
                        fseek($stream, $start);
                    } else {
                        $toSkip = $start;
                        while ($toSkip > 0 && ! feof($stream)) {
                            $skipped = fread($stream, min(65536, $toSkip));
                            if ($skipped === false || $skipped === '') {
                                break;
                            }
                            $toSkip -= strlen($skipped);
                        }
                    }
                }
                $remaining = $length;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(65536, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    $this->flushStreamOutput();
                }
            } finally {
                fclose($stream);
            }
        }, $statusCode, $headers);
    }

    private function flushStreamOutput(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    private function voiceResponseContentType(string $mimeType): string
    {
        $baseMimeType = strtolower(trim(explode(';', $mimeType)[0] ?? ''));
        if ($baseMimeType === '') {
            return 'audio/webm';
        }

        if ($baseMimeType === 'video/webm' || str_contains($baseMimeType, 'matroska')) {
            return 'audio/webm';
        }

        if (str_starts_with($baseMimeType, 'audio/')) {
            return $baseMimeType;
        }

        if ($baseMimeType === 'video/mp4' || $baseMimeType === 'video/quicktime' || str_contains($baseMimeType, 'mp4') || str_contains($baseMimeType, 'm4a') || str_contains($baseMimeType, 'quicktime') || str_contains($baseMimeType, 'aac')) {
            return 'audio/mp4';
        }

        return 'audio/webm';
    }

    private function resolveRecipientId(CounselingSession $session, int $senderId): ?int
    {
        if ((int) $session->student_id === $senderId) {
            $peerCounselorId = $this->activeCasePeerCounselorId($session);

            return $peerCounselorId > 0
                ? $peerCounselorId
                : ($session->counselor_id ? (int) $session->counselor_id : null);
        }

        if ($this->activeCasePeerCounselorId($session) === $senderId || (int) $session->counselor_id === $senderId) {
            return (int) $session->student_id;
        }

        return null;
    }

    private function voiceFileName(string $originalName, string $extension): string
    {
        $extension = trim(strtolower($extension), '.');
        $baseName = trim(pathinfo($originalName, PATHINFO_FILENAME));
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName) ?: 'voice-note';
        $baseName = trim($baseName, '.-_') ?: 'voice-note';

        return Str::limit($baseName, 120, '').'.'.($extension !== '' ? $extension : 'webm');
    }

    private function legacyPublicVoicePath(string $fileUrl): ?string
    {
        $urlPath = parse_url($fileUrl, PHP_URL_PATH);
        if (! is_string($urlPath) || ! str_starts_with($urlPath, '/storage/')) {
            return null;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        foreach (['voice-notes/', 'chat-attachments/', 'uploads/chat_files/'] as $allowedPrefix) {
            if (str_starts_with($path, $allowedPrefix)) {
                return $path;
            }
        }

        return null;
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
            ->where('session_id', (string) $session->id)
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

        return $studentDiagnostic ? strtolower((string) $studentDiagnostic) : null;
    }

    private function notifyRecipients(CounselingSession $session, int $senderId, Message $message): void
    {
        $recipientId = $this->resolveRecipientId($session, $senderId);
        if (! $recipientId || $recipientId === $senderId) {
            return;
        }

        $sender = User::with('profile')->find($senderId);
        $senderName = optional(optional($sender)->profile)->full_name
            ?: ($sender?->email ? Str::before((string) $sender->email, '@') : 'Someone');

        if (
            (int) $session->student_id === $senderId
            && $this->shouldMaskStudentIdentityForRecipient($session, $recipientId, $message->sent_as_anonymous)
        ) {
            $senderName = $this->resolveAnonymousLabel($session);
        }

        Notification::create([
            'user_id' => $recipientId,
            'title' => 'New voice note',
            'message' => "{$senderName}: sent a voice note",
            'meta' => [
                'chat_session_id' => (int) $session->id,
                'chat_message_id' => (int) $message->id,
                'is_encrypted' => false,
                'message_type' => 'voice',
            ],
            'type' => 'info',
        ]);
    }

    private function isAnonymousSessionExpired(CounselingSession $session): bool
    {
        if (! $session->is_anonymous) {
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
        if (! $recipient) {
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

    private function messagePayloadForViewer(
        Message $message,
        CounselingSession $session,
        User $viewer,
        bool $includeSender = false,
    ): array {
        $payloadMessage = clone $message;

        if ($this->shouldMaskStudentIdentityForRecipient($session, (int) $viewer->id, $payloadMessage->sent_as_anonymous)) {
            if ((int) $payloadMessage->sender_id === (int) $session->student_id) {
                $payloadMessage->sender_id = 0;
                $payloadMessage->sender_name_snapshot = $this->resolveAnonymousLabel($session);
                $payloadMessage->unsetRelation('sender');
            }

            if ((int) $payloadMessage->recipient_id === (int) $session->student_id) {
                $payloadMessage->recipient_id = 0;
            }
        }

        return ChatMessageData::make($payloadMessage, $includeSender);
    }

    private function activeCasePeerCounselorId(CounselingSession $session): int
    {
        $peerCounselorId = (int) ($session->peer_counselor_id ?? 0);
        if ($peerCounselorId > 0 && $session->assigned_role === 'peer_counselor') {
            return $peerCounselorId;
        }

        return (int) (PeerAssignment::query()
            ->where('session_id', (int) $session->id)
            ->whereNotNull('peer_counselor_id')
            ->whereIn('status', ['active', 'escalated'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->value('peer_counselor_id') ?? 0);
    }
}
