<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
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
        'disk',
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
        $available = $this->storedFileExists();

        return [
            'id' => (int) $this->id,
            'message_id' => (int) $this->message_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_type' => $this->file_type,
            'file_size' => (int) $this->file_size,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'available' => $available,
            'url' => $available ? $this->signedUrl() : null,
            'download_url' => $available ? $this->signedUrl(true) : null,
        ];
    }

    /**
     * Disk this file was actually stored on. Legacy rows (null disk) fall
     * back to the globally configured attachments disk.
     */
    public function resolveDisk(): string
    {
        $disk = trim((string) ($this->disk ?? ''));

        return $disk !== '' ? $disk : (string) config('chat.attachments.disk', 'local');
    }

    public function signedUrl(bool $download = false): string
    {
        $minutes = (int) config('chat.attachments.signed_url_minutes', 1440);
        $disk = $this->resolveDisk();
        $expiry = now()->addMinutes(max(30, $minutes));

        if ($disk === 's3') {
            $options = [];
            if ($download) {
                $options['ResponseContentDisposition'] = sprintf(
                    'attachment; filename="%s"',
                    str_replace(['"', '\\'], ['_', '_'], (string) $this->file_name)
                );
            }
            /** @var FilesystemAdapter $storage */
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
        try {
            $disk = $this->resolveDisk();
            if ($this->file_path && Storage::disk($disk)->exists($this->file_path)) {
                Storage::disk($disk)->delete($this->file_path);
            }
        } catch (\Throwable) {
            // Disk unreachable — leave the orphaned object for a cleanup job.
        }
    }

    public function storedFileExists(): bool
    {
        if (! $this->file_path) {
            return false;
        }

        try {
            return Storage::disk($this->resolveDisk())->exists($this->file_path);
        } catch (\Throwable) {
            return false;
        }
    }
}
