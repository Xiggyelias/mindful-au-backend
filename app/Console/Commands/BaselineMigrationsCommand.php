<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BaselineMigrationsCommand extends Command
{
    protected $signature = 'migrations:baseline
        {--dry-run : Show pending migration records without inserting}
        {--batch= : Explicit batch number to use for inserted records}';

    protected $description = 'Align the migrations table with existing migration files without executing schema changes.';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->warn('migrations table not found. Installing it first.');
            $this->call('migrate:install');
        }

        $migrationFiles = collect(File::files(database_path('migrations')))
            ->map(fn ($file) => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->sort()
            ->values();

        if ($migrationFiles->isEmpty()) {
            $this->info('No migration files found.');

            return self::SUCCESS;
        }

        $alreadyRan = DB::table('migrations')
            ->pluck('migration')
            ->all();

        $missing = $migrationFiles
            ->reject(fn ($migration) => in_array($migration, $alreadyRan, true))
            ->values();

        if ($missing->isEmpty()) {
            $this->info('Migration table is already aligned. Nothing to baseline.');

            return self::SUCCESS;
        }

        $currentMaxBatch = (int) (DB::table('migrations')->max('batch') ?? 0);
        $batchOption = $this->option('batch');
        $batch = is_numeric($batchOption)
            ? max(1, (int) $batchOption)
            : max(1, $currentMaxBatch);

        $this->info(sprintf('Found %d migration records to baseline (batch %d).', $missing->count(), $batch));

        if ($this->option('dry-run')) {
            foreach ($missing as $migration) {
                $this->line(" - {$migration}");
            }
            $this->info('Dry run complete. No records were inserted.');

            return self::SUCCESS;
        }

        $rows = $missing->map(fn ($migration) => [
            'migration' => $migration,
            'batch' => $batch,
        ])->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('migrations')->insert($chunk);
        }

        $this->info(sprintf('Baseline complete. Inserted %d migration records.', count($rows)));

        return self::SUCCESS;
    }
}
