<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\CounselingSession;
use App\Models\User;
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

        return $user->hasRole('peer_counselor')
            && (int) $session->peer_counselor_id === $uid
            && $session->assigned_role === 'peer_counselor';
    }

    public function upload(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::findOrFail($sessionId);
        $user = $request->user();

        if (!$this->viewerCanAccessVoiceThread($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'audio' => [
                'required',
                'file',
                'max:10240',
                'mimes:mp3,wav,m4a,ogg,webm',
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
            'content' => 'Voice note',
            'message_type' => 'voice',
            'file_url' => $url,
            'is_encrypted' => false,
            'sent_as_anonymous' => (bool) $session->is_anonymous,
        ]);

        return response()->json($message->load('sender'), 201);
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
}








