<?php

namespace App\Console\Commands;

use App\Models\Diagnostic;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SendDailySystemReportCommand extends Command
{
    protected $signature = 'system:daily-report';
    protected $description = 'Send the daily system summary report to admins';

    public function handle(): int
    {
        $admins = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'admin')->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        if ($admins->isEmpty()) {
            $this->info('No approved admins available. Skipping daily report.');
            return self::SUCCESS;
        }

        $today = now();
        $students = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'student')->where('approved', true);
            })
            ->count();
        $counselors = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'counselor')->where('approved', true);
            })
            ->count();
        $pendingCounselors = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->where('role', 'counselor')->where('approved', false);
            })
            ->count();
        $highRiskIn24h = Diagnostic::query()
            ->whereIn('risk_level', ['high', 'critical'])
            ->where('created_at', '>=', $today->copy()->subDay())
            ->count();

        $message = sprintf(
            'Date: %s | Students: %d | Counselors: %d | Pending counselor approvals: %d | High-risk diagnostics (24h): %d',
            $today->toDateString(),
            $students,
            $counselors,
            $pendingCounselors,
            $highRiskIn24h
        );

        foreach ($admins as $adminId) {
            Notification::query()->create([
                'user_id' => (int) $adminId,
                'title' => 'Daily System Report',
                'message' => $message,
                'type' => 'info',
            ]);
        }

        $this->info('Daily system report delivered to admins.');
        return self::SUCCESS;
    }
}

