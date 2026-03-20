<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class RepairPasswordHashes extends Command
{
    protected $signature = 'auth:repair-hashes {--dry-run}';
    protected $description = 'Find and re-hash plain text passwords in the database';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $users = User::all();
        $repaired = 0;
        $skipped = 0;

        $this->info('Scanning users for unhashed passwords...');
        $this->line('');

        foreach ($users as $user) {
            // Check if password looks like a bcrypt hash (starts with $2y$ or $2a$ or $2b$)
            if (preg_match('/^\$2[aby]\$/', $user->password)) {
                $this->line("✓ {$user->email} - Already hashed");
                $skipped++;
            } else {
                // Password is likely plain text
                $this->warn("✗ {$user->email} - Plain text password detected!");
                
                if (!$dryRun) {
                    // Re-hash the password (assume it's plain text)
                    $user->update(['password' => Hash::make($user->password)]);
                    $this->info("  → Re-hashed successfully");
                }
                $repaired++;
            }
        }

        $this->line('');
        $this->info("Summary:");
        $this->line("  Skipped (already hashed): $skipped");
        $this->line("  Repaired: $repaired");

        if ($dryRun && $repaired > 0) {
            $this->warn("  (Dry run mode - no changes made)");
            $this->line("  Run without --dry-run to apply fixes");
        }
    }
}
