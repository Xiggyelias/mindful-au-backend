<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\CounselingSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VoiceNotesController extends Controller
{
    public function upload(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::findOrFail($sessionId);
        $user = $request->user();

        if (!$user->hasRole('admin') && 
            $session->student_id !== $user->id && 
            $session->counselor_id !== $user->id &&
            $session->peer_counselor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav,m4a,ogg,webm|max:10240|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/ogg,audio/webm',
        ]);

        $file = $request->file('audio');
        $path = $file->storeAs(
            'voice-notes',
            Str::uuid()->toString() . '.' . $file->guessExtension(),
            'public'
        );
        $url = Storage::url($path);

        $message = Message::create([
            'session_id' => $sessionId,
            'sender_id' => $user->id,
            'content' => 'Voice note',
            'message_type' => 'voice',
            'file_url' => $url,
            'is_encrypted' => false,
        ]);

        return response()->json($message->load('sender'), 201);
    }

    public function download(Request $request, string $messageId): JsonResponse
    {
        $message = Message::findOrFail($messageId);
        $user = $request->user();

        $session = $message->session;
        if (!$user->hasRole('admin') && 
            $session->student_id !== $user->id && 
            $session->counselor_id !== $user->id &&
            $session->peer_counselor_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($message->message_type !== 'voice' || !$message->file_url) {
            return response()->json(['message' => 'Not a voice note'], 400);
        }

        $urlPath = parse_url((string) $message->file_url, PHP_URL_PATH);
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

        return response()->json([
            'download_url' => Storage::disk('public')->url($path),
            'message' => $message,
        ]);
    }
}








