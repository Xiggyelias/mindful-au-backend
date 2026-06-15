<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class VerifyDatabaseBackupCommand extends Command
{
    protected $signature = 'system:backup:verify {--notify : Notify admins if verification fails}';

    protected $description = 'Verify integrity and readability of the latest backup snapshot';

    public function handle(): int
    {
        $run = BackupRun::query()
            ->whereNotNull('file_path')
            ->orderByDesc('created_at')
            ->first();

        if (! $run) {
            $this->error('No backup runs found to verify.');

            return self::FAILURE;
        }

        $absolutePath = storage_path('app/'.ltrim((string) $run->file_path, '/'));
        if (! File::exists($absolutePath)) {
            $run->update([
                'verification_status' => 'failed',
                'error_message' => 'Backup file is missing.',
            ]);
            $this->maybeNotifyFailure("Backup verification failed. Missing file: {$run->file_path}");
            $this->error('Backup file missing.');

            return self::FAILURE;
        }

        try {
            $raw = File::get($absolutePath);
            $actualChecksum = hash('sha256', (string) $raw);
            if (! empty($run->checksum_sha256) && ! hash_equals((string) $run->checksum_sha256, (string) $actualChecksum)) {
                throw new \RuntimeException('Backup checksum mismatch.');
            }

            $expectedFingerprint = data_get($run->metadata ?? [], 'backup_encryption.key_fingerprint');
            if ($run->is_encrypted && is_string($expectedFingerprint) && $expectedFingerprint !== '') {
                $currentFingerprint = $this->currentAppKeyFingerprint();
                if (! hash_equals($expectedFingerprint, $currentFingerprint)) {
                    throw new \RuntimeException(
                        'Backup key fingerprint mismatch. Backup was encrypted with a different APP_KEY.'
                    );
                }
            }

            $jsonPayload = $run->is_encrypted
                ? Crypt::decryptString((string) $raw)
                : (string) $raw;
            $decoded = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded) || ! array_key_exists('tables', $decoded)) {
                throw new \RuntimeException('Backup structure is invalid.');
            }

            $tableCount = is_array($decoded['tables']) ? count($decoded['tables']) : 0;
            $run->update([
                'verification_status' => 'verified',
                'error_message' => null,
                'metadata' => array_merge($run->metadata ?? [], [
                    'verified_at' => now()->toIso8601String(),
                    'verified_table_count' => $tableCount,
                    'verified_with_key_fingerprint' => $run->is_encrypted
                        ? $this->currentAppKeyFingerprint()
                        : null,
                ]),
            ]);

            $this->info("Backup verification successful ({$tableCount} tables).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update([
                'verification_status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
                'metadata' => array_merge($run->metadata ?? [], [
                    'verified_at' => now()->toIso8601String(),
                    'verification_failed' => true,
                ]),
            ]);
            $this->maybeNotifyFailure('Backup verification failed: '.$e->getMessage());
            $this->error('Backup verification failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function maybeNotifyFailure(string $message): void
    {
        if (! $this->option('notify')) {
            return;
        }

        $adminIds = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'admin')->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($adminIds as $adminId) {
            Notification::query()->create([
                'user_id' => (int) $adminId,
                'title' => 'Backup Verification Failed',
                'message' => $message,
                'type' => 'error',
            ]);
        }
    }

    private function currentAppKeyFingerprint(): string
    {
        $appKey = trim((string) config('app.key', ''));
        if ($appKey === '') {
            return 'app-key-missing';
        }

        return hash('sha256', $appKey);
    }
}
