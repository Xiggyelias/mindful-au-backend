<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class BackupRestoreDrillCommand extends Command
{
    protected $signature = 'system:backup:drill {path? : Relative backup path in storage/app}';

    protected $description = 'Run a dry-run restore drill by validating backup readability and payload schema';

    public function handle(): int
    {
        $relativePath = trim((string) $this->argument('path'));
        $run = null;

        if ($relativePath === '') {
            $run = BackupRun::query()
                ->whereNotNull('file_path')
                ->orderByDesc('created_at')
                ->first();
            if (! $run) {
                $this->error('No backup file available for restore drill.');

                return self::FAILURE;
            }
            $relativePath = (string) $run->file_path;
        }

        $absolutePath = storage_path('app/'.ltrim($relativePath, '/'));
        if (! File::exists($absolutePath)) {
            $this->error("Backup file not found: {$relativePath}");

            return self::FAILURE;
        }

        try {
            $raw = File::get($absolutePath);
            $expectedFingerprint = data_get($run?->metadata ?? [], 'backup_encryption.key_fingerprint');
            if ($run?->is_encrypted && is_string($expectedFingerprint) && $expectedFingerprint !== '') {
                $currentFingerprint = $this->currentAppKeyFingerprint();
                if (! hash_equals($expectedFingerprint, $currentFingerprint)) {
                    throw new \RuntimeException(
                        'Backup key fingerprint mismatch. Backup was encrypted with a different APP_KEY.'
                    );
                }
            }

            $payload = str_ends_with($relativePath, '.enc')
                ? Crypt::decryptString((string) $raw)
                : (string) $raw;

            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || ! is_array($decoded['tables'] ?? null)) {
                throw new \RuntimeException('Invalid backup structure: tables section missing.');
            }

            $tables = array_keys($decoded['tables']);
            $required = ['users', 'profiles', 'user_roles', 'counseling_sessions', 'messages'];
            $missing = array_values(array_diff($required, $tables));
            if (! empty($missing)) {
                throw new \RuntimeException('Backup restore drill failed. Missing tables: '.implode(', ', $missing));
            }

            if ($run) {
                $run->update([
                    'metadata' => array_merge($run->metadata ?? [], [
                        'last_restore_drill_at' => now()->toIso8601String(),
                        'last_restore_drill_status' => 'success',
                    ]),
                ]);
            }

            $this->info('Restore drill succeeded. Verified tables: '.count($tables));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($run) {
                $run->update([
                    'metadata' => array_merge($run->metadata ?? [], [
                        'last_restore_drill_at' => now()->toIso8601String(),
                        'last_restore_drill_status' => 'failed',
                        'last_restore_drill_error' => substr($e->getMessage(), 0, 250),
                    ]),
                ]);
            }
            $this->error('Restore drill failed: '.$e->getMessage());

            return self::FAILURE;
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
