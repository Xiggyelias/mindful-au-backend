<?php

namespace App\Support;

use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ChatMessageData
{
    private const DELETE_TOMBSTONE = 'This message was deleted.';

    public static function make(Message $message, bool $includeSender = false): array
    {
        $message->loadMissing('chatFile');
        if ($includeSender) {
            $message->loadMissing('sender.profile');
        }

        $attachment = $message->chatFile?->toAttachmentPayload();
        $senderRole = self::normalizeSenderRole($message);
        $senderName = self::resolveSenderName($message, $senderRole);
        $deleteWindowMinutes = max(1, (int) config('chat.delete_for_everyone_minutes', 15));
        $deleteForEveryoneUntil = $message->created_at?->copy()->addMinutes($deleteWindowMinutes);
        $isDeleted = (string) $message->content === self::DELETE_TOMBSTONE;

        $payload = [
            'id' => (int) $message->id,
            'case_id' => $message->case_id !== null ? (int) $message->case_id : (int) $message->session_id,
            'session_id' => (int) $message->session_id,
            'sender_id' => (int) $message->sender_id,
            'sender_role' => $senderRole,
            'sender_name_snapshot' => $senderName,
            'sender_display_name' => $senderName,
            'recipient_id' => $message->recipient_id !== null ? (int) $message->recipient_id : null,
            'content' => (string) $message->content,
            'message_type' => (string) $message->message_type,
            'file_url' => $attachment['url'] ?? $message->file_url,
            'has_file' => $attachment !== null || (bool) $message->has_file,
            'attachment' => $attachment,
            'is_encrypted' => (bool) $message->is_encrypted,
            'sent_as_anonymous' => $message->sent_as_anonymous === null
                ? null
                : (bool) $message->sent_as_anonymous,
            'seen_at' => $message->seen_at?->toISOString(),
            /** Same as DB `seen_at IS NOT NULL` (this API uses nullable timestamps, not a separate is_read column). */
            'is_read' => $message->seen_at !== null,
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
            'is_deleted' => $isDeleted,
            'delete_for_everyone_until' => $isDeleted ? null : $deleteForEveryoneUntil?->toISOString(),
        ];

        if ($includeSender) {
            $payload['sender'] = $message->sender ? [
                'id' => (int) $message->sender->id,
                'name' => $senderName,
                'role' => $senderRole,
            ] : null;
        }

        return $payload;
    }

    private static function normalizeSenderRole(Message $message): string
    {
        $role = trim((string) ($message->sender_role ?? ''));
        if (in_array($role, ['student', 'peer_counselor', 'counselor', 'admin'], true)) {
            return $role;
        }

        $sender = $message->relationLoaded('sender') ? $message->sender : null;
        if ($sender?->hasRole('admin')) {
            return 'admin';
        }
        if ($sender?->hasRole('counselor')) {
            return 'counselor';
        }
        if ($sender?->hasRole('peer_counselor')) {
            return 'peer_counselor';
        }

        return 'student';
    }

    private static function resolveSenderName(Message $message, string $senderRole): string
    {
        $snapshot = trim((string) ($message->sender_name_snapshot ?? ''));
        if ($snapshot !== '') {
            return $snapshot;
        }

        $sender = $message->relationLoaded('sender') ? $message->sender : null;
        $profileName = trim((string) ($sender?->profile?->full_name ?? ''));
        if ($profileName !== '') {
            return $profileName;
        }

        $email = trim((string) ($sender?->email ?? ''));
        if ($email !== '') {
            return Str::before($email, '@');
        }

        return match ($senderRole) {
            'admin' => 'Admin',
            'counselor' => 'Counselor',
            'peer_counselor' => 'Peer Counselor',
            default => 'Student',
        };
    }

    /**
     * @param  iterable<Message>  $messages
     * @return array<int, array<string, mixed>>
     */
    public static function collection(iterable $messages, bool $includeSender = false): array
    {
        return Collection::make($messages)
            ->map(static fn (Message $message): array => self::make($message, $includeSender))
            ->values()
            ->all();
    }
}
