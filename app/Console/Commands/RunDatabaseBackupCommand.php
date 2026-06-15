<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class RunDatabaseBackupCommand extends Command
{
    protected $signature = 'system:backup {--notify : Notify admins when backup completes}';

    protected $description = 'Create a JSON backup snapshot of core tables';

    public function handle(): int
    {
        $isEncrypted = $this->shouldEncryptBackup();
        $run = BackupRun::query()->create([
            'status' => 'processing',
            'is_encrypted' => $isEncrypted,
            'verification_status' => 'pending',
            'started_at' => now(),
            'metadata' => array_merge([
                'triggered_by' => 'scheduler_or_manual',
            ], $this->backupEncryptionMetadata($isEncrypted)),
        ]);

        try {
            $tables = [
                'users',
                'profiles',
                'user_roles',
                'appointments',
                'counseling_sessions',
                'messages',
                'notifications',
                'diagnostics',
                'ai_diagnostics',
                'system_settings',
            ];

            $maxRowsPerTable = max(100, (int) env('AUTO_BACKUP_MAX_ROWS', 10000));
            $snapshot = [
                'generated_at' => now()->toIso8601String(),
                'connection' => config('database.default'),
                'tables' => [],
            ];

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $totalRows = (int) DB::table($table)->count();
                $rows = DB::table($table)
                    ->limit($maxRowsPerTable)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                $snapshot['tables'][$table] = [
                    'total_rows' => $totalRows,
                    'captured_rows' => count($rows),
                    'truncated' => $totalRows > $maxRowsPerTable,
                    'rows' => $rows,
                ];
            }

            $jsonPayload = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (! is_string($jsonPayload)) {
                throw new \RuntimeException('Failed to encode backup payload.');
            }

            $fileSuffix = $isEncrypted ? '.json.enc' : '.json';
            $relativePath = 'backups/system-backup-'.now()->format('Ymd-His').$fileSuffix;
            $absolutePath = storage_path('app/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));

            $payloadToWrite = $isEncrypted ? Crypt::encryptString($jsonPayload) : $jsonPayload;
            File::put($absolutePath, $payloadToWrite);
            $fileSize = (int) File::size($absolutePath);
            $checksum = hash('sha256', (string) $payloadToWrite);

            $run->update([
                'status' => 'success',
                'file_path' => $relativePath,
                'file_size_bytes' => $fileSize,
                'checksum_sha256' => $checksum,
                'is_encrypted' => $isEncrypted,
                'verification_status' => 'pending',
                'finished_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], $this->backupEncryptionMetadata($isEncrypted), [
                    'generated_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->pruneOldBackups();

            if ($this->option('notify')) {
                $this->notifyAdmins($relativePath);
            }

            $this->info("Backup written to storage/app/{$relativePath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'verification_status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
                'finished_at' => now(),
            ]);

            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function notifyAdmins(string $fileName): void
    {
        $admins = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'admin')->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($admins as $adminId) {
            Notification::query()->create([
                'user_id' => (int) $adminId,
                'title' => 'Database Backup Complete',
                'message' => "Automatic backup saved: {$fileName}",
                'type' => 'info',
            ]);
        }
    }

    private function shouldEncryptBackup(): bool
    {
        return filter_var((string) env('AUTO_BACKUP_ENCRYPT', true), FILTER_VALIDATE_BOOL);
    }

    private function backupEncryptionMetadata(bool $isEncrypted): array
    {
        return [
            'backup_encryption' => [
                'scheme' => $isEncrypted ? 'laravel_crypt' : 'plain_json',
                'key_source' => $isEncrypted ? 'app_key' : null,
                'key_fingerprint' => $isEncrypted ? $this->currentAppKeyFingerprint() : null,
                'rotation_note' => $isEncrypted
                    ? 'If APP_KEY rotates, restore with previous APP_KEY or re-encrypt legacy backups first.'
                    : null,
            ],
        ];
    }

    private function currentAppKeyFingerprint(): string
    {
        $appKey = trim((string) config('app.key', ''));
        if ($appKey === '') {
            return 'app-key-missing';
        }

        return hash('sha256', $appKey);
    }

    private function pruneOldBackups(): void
    {
        $retentionDays = max(1, (int) env('AUTO_BACKUP_RETENTION_DAYS', 14));
        $cutoff = now()->subDays($retentionDays);

        $staleRuns = BackupRun::query()
            ->whereNotNull('file_path')
            ->where('created_at', '<', $cutoff)
            ->get(['id', 'file_path']);

        foreach ($staleRuns as $run) {
            $path = storage_path('app/'.ltrim((string) $run->file_path, '/'));
            try {
                if (File::exists($path)) {
                    File::delete($path);
                }
                $run->update([
                    'file_path' => null,
                    'verification_status' => 'pruned',
                ]);
            } catch (\Throwable) {
                // Skip file issues and continue pruning.
            }
        }
    }
}
