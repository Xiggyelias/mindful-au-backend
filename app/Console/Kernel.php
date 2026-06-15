<?php

namespace App\Console;

use App\Jobs\DailyCounselorHealthCheck;
use App\Support\SystemSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run daily counselor health check at 9 AM
        $schedule->job(new DailyCounselorHealthCheck)->daily()->at('09:00');

        // Send daily admin summary only when enabled in system settings.
        $schedule->command('system:daily-report')
            ->daily()
            ->at('08:00')
            ->when(fn () => $this->isSettingEnabled('daily_reports'));

        // Run automated backup only when enabled in system settings.
        $schedule->command('system:backup --notify')
            ->daily()
            ->at('02:00')
            ->when(fn () => $this->isSettingEnabled('auto_backup'));

        // Verify latest backup integrity after backup run.
        $schedule->command('system:backup:verify --notify')
            ->daily()
            ->at('02:30')
            ->when(fn () => $this->isSettingEnabled('auto_backup'));

        // Run a weekly restore drill to validate disaster recovery readiness.
        $schedule->command('system:backup:drill')
            ->weeklyOn(0, '03:00')
            ->when(fn () => $this->isSettingEnabled('auto_backup'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    private function isSettingEnabled(string $key): bool
    {
        try {
            return SystemSettings::getBool($key, false);
        } catch (\Throwable) {
            return false;
        }
    }
}
