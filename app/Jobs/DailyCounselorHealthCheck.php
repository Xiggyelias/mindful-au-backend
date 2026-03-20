<?php

namespace App\Jobs;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use App\Models\CounselorWellnessLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DailyCounselorHealthCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $counselors = User::whereHas('roles', function($query) {
            $query->where('role', 'counselor')->where('approved', true);
        })->get();

        foreach ($counselors as $counselor) {
            try {
                $scores = $this->computeLiveScores($counselor);

                CounselorWellnessLog::create([
                    'counselor_id' => $counselor->id,
                    'mood_score' => $scores['mood_score'],
                    'stress_level' => $scores['stress_level'],
                    'burnout_index' => $scores['burnout_index'],
                    'recommendations' => $scores['recommendations'],
                    'notes' => 'Daily automated live health check',
                    'check_in_version' => 'auto-v2',
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
                Log::error("Failed to process health check for counselor {$counselor->id}: " . $e->getMessage());
            }
        }
    }

    private function computeLiveScores(User $counselor): array
    {
        $now = now();

        $sessions7d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->get(['id', 'started_at', 'ended_at', 'created_at']);

        $sessions30d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->get(['id', 'created_at']);

        $sessionIds = $sessions30d->pluck('id')->filter()->values();
        $aiDiagnostics14d = collect();
        if ($sessionIds->isNotEmpty()) {
            $aiDiagnostics14d = AiDiagnostic::query()
                ->whereIn('session_id', $sessionIds->all())
                ->where('created_at', '>=', $now->copy()->subDays(14))
                ->get(['risk_level', 'stress_level', 'anxiety_level', 'depression_level']);
        }

        $minutes7d = 0;
        foreach ($sessions7d as $session) {
            if ($session->started_at && $session->ended_at) {
                $minutes = $session->started_at->diffInMinutes($session->ended_at, false);
                if ($minutes > 0) {
                    $minutes7d += $minutes;
                    continue;
                }
            }
            $minutes7d += 50;
        }

        $upcoming7d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $pendingApproval = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', $now)
            ->count();

        $highRiskCases = $aiDiagnostics14d->whereIn('risk_level', ['high', 'critical'])->count();

        $avgDistress = (int) round(
            $aiDiagnostics14d
                ->map(function ($item) {
                    $values = array_filter([
                        $item->stress_level,
                        $item->anxiety_level,
                        $item->depression_level,
                    ], fn ($value) => is_numeric($value));

                    if (empty($values)) {
                        return null;
                    }

                    return array_sum($values) / count($values);
                })
                ->filter(fn ($value) => $value !== null)
                ->avg() ?? 0
        );

        $workloadIndex = (int) round(
            (min(100, ($sessions7d->count() / 18) * 100) * 0.6)
            + (min(100, ($minutes7d / 900) * 100) * 0.4)
        );

        $riskExposure = $aiDiagnostics14d->count() > 0
            ? (int) round(min(100, (($highRiskCases / $aiDiagnostics14d->count()) * 100 * 0.65) + ($avgDistress * 0.35)))
            : 0;

        $schedulePressure = (int) round(min(100, ($upcoming7d * 8) + ($pendingApproval * 12)));

        if ($sessions7d->count() === 0 && $upcoming7d === 0) {
            $stress = 18;
            $burnout = 14;
            $mood = 82;
        } else {
            $stress = $this->clampInt((int) round(($workloadIndex * 0.45) + ($riskExposure * 0.30) + ($schedulePressure * 0.25)));
            $burnout = $this->clampInt((int) round(($stress * 0.58) + ($workloadIndex * 0.22) + ($riskExposure * 0.20)));
            $mood = $this->clampInt(100 - (int) round(($stress * 0.55) + ($burnout * 0.25)));
        }

        $recommendations = sprintf(
            'Live trend: %d sessions in 7 days, %d upcoming appointments. Stress %d%%, burnout %d%%.',
            $sessions7d->count(),
            $upcoming7d,
            $stress,
            $burnout
        );

        return [
            'mood_score' => $mood,
            'stress_level' => $stress,
            'burnout_index' => $burnout,
            'recommendations' => $recommendations,
        ];
    }

    private function clampInt(int $value, int $min = 0, int $max = 100): int
    {
        return max($min, min($max, $value));
    }
}








