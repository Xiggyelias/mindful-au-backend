<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ChatFile extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function toAttachmentPayload(): array
    {
        return [
            'id' => (int) $this->id,
            'message_id' => (int) $this->message_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => (int) $this->file_size,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'url' => $this->signedUrl(),
            'download_url' => $this->signedUrl(true),
        ];
    }

    public function signedUrl(bool $download = false): string
    {
        $minutes = (int) config('chat.attachments.signed_url_minutes', 1440);
        $disk = (string) config('chat.attachments.disk', 'local');
        $expiry = now()->addMinutes(max(30, $minutes));

        if ($disk === 's3') {
            $options = [];
            if ($download) {
                $options['ResponseContentDisposition'] = sprintf(
                    'attachment; filename="%s"',
                    str_replace(['"', '\\'], ['_', '_'], (string) $this->file_name)
                );
            }
            /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
            $storage = Storage::disk('s3');
            return $storage->temporaryUrl($this->file_path, $expiry, $options);
        }

        return URL::temporarySignedRoute(
            'chat-files.content',
            $expiry,
            [
                'chatFile' => $this->id,
                'download' => $download ? 1 : 0,
            ]
        );
    }

    public function deleteStoredFile(): void
    {
        $disk = (string) config('chat.attachments.disk', 'local');
        if ($this->file_path && Storage::disk($disk)->exists($this->file_path)) {
            Storage::disk($disk)->delete($this->file_path);
        }
    }
}
