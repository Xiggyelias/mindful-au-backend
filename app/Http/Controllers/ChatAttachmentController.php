<?php

namespace App\Http\Controllers;

use App\Models\ChatFile;
use App\Models\Message;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\User;
use App\Support\ChatMessageData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChatAttachmentController extends Controller
{
    private const ANONYMOUS_SESSION_TTL_HOURS = 24;

    public function uploadForChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|min:1',
        ]);

        return $this->upload($request, (string) $validated['session_id']);
    }

    public function upload(Request $request, string $sessionId): JsonResponse
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
                'identity_revealed_at',
                'updated_at',
            ])
            ->findOrFail($sessionId);
        $user = $request->user();
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);

        if ($this->isAnonymousSessionExpired($session)) {
            return response()->json(['message' => 'This anonymous session has expired.'], 410);
        }

        $uid = (int) $user->id;
        if (
            !$user->hasRole('admin')
            && (int) $session->student_id !== $uid
            && (int) $session->counselor_id !== $uid
            && !$isAssignedPeerCounselor
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$user->hasRole('admin') && in_array($session->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'This session is closed and cannot receive new messages.',
            ], 422);
        }

        $isDelegatedPeerThread = $session->assigned_role === 'peer_counselor'
            && (int) $session->peer_counselor_id > 0;
        if (
            ! $user->hasRole('admin')
            && $isDelegatedPeerThread
            && $isAssignedPeerCounselor
        ) {
            return response()->json([
                'message' => 'Peer counselors cannot upload attachments in supervised chat.',
            ], 422);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (int) config('chat.attachments.max_upload_kb', 5120),
                'extensions:' . implode(',', (array) config('chat.attachments.allowed_extensions', [])),
                'mimetypes:' . implode(',', (array) config('chat.attachments.allowed_mime_types', [])),
            ],
            'message_type' => 'nullable|in:file,voice',
        ]);

        $file = $request->file('file');
        $disk = (string) config('chat.attachments.disk', 'local');
        $directory = trim((string) config('chat.attachments.directory', 'uploads/chat_files'), '/');
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'));
        $storedFileName = Str::uuid()->toString() . '.' . $extension;
        $datedDirectory = trim($directory . '/' . now()->format('Y/m'), '/');
        $storedPath = Storage::disk($disk)->putFileAs($datedDirectory, $file, $storedFileName);

        if (!$storedPath) {
            return response()->json([
                'message' => 'Unable to store attachment.',
            ], 500);
        }

        $messageType = (string) $request->input('message_type', '');
        if ($messageType === '') {
            $messageType = str_starts_with((string) $file->getMimeType(), 'audio/') ? 'voice' : 'file';
        }

        $message = null;
        $chatFile = null;

        try {
            DB::beginTransaction();

            $message = Message::create([
                'session_id' => $sessionId,
                'sender_id' => $user->id,
                'recipient_id' => $this->resolveRecipientId($session, (int) $user->id),
                'content' => $this->sanitizeFileName((string) $file->getClientOriginalName(), $extension),
                'message_type' => $messageType,
                'file_url' => null,
                'has_file' => true,
                'is_encrypted' => false,
                'sent_as_anonymous' => (bool) $session->is_anonymous,
                'seen_at' => null,
            ]);

            $chatFile = ChatFile::create([
                'message_id' => $message->id,
                'file_name' => $this->sanitizeFileName((string) $file->getClientOriginalName(), $extension),
                'file_path' => $storedPath,
                'file_type' => (string) $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            Storage::disk($disk)->delete($storedPath);
            throw $exception;
        }

        $message->setRelation('chatFile', $chatFile);

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

        try {
            $this->notifyRecipient($session, (int) $user->id, $message, $messageType);
        } catch (\Throwable $_) {
            // no-op
        }

        return response()->json(ChatMessageData::make($message, true), 201);
    }

    public function download(Request $request, string $messageId): JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();
        $session = $message->session()->with(['student', 'counselor'])->first() ?? $message->session;
        $isAssignedPeerCounselor = $this->isAssignedPeerCounselor($user, $session);
        $uid = (int) $user->id;

        if (
            !$user->hasRole('admin')
            && (int) $session->student_id !== $uid
            && (int) $session->counselor_id !== $uid
            && !$isAssignedPeerCounselor
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->loadMissing('chatFile');

        if ($message->chatFile) {
            return response()->json([
                'download_url' => $message->chatFile->signedUrl(true),
                'message' => ChatMessageData::make($message, true),
            ]);
        }

        if (!in_array((string) $message->message_type, ['file', 'voice'], true) || !$message->file_url) {
            return response()->json(['message' => 'Not a file attachment'], 400);
        }

        $urlPath = parse_url((string) $message->file_url, PHP_URL_PATH);
        if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/chat-attachments/')) {
            return response()->json(['message' => 'Invalid attachment path'], 400);
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');
        if (str_contains($path, '..')) {
            return response()->json(['message' => 'Invalid attachment path'], 400);
        }
        
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return response()->json([
            'download_url' => Storage::disk('public')->url($path),
            'message' => ChatMessageData::make($message, true),
        ]);
    }

    public function show(Request $request, ChatFile $chatFile): BinaryFileResponse
    {
        $disk = (string) config('chat.attachments.disk', 'local');
        abort_unless(Storage::disk($disk)->exists($chatFile->file_path), 404);

        $absolutePath = Storage::disk($disk)->path($chatFile->file_path);
        $download = filter_var((string) $request->query('download', '0'), FILTER_VALIDATE_BOOL);
        $disposition = $download ? 'attachment' : 'inline';
        $safeName = str_replace(['\\', '"'], ['_', ''], (string) $chatFile->file_name);

        return response()->file($absolutePath, [
            'Content-Type' => $chatFile->file_type,
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $safeName),
            'Cache-Control' => 'private, max-age=300',
        ]);
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

    private function sanitizeFileName(string $fileName, string $fallbackExtension): string
    {
        $extension = strtolower(trim(pathinfo($fileName, PATHINFO_EXTENSION)));
        if ($extension === '') {
            $extension = $fallbackExtension;
        }

        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $normalizedBaseName = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($baseName)) ?: 'attachment';
        $normalizedBaseName = trim((string) $normalizedBaseName, '._-');
        if ($normalizedBaseName === '') {
            $normalizedBaseName = 'attachment';
        }

        return sprintf('%s.%s', Str::limit($normalizedBaseName, 80, ''), $extension);
    }

    private function isAssignedPeerCounselor(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('peer_counselor')
            && (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor';
    }

    private function notifyRecipient(
        CounselingSession $session,
        int $senderId,
        Message $message,
        string $messageType,
    ): void {
        $recipientId = $this->resolveRecipientId($session, $senderId);
        if (!$recipientId || $recipientId === $senderId) {
            return;
        }

        $sender = User::with('profile', 'roles')->find($senderId);
        $senderName = optional(optional($sender)->profile)->full_name
            ?: ($sender?->email ? Str::before($sender->email, '@') : 'Someone');

        if (
            (int) $session->student_id === $senderId
            && $this->shouldMaskStudentIdentityForRecipient($session, $recipientId, $message->sent_as_anonymous)
        ) {
            $senderName = $this->resolveAnonymousLabel($session);
        }

        $preview = $messageType === 'voice' ? 'sent a voice note' : 'sent an attachment';

        Notification::create([
            'user_id' => $recipientId,
            'title' => 'New message',
            'message' => sprintf(
                '%s: %s',
                $senderName,
                $preview
            ),
            'meta' => [
                'chat_session_id' => (int) $session->id,
                'chat_message_id' => (int) $message->id,
                'is_encrypted' => (bool) $message->is_encrypted,
                'message_type' => $messageType,
            ],
            'type' => 'info',
        ]);
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
}
