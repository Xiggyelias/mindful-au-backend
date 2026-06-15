<?php

namespace App\Jobs;

use App\Models\CounselorWellnessLog;
use App\Models\User;
use App\Services\CounselorLiveHealthCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DailyCounselorHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CounselorLiveHealthCheckService $liveHealthCheck): void
    {
        $counselors = User::whereHas('roles', function ($query) {
            $query->where('role', 'counselor')->where('approved', true);
        })->get();

        foreach ($counselors as $counselor) {
            try {
                $summary = $liveHealthCheck->buildLiveSummary($counselor);
                if (! ($summary['has_live_activity'] ?? false)) {
                    Log::info('Skipped daily counselor health check without live activity.', [
                        'counselor_id' => $counselor->id,
                        'source' => $summary['source'] ?? null,
                    ]);

                    continue;
                }

                $scores = $summary['scores'];

                CounselorWellnessLog::create([
                    'counselor_id' => $counselor->id,
                    'mood_score' => $scores['mood_score'],
                    'stress_level' => $scores['stress_level'],
                    'burnout_index' => $scores['burnout_index'],
                    'recommendations' => $summary['recommendations'],
                    'notes' => 'Daily automated live health check',
                    'check_in_version' => 'auto-v3',
                ]);

                // Send notification if high stress/burnout
                if (($scores['stress_level'] ?? 0) > 70 || ($scores['burnout_index'] ?? 0) > 70) {
                    $counselor->notifications()->create([
                        'title' => 'Wellness Alert',
                        'message' => 'Your stress or burnout levels are elevated. Please consider taking a break or speaking with a supervisor.',
                        'type' => 'warning',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to process health check for counselor {$counselor->id}: ".$e->getMessage());
            }
        }
    }
}
