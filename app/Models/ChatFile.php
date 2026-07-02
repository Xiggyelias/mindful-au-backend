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

    /** Memoized result of locateDisk() for this request. */
    private string|false|null $locatedDisk = null;

    /**
     * Find the disk the file actually exists on, probing the recorded disk
     * first and then the other known chat-upload disks. Legacy rows predate
     * the disk column, so after CHAT_UPLOAD_DISK changes (local -> s3) the
     * recorded/configured disk no longer matches where their bytes live.
     * When the file turns up on a different disk, persist it so future
     * reads skip the probe. Returns null when the file is gone everywhere.
     */
    public function locateDisk(): ?string
    {
        if ($this->locatedDisk !== null) {
            return $this->locatedDisk === false ? null : $this->locatedDisk;
        }

        if (! $this->file_path) {
            $this->locatedDisk = false;

            return null;
        }

        $candidates = array_values(array_unique(array_filter([
            $this->resolveDisk(),
            (string) config('chat.attachments.disk', 'local'),
            'local',
            'public',
        ], fn (string $disk): bool => config("filesystems.disks.{$disk}") !== null)));

        foreach ($candidates as $disk) {
            try {
                if (! Storage::disk($disk)->exists($this->file_path)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            if ($this->exists && $disk !== trim((string) ($this->disk ?? ''))) {
                $this->forceFill(['disk' => $disk])->saveQuietly();
            }

            $this->locatedDisk = $disk;

            return $disk;
        }

        $this->locatedDisk = false;

        return null;
    }

    public function signedUrl(bool $download = false): string
    {
        $minutes = (int) config('chat.attachments.signed_url_minutes', 1440);
        $disk = $this->locateDisk() ?? $this->resolveDisk();
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
            $disk = $this->locateDisk();
            if ($disk !== null && $this->file_path) {
                Storage::disk($disk)->delete($this->file_path);
            }
        } catch (\Throwable) {
            // Disk unreachable — leave the orphaned object for a cleanup job.
        }
    }

    public function storedFileExists(): bool
    {
        return $this->locateDisk() !== null;
    }
}
