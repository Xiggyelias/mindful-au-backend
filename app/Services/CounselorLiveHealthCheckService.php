<?php

namespace App\Services;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\CounselorWellnessLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class CounselorLiveHealthCheckService
{
    private const CHECK_IN_VERSION = 'v1';

    public function buildLiveSummary(User $counselor, ?Carbon $now = null): array
    {
        $now ??= now();

        $baseMetrics = $this->calculateLiveMetrics($counselor, $now);
        $scoreData = $this->calculateLiveScores($baseMetrics, $counselor, $now);
        $scores = $scoreData['values'];

        $metrics = [
            'sessions_7d' => $baseMetrics['sessions_7d_count'],
            'sessions_30d' => $baseMetrics['sessions_30d_count'],
            'completed_appointments_7d' => $baseMetrics['completed_appointments_7d'],
            'completed_appointments_30d' => $baseMetrics['completed_appointments_30d'],
            'active_days_7d' => $baseMetrics['active_days_7d'],
            'session_minutes_7d' => $baseMetrics['session_minutes_7d'],
            'upcoming_appointments_3d' => $baseMetrics['upcoming_appointments_3d'],
            'upcoming_appointments_7d' => $baseMetrics['upcoming_appointments_7d'],
            'pending_appointments' => $baseMetrics['pending_appointments'],
            'high_risk_ai_diagnostics_14d' => $baseMetrics['high_risk_ai_diagnostics_14d'],
            'ai_diagnostics_14d' => $baseMetrics['ai_diagnostics_14d_count'],
            'avg_distress_signal_14d' => $baseMetrics['avg_distress_signal_14d'],
            'workload_index' => $scoreData['indices']['workload_index'],
            'risk_exposure_index' => $scoreData['indices']['risk_exposure_index'],
            'schedule_pressure_index' => $scoreData['indices']['schedule_pressure_index'],
            'live_data_points' => $baseMetrics['live_data_points'],
            'last_activity_at' => $baseMetrics['last_activity_at'],
        ];

        return [
            'generated_at' => $now->toIso8601String(),
            'source' => $scoreData['source'],
            'has_live_activity' => $baseMetrics['has_live_activity'],
            'scores' => $scores,
            'labels' => [
                'wellness' => $this->wellnessLabel($scores['mood_score']),
                'stress' => $this->pressureLabel($scores['stress_level']),
                'burnout' => $this->pressureLabel($scores['burnout_index']),
            ],
            'metrics' => $metrics,
            'recommendations' => $this->buildLiveRecommendations($scores, $metrics, $scoreData['source']),
        ];
    }

    private function calculateLiveMetrics(User $counselor, Carbon $now): array
    {
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $fourteenDaysAgo = $now->copy()->subDays(14);
        $upcomingStatuses = ['scheduled', 'confirmed', 'pending'];

        $sessions7d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where(function ($query) use ($sevenDaysAgo) {
                $query->where('created_at', '>=', $sevenDaysAgo)
                    ->orWhere('updated_at', '>=', $sevenDaysAgo)
                    ->orWhere('started_at', '>=', $sevenDaysAgo)
                    ->orWhere('ended_at', '>=', $sevenDaysAgo);
            })
            ->get(['id', 'status', 'started_at', 'ended_at', 'created_at', 'updated_at']);

        $sessions30d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where(function ($query) use ($thirtyDaysAgo) {
                $query->where('created_at', '>=', $thirtyDaysAgo)
                    ->orWhere('updated_at', '>=', $thirtyDaysAgo)
                    ->orWhere('started_at', '>=', $thirtyDaysAgo)
                    ->orWhere('ended_at', '>=', $thirtyDaysAgo);
            })
            ->get(['id', 'status', 'started_at', 'ended_at', 'created_at', 'updated_at']);

        $appointments7d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$sevenDaysAgo, $now])
            ->get(['id', 'status', 'scheduled_at', 'duration_minutes', 'created_at', 'updated_at']);

        $appointments30d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'completed')
            ->whereBetween('scheduled_at', [$thirtyDaysAgo, $now])
            ->get(['id', 'status', 'scheduled_at', 'duration_minutes', 'created_at', 'updated_at']);

        $sessionIds30d = $sessions30d->pluck('id')->filter()->values();
        $appointmentContextIds30d = $appointments30d->pluck('id')->map(fn ($id) => "apt_{$id}")->values();
        $allContextIds = $sessionIds30d->merge($appointmentContextIds30d);

        $aiDiagnostics14d = collect();
        if ($allContextIds->isNotEmpty()) {
            $aiDiagnostics14d = AiDiagnostic::query()
                ->whereIn('session_id', $allContextIds->all())
                ->where('created_at', '>=', $fourteenDaysAgo)
                ->get(['risk_level', 'stress_level', 'anxiety_level', 'depression_level', 'created_at']);
        }

        $merged7d = $sessions7d->concat($appointments7d);
        $minutes7d = $this->sumSessionMinutes($merged7d);
        $activeDays7d = $merged7d
            ->map(function ($item) {
                $reference = $item->started_at ?? $item->scheduled_at ?? $item->updated_at ?? $item->created_at;
                if (! $reference) {
                    return null;
                }

                return ($reference instanceof Carbon ? $reference : Carbon::parse($reference))->toDateString();
            })
            ->filter()
            ->unique()
            ->count();

        $upcoming7d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->whereIn('status', $upcomingStatuses)
            ->whereBetween('scheduled_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $upcoming3d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->whereIn('status', $upcomingStatuses)
            ->whereBetween('scheduled_at', [$now, $now->copy()->addDays(3)])
            ->count();

        $pendingAppointments = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'pending')
            ->where('scheduled_at', '>=', $now)
            ->count();

        $highRiskDiagnostics14d = $aiDiagnostics14d
            ->whereIn('risk_level', ['high', 'critical'])
            ->count();

        $avgDiagnosticLoad = (int) round(
            $aiDiagnostics14d
                ->map(function ($diagnostic) {
                    $levels = array_filter([
                        $diagnostic->stress_level,
                        $diagnostic->anxiety_level,
                        $diagnostic->depression_level,
                    ], fn ($value) => is_numeric($value));

                    if (empty($levels)) {
                        return null;
                    }

                    return array_sum($levels) / count($levels);
                })
                ->filter(fn ($value) => $value !== null)
                ->avg() ?? 0
        );

        $lastActivityAt = $merged7d
            ->concat($aiDiagnostics14d)
            ->map(fn ($item) => $item->ended_at ?? $item->scheduled_at ?? $item->updated_at ?? $item->created_at ?? null)
            ->filter()
            ->map(fn ($value) => $value instanceof Carbon ? $value : Carbon::parse($value))
            ->sortDesc()
            ->first();

        $liveDataPoints = $merged7d->count() + $upcoming7d + $aiDiagnostics14d->count();

        return [
            'sessions_7d_count' => $sessions7d->count() + $appointments7d->count(),
            'sessions_30d_count' => $sessions30d->count() + $appointments30d->count(),
            'completed_appointments_7d' => $appointments7d->count(),
            'completed_appointments_30d' => $appointments30d->count(),
            'active_days_7d' => $activeDays7d,
            'session_minutes_7d' => $minutes7d,
            'upcoming_appointments_3d' => $upcoming3d,
            'upcoming_appointments_7d' => $upcoming7d,
            'pending_appointments' => $pendingAppointments,
            'high_risk_ai_diagnostics_14d' => $highRiskDiagnostics14d,
            'ai_diagnostics_14d_count' => $aiDiagnostics14d->count(),
            'avg_distress_signal_14d' => $avgDiagnosticLoad,
            'live_data_points' => $liveDataPoints,
            'has_live_activity' => $liveDataPoints > 0,
            'last_activity_at' => $lastActivityAt?->toIso8601String(),
        ];
    }

    private function calculateLiveScores(array $metrics, User $counselor, Carbon $now): array
    {
        $recentSelfCheckIn = CounselorWellnessLog::query()
            ->where('counselor_id', $counselor->id)
            ->where('check_in_version', self::CHECK_IN_VERSION)
            ->latest()
            ->first();

        $hasRecentSelfCheckIn = $recentSelfCheckIn
            && $recentSelfCheckIn->created_at
            && $recentSelfCheckIn->created_at->gte($now->copy()->subHours(48));

        $emptyIndices = [
            'workload_index' => 0,
            'risk_exposure_index' => 0,
            'schedule_pressure_index' => 0,
        ];

        if (! $metrics['has_live_activity']) {
            if ($hasRecentSelfCheckIn) {
                return [
                    'source' => 'self-check-in-only',
                    'values' => [
                        'mood_score' => $this->nullableInt($recentSelfCheckIn->mood_score),
                        'stress_level' => $this->nullableInt($recentSelfCheckIn->stress_level),
                        'burnout_index' => $this->nullableInt($recentSelfCheckIn->burnout_index),
                    ],
                    'indices' => $emptyIndices,
                ];
            }

            return [
                'source' => 'live-insufficient-data',
                'values' => [
                    'mood_score' => null,
                    'stress_level' => null,
                    'burnout_index' => null,
                ],
                'indices' => $emptyIndices,
            ];
        }

        $workloadFromSessions = min(100, ($metrics['sessions_7d_count'] / 18) * 100);
        $workloadFromMinutes = min(100, ($metrics['session_minutes_7d'] / 900) * 100);
        $workloadIndex = (int) round(($workloadFromSessions * 0.6) + ($workloadFromMinutes * 0.4));

        $riskRatio = $metrics['ai_diagnostics_14d_count'] > 0
            ? $metrics['high_risk_ai_diagnostics_14d'] / $metrics['ai_diagnostics_14d_count']
            : 0.0;
        $riskExposure = (int) round(min(100, ($riskRatio * 100 * 0.65) + ($metrics['avg_distress_signal_14d'] * 0.35)));

        $schedulePressure = (int) round(min(
            100,
            ($metrics['upcoming_appointments_3d'] * 18)
            + ($metrics['pending_appointments'] * 12)
            + ($metrics['upcoming_appointments_7d'] * 6)
        ));

        $recoveryPenalty = match (true) {
            $metrics['active_days_7d'] >= 7 => 15,
            $metrics['active_days_7d'] >= 6 => 10,
            $metrics['active_days_7d'] >= 5 => 6,
            default => 0,
        };

        $stress = $this->clampInt(
            round(($workloadIndex * 0.45) + ($riskExposure * 0.30) + ($schedulePressure * 0.25) + $recoveryPenalty)
        );

        $burnout = $this->clampInt(
            round(($stress * 0.58) + ($workloadIndex * 0.22) + ($riskExposure * 0.20) + ($metrics['active_days_7d'] >= 6 ? 6 : 0))
        );

        $mood = $this->clampInt(
            round(100 - (($stress * 0.55) + ($burnout * 0.25)) + ($metrics['active_days_7d'] <= 4 ? 8 : 0))
        );

        $source = 'live-computed';
        if ($hasRecentSelfCheckIn) {
            $mood = $this->blendWithSelfCheckIn($mood, $recentSelfCheckIn->mood_score);
            $stress = $this->blendWithSelfCheckIn($stress, $recentSelfCheckIn->stress_level);
            $burnout = $this->blendWithSelfCheckIn($burnout, $recentSelfCheckIn->burnout_index);
            $source = 'live-computed+self-check-in';
        }

        return [
            'source' => $source,
            'values' => [
                'mood_score' => $mood,
                'stress_level' => $stress,
                'burnout_index' => $burnout,
            ],
            'indices' => [
                'workload_index' => $workloadIndex,
                'risk_exposure_index' => $riskExposure,
                'schedule_pressure_index' => $schedulePressure,
            ],
        ];
    }

    private function sumSessionMinutes($sessions): int
    {
        $total = 0;

        foreach ($sessions as $session) {
            if ($session->started_at !== null && $session->ended_at !== null) {
                $start = $session->started_at instanceof Carbon
                    ? $session->started_at
                    : Carbon::parse($session->started_at);
                $end = $session->ended_at instanceof Carbon
                    ? $session->ended_at
                    : Carbon::parse($session->ended_at);
                $minutes = $start->diffInMinutes($end, false);
                if ($minutes > 0) {
                    $total += $minutes;

                    continue;
                }
            }

            if (isset($session->duration_minutes) && $session->duration_minutes > 0) {
                $total += (int) $session->duration_minutes;

                continue;
            }

            $total += 50;
        }

        return $total;
    }

    private function buildLiveRecommendations(array $scores, array $metrics, string $source): string
    {
        if ($source === 'live-insufficient-data') {
            return 'No live counseling activity was found for this counselor yet. Complete sessions, appointments, or a self check-in to generate a live wellness score.';
        }

        if ($source === 'self-check-in-only') {
            return 'Only your recent self check-in is available right now. Live workload scores will update after sessions, appointments, or risk reviews are recorded.';
        }

        $tips = [];

        $tips[] = sprintf(
            'Live trend: %d sessions in the last 7 days, %d upcoming in the next 7 days.',
            (int) ($metrics['sessions_7d'] ?? 0),
            (int) ($metrics['upcoming_appointments_7d'] ?? 0)
        );

        $stress = (int) ($scores['stress_level'] ?? 0);
        $burnout = (int) ($scores['burnout_index'] ?? 0);
        $mood = (int) ($scores['mood_score'] ?? 0);

        if ($stress >= 70) {
            $tips[] = 'Stress is high. Keep a 10-minute buffer between sessions and defer non-urgent admin tasks.';
        } elseif ($stress >= 40) {
            $tips[] = 'Stress is moderate. Maintain short recovery breaks every 2 sessions.';
        } else {
            $tips[] = 'Stress is currently manageable based on live workload and risk signals.';
        }

        if ($burnout >= 70) {
            $tips[] = 'Burnout risk is elevated. Reduce overtime this week and schedule peer supervision.';
        } elseif ($burnout >= 40) {
            $tips[] = 'Burnout risk is moderate. Protect sleep and boundaries after work hours.';
        }

        $highRiskCases = (int) ($metrics['high_risk_ai_diagnostics_14d'] ?? 0);
        if ($highRiskCases > 0) {
            $tips[] = "You handled {$highRiskCases} high-risk diagnostics in 14 days. Plan a debrief to reduce emotional carry-over.";
        }

        if ($mood <= 45) {
            $tips[] = 'Your wellbeing score is low. Please book a support check-in this week.';
        }

        return implode(' ', $tips);
    }

    private function blendWithSelfCheckIn(int $computedValue, ?int $selfCheckInValue): int
    {
        if (! is_numeric($selfCheckInValue)) {
            return $computedValue;
        }

        return $this->clampInt(round(($computedValue * 0.45) + (((int) $selfCheckInValue) * 0.55)));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? $this->clampInt((int) $value) : null;
    }

    private function clampInt(float|int $value, int $min = 0, int $max = 100): int
    {
        return (int) max($min, min($max, round($value)));
    }

    private function pressureLabel(?int $score): string
    {
        if ($score === null) {
            return 'No live data';
        }

        if ($score >= 70) {
            return 'High';
        }

        if ($score >= 40) {
            return 'Moderate';
        }

        return 'Low';
    }

    private function wellnessLabel(?int $score): string
    {
        if ($score === null) {
            return 'No live data';
        }

        if ($score >= 70) {
            return 'Good';
        }

        if ($score >= 50) {
            return 'Moderate';
        }

        return 'Needs Attention';
    }
}
