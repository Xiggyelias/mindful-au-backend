<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stores chat/voice uploads on the configured attachments disk with an
 * automatic fallback to the private 'local' disk when the configured disk
 * is unusable (e.g. S3 credentials missing or object store unreachable).
 *
 * Returns both the stored path and the disk actually used so callers can
 * persist the disk on the chat_files row — reads must not assume the
 * globally configured disk.
 */
class ChatAttachmentStorage
{
    public const FALLBACK_DISK = 'local';

    /**
     * @return array{disk: string, path: string}|null null when every disk failed
     */
    public static function store(UploadedFile $file, string $directory, string $fileName): ?array
    {
        $configuredDisk = (string) config('chat.attachments.disk', 'local');

        foreach (array_unique([$configuredDisk, self::FALLBACK_DISK]) as $disk) {
            if (! self::diskIsConfigured($disk)) {
                Log::warning('Chat attachment disk is not configured; skipping.', ['disk' => $disk]);

                continue;
            }

            try {
                /** @var FilesystemAdapter $storage */
                $storage = Storage::disk($disk);
                $path = $storage->putFileAs($directory, $file, $fileName);

                if (is_string($path) && $path !== '') {
                    if ($disk !== $configuredDisk) {
                        Log::warning('Chat attachment stored on fallback disk.', [
                            'configured_disk' => $configuredDisk,
                            'disk' => $disk,
                            'path' => $path,
                        ]);
                    }

                    return ['disk' => $disk, 'path' => $path];
                }

                Log::error('Chat attachment disk rejected the upload.', ['disk' => $disk]);
            } catch (\Throwable $exception) {
                Log::error('Chat attachment upload failed on disk.', [
                    'disk' => $disk,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * S3 with empty credentials always fails at request time — skip it up
     * front instead of paying a doomed network round-trip on every upload.
     */
    private static function diskIsConfigured(string $disk): bool
    {
        if (config("filesystems.disks.{$disk}") === null) {
            return false;
        }

        if ((string) config("filesystems.disks.{$disk}.driver") === 's3') {
            return trim((string) config("filesystems.disks.{$disk}.key")) !== ''
                && trim((string) config("filesystems.disks.{$disk}.secret")) !== '';
        }

        return true;
    }
}
