<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\CounselingSession;
use App\Models\AiDiagnostic;
use App\Models\Notification;
use App\Models\PeerAssignment;
use App\Models\User;
use App\Support\ChatMessageData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceNotesController extends Controller
{
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

        if (!$this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$user->hasRole('admin') && in_array((string) $session->status, ['completed', 'cancelled'], true)) {
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
                'mimes:mp3,wav,m4a,ogg,webm,weba',
                // audio/x-matroska and video/x-matroska: older libmagic detects
                // Chrome MediaRecorder WebM blobs as Matroska instead of WebM.
                'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg,audio/webm,video/webm,audio/x-matroska,video/x-matroska,application/x-matroska',
            ],
        ]);

        $file = $request->file('audio');
        $path = $file->storeAs(
            'voice-notes',
            Str::uuid()->toString() . '.' . $file->guessExtension(),
            'local'
        );
        // Store the private path prefixed with a sentinel so the stream() method
        // can distinguish it from legacy public-disk paths (which start with /storage/).
        $url = 'private://' . $path;

        $message = Message::create([
            'session_id' => $sessionId,
            'sender_id' => $user->id,
            'recipient_id' => $this->resolveRecipientId($session, (int) $user->id),
            'content' => 'Voice note',
            'message_type' => 'voice',
            'file_url' => $url,
            'has_file' => true,
            'is_encrypted' => false,
            'sent_as_anonymous' => (bool) $session->is_anonymous,
        ]);

        $this->notifyRecipients($session, (int) $user->id, $message);

        return response()->json(ChatMessageData::make($message->load('sender.profile'), true), 201);
    }

    public function download(Request $request, string $messageId): JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();

        $session = $message->session;
        if (!$this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->message_type !== 'voice' || !$message->file_url) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        $fileUrl = (string) $message->file_url;

        // New private-disk records use the 'private://' sentinel prefix.
        // Legacy records stored the public storage URL (/storage/voice-notes/…).
        if (str_starts_with($fileUrl, 'private://')) {
            $path = Str::after($fileUrl, 'private://');
            if (str_contains($path, '..') || !str_starts_with($path, 'voice-notes/')) {
                return response()->json(['message' => 'Invalid voice note path'], 400);
            }
            if (!Storage::disk('local')->exists($path)) {
                return response()->json(['message' => 'File not found'], 404);
            }
            // For private files, return a stream URL (requires auth); never expose a direct URL.
            return response()->json([
                'stream_url' => url("/api/messages/{$messageId}/voice-note/stream"),
                'message' => $message,
            ]);
        }

        // Legacy path: file was stored on the public disk.
        $urlPath = parse_url($fileUrl, PHP_URL_PATH);
        if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/voice-notes/')) {
            return response()->json(['message' => 'Invalid voice note path'], 400);
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');
        if (str_contains($path, '..')) {
            return response()->json(['message' => 'Invalid voice note path'], 400);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Legacy files: return stream_url so client uses the auth-gated endpoint.
        return response()->json([
            'stream_url' => url("/api/messages/{$messageId}/voice-note/stream"),
            // Kept for backwards compatibility with older client builds.
            'download_url' => asset('storage/' . $path),
            'message' => $message,
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
        if (!$this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->message_type !== 'voice' || !$message->file_url) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        $fileUrl = (string) $message->file_url;

        if (str_starts_with($fileUrl, 'private://')) {
            $path = Str::after($fileUrl, 'private://');
            if (str_contains($path, '..') || !str_starts_with($path, 'voice-notes/')) {
                return response()->json(['message' => 'Invalid voice note path'], 400);
            }
            $disk = Storage::disk('local');
        } else {
            $urlPath = parse_url($fileUrl, PHP_URL_PATH);
            if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/voice-notes/')) {
                return response()->json(['message' => 'Invalid voice note path'], 400);
            }
            $path = ltrim(Str::after($urlPath, '/storage/'), '/');
            if (str_contains($path, '..')) {
                return response()->json(['message' => 'Invalid voice note path'], 400);
            }
            $disk = Storage::disk('public');
        }

        if (!$disk->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $mimeType = $disk->mimeType($path) ?: 'audio/webm';
        $size     = $disk->size($path);

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type'        => $mimeType,
            'Content-Length'      => $size,
            'Content-Disposition' => 'inline',
            'Cache-Control'       => 'private, no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges'       => 'none',
        ]);
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








