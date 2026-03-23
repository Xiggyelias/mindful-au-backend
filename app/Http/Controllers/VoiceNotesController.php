<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\CounselingSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoiceNotesController extends Controller
{
    private const PRIVATE_DISK = 'local';
    private const PRIVATE_PREFIX = 'private://';

    public function upload(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::findOrFail($sessionId);
        $user = $request->user();

        if (!$user->hasRole('admin') && 
            $session->student_id !== $user->id && 
            $session->counselor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,ogg,webm|max:10240|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg,audio/webm',
        ]);

        $file = $request->file('audio');
        $extension = $file->guessExtension() ?: $file->extension() ?: 'webm';
        $path = $file->storeAs(
            'voice-notes/private',
            Str::uuid()->toString() . '.' . $extension,
            self::PRIVATE_DISK
        );
        if (!is_string($path) || trim($path) === '') {
            return response()->json(['message' => 'Failed to store voice note'], 500);
        }

        $message = Message::create([
            'session_id' => $sessionId,
            'sender_id' => $user->id,
            'content' => 'Voice note',
            'message_type' => 'voice',
            'file_url' => self::PRIVATE_PREFIX . ltrim($path, '/'),
            'is_encrypted' => false,
        ]);

        return response()->json($message->load('sender'), 201);
    }

    public function download(Request $request, string $messageId): StreamedResponse|JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();

        $session = $message->session;
        if (!$user->hasRole('admin') && 
            $session->student_id !== $user->id && 
            $session->counselor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->message_type !== 'voice' || !$message->file_url) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        $resolvedPath = $this->resolveVoiceNotePath((string) $message->file_url);
        if ($resolvedPath === null) {
            return response()->json(['message' => 'Invalid voice note path'], 400);
        }

        $disk = $resolvedPath['disk'];
        $path = $resolvedPath['path'];

        if (!Storage::disk($disk)->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        $fileName = basename($path);
        $mimeType = Storage::disk($disk)->mimeType($path) ?: 'audio/webm';

        return Storage::disk($disk)->response($path, $fileName, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array{disk: string, path: string}|null
     */
    private function resolveVoiceNotePath(string $storedReference): ?array
    {
        $trimmedReference = trim($storedReference);
        if ($trimmedReference === '') {
            return null;
        }

        if (str_starts_with($trimmedReference, self::PRIVATE_PREFIX)) {
            $path = ltrim(Str::after($trimmedReference, self::PRIVATE_PREFIX), '/');

            return $this->isValidVoiceNotePath($path)
                ? ['disk' => self::PRIVATE_DISK, 'path' => $path]
                : null;
        }

        if (str_starts_with($trimmedReference, 'voice-notes/')) {
            return $this->isValidVoiceNotePath($trimmedReference)
                ? ['disk' => 'public', 'path' => $trimmedReference]
                : null;
        }

        $urlPath = parse_url($trimmedReference, PHP_URL_PATH);
        if (!is_string($urlPath) || !str_starts_with($urlPath, '/storage/voice-notes/')) {
            return null;
        }

        $path = ltrim(Str::after($urlPath, '/storage/'), '/');

        return $this->isValidVoiceNotePath($path)
            ? ['disk' => 'public', 'path' => $path]
            : null;
    }

    private function isValidVoiceNotePath(string $path): bool
    {
        return $path !== ''
            && !str_contains($path, '..')
            && str_starts_with($path, 'voice-notes/');
    }
}








