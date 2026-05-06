<?php

namespace App\Support;

use App\Models\Message;
use Illuminate\Support\Collection;

class ChatMessageData
{
    public static function make(Message $message, bool $includeSender = false): array
    {
        $message->loadMissing('chatFile');
        if ($includeSender) {
            $message->loadMissing('sender.profile');
        }

        $attachment = $message->chatFile?->toAttachmentPayload();

        $payload = [
            'id' => (int) $message->id,
            'session_id' => (int) $message->session_id,
            'sender_id' => (int) $message->sender_id,
            'recipient_id' => $message->recipient_id !== null ? (int) $message->recipient_id : null,
            'content' => (string) $message->content,
            'message_type' => (string) $message->message_type,
            'file_url' => $attachment['url'] ?? $message->file_url,
            'has_file' => $attachment !== null || (bool) $message->has_file,
            'attachment' => $attachment,
            'is_encrypted' => (bool) $message->is_encrypted,
            'seen_at' => $message->seen_at?->toISOString(),
            /** Same as DB `seen_at IS NOT NULL` (this API uses nullable timestamps, not a separate is_read column). */
            'is_read' => $message->seen_at !== null,
            'created_at' => $message->created_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
        ];

        if ($includeSender) {
            $payload['sender'] = $message->sender ? [
                'id' => (int) $message->sender->id,
                'name' => $message->sender->profile->full_name ?? 'Anonymous',
            ] : null;
        }

        return $payload;
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
