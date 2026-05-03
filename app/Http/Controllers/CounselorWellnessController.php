<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\CounselorWellnessLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CounselorWellnessController extends Controller
{
    private const CHECK_IN_VERSION = 'v1';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? $request->counselor_id
            : $user->id;

        $logs = CounselorWellnessLog::where('counselor_id', $counselorId)
            ->latest()
            ->get();

        return response()->json($logs);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? (int) $request->counselor_id
            : $user->id;

        $counselor = User::findOrFail($counselorId);

        $latestLog = CounselorWellnessLog::where('counselor_id', $counselor->id)
            ->latest()
            ->first();

        $summary = $this->buildLiveSummary($counselor);

        return response()->json([
            ...$summary,
            'latest_log' => $latestLog,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can create wellness logs'], 403);
        }

        $validated = $request->validate([
            'mood_score' => 'sometimes|integer|min:0|max:100',
            'stress_level' => 'sometimes|integer|min:0|max:100',
            'burnout_index' => 'sometimes|integer|min:0|max:100',
            'notes' => 'sometimes|string|max:2000',
            'check_in' => 'sometimes|array',
            'check_in.emotional_drain' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.disconnect_difficulty' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.calm_control' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.energy_level' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.break_quality' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.support_level' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.sleep_quality' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.burnout_worry' => 'required_with:check_in|integer|min:0|max:4',
        ]);

        $hasManualScore =
            array_key_exists('mood_score', $validated) ||
            array_key_exists('stress_level', $validated) ||
            array_key_exists('burnout_index', $validated);

        $hasCheckIn = array_key_exists('check_in', $validated);
        $hasNotes = array_key_exists('notes', $validated) && trim((string) $validated['notes']) !== '';

        if (!$hasManualScore && !$hasCheckIn && !$hasNotes) {
            return response()->json([
                'message' => 'Provide either check-in answers, score values, or notes.',
            ], 422);
        }

        $payload = [
            'counselor_id' => $user->id,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($hasCheckIn) {
            $scores = $this->calculateCheckInScores($validated['check_in']);
            $payload['mood_score'] = $scores['mood_score'];
            $payload['stress_level'] = $scores['stress_level'];
            $payload['burnout_index'] = $scores['burnout_index'];
            $payload['recommendations'] = $this->buildCheckInRecommendations($scores);
            $payload['check_in_answers'] = $validated['check_in'];
            $payload['check_in_version'] = self::CHECK_IN_VERSION;
        } else {
            if (array_key_exists('mood_score', $validated)) {
                $payload['mood_score'] = $validated['mood_score'];
            }
            if (array_key_exists('stress_level', $validated)) {
                $payload['stress_level'] = $validated['stress_level'];
            }
            if (array_key_exists('burnout_index', $validated)) {
                $payload['burnout_index'] = $validated['burnout_index'];
            }
        }

        $log = CounselorWellnessLog::create([
            ...$payload,
        ]);

        return response()->json($log, 201);
    }

    public function runHealthCheck(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? $request->counselor_id
            : $user->id;

        $counselor = User::findOrFail($counselorId);
        $summary = $this->buildLiveSummary($counselor);
        $scores = $summary['scores'];

        $log = CounselorWellnessLog::create([
            'counselor_id' => $counselor->id,
            'mood_score' => $scores['mood_score'] ?? null,
            'stress_level' => $scores['stress_level'] ?? null,
            'burnout_index' => $scores['burnout_index'] ?? null,
            'recommendations' => $summary['recommendations'] ?? null,
            'notes' => 'Automated health check from live activity data',
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

        return response()->json([
            ...$log->toArray(),
            'summary' => $summary,
        ], 201);
    }

    private function calculateCheckInScores(array $answers): array
    {
        // Values are 0-4 on a frequency scale.
        $emotionalDrain = (int) $answers['emotional_drain'];
        $disconnectDifficulty = (int) $answers['disconnect_difficulty'];
        $calmControl = (int) $answers['calm_control'];
        $energyLevel = (int) $answers['energy_level'];
        $breakQuality = (int) $answers['break_quality'];
        $supportLevel = (int) $answers['support_level'];
        $sleepQuality = (int) $answers['sleep_quality'];
        $burnoutWorry = (int) $answers['burnout_worry'];

        $inverseCalm = 4 - $calmControl;
        $inverseBreaks = 4 - $breakQuality;
        $inverseEnergy = 4 - $energyLevel;
        $inverseSleep = 4 - $sleepQuality;

        $stressRaw = ($emotionalDrain + $disconnectDifficulty + $inverseCalm + $inverseBreaks + $burnoutWorry) / 5;
        $burnoutRaw = ($emotionalDrain + $disconnectDifficulty + $burnoutWorry + $inverseEnergy + $inverseSleep) / 5;
        $moodRaw = ($calmControl + $energyLevel + $breakQuality + $supportLevel + $sleepQuality) / 5;

        return [
            'stress_level' => (int) round($stressRaw * 25),
            'burnout_index' => (int) round($burnoutRaw * 25),
            'mood_score' => (int) round($moodRaw * 25),
        ];
    }

    private function buildCheckInRecommendations(array $scores): string
    {
        $stress = (int) ($scores['stress_level'] ?? 0);
        $burnout = (int) ($scores['burnout_index'] ?? 0);
        $mood = (int) ($scores['mood_score'] ?? 0);

        $tips = [];

        if ($stress >= 70) {
            $tips[] = 'High stress detected. Block two short recovery breaks between sessions and reduce non-urgent tasks today.';
        } elseif ($stress >= 45) {
            $tips[] = 'Moderate stress detected. Add a 10-minute reset after every 2 sessions.';
        }

        if ($burnout >= 70) {
            $tips[] = 'Burnout risk is high. Please escalate workload concerns to your supervisor and avoid overtime this week.';
        } elseif ($burnout >= 45) {
            $tips[] = 'Burnout risk is moderate. Prioritize sleep, boundaries, and peer support check-ins.';
        }

        if ($mood <= 35) {
            $tips[] = 'Wellbeing score is low. Consider a same-week peer debrief or counselor support session.';
        } elseif ($mood <= 55) {
            $tips[] = 'Wellbeing is fair. Maintain routines: hydration, meal timing, and short movement breaks.';
        }

        if (empty($tips)) {
            $tips[] = 'Wellness looks stable. Keep your current routines and continue daily check-ins.';
        }

        return implode(' ', $tips);
    }

    private function buildLiveSummary(User $counselor): array
    {
        $now = now();

        $sessions7d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->get(['id', 'status', 'started_at', 'ended_at', 'created_at']);

        $appointments7d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $now->copy()->subDays(7))
            ->get(['id', 'status', 'scheduled_at as started_at', 'scheduled_at as created_at', 'duration_minutes']);

        $sessions30d = CounselingSession::query()
            ->where('counselor_id', $counselor->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->get(['id', 'status', 'started_at', 'ended_at', 'created_at']);

        $appointments30d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'completed')
            ->where('scheduled_at', '>=', $now->copy()->subDays(30))
            ->get(['id', 'status', 'scheduled_at as started_at', 'scheduled_at as created_at', 'duration_minutes']);

        $sessionIds30d = $sessions30d->pluck('id')->filter()->values();
        $aptIds30d = $appointments30d->pluck('id')->map(fn($id) => "apt_{$id}")->values();

        $aiDiagnostics14d = collect();
        $allContextIds = $sessionIds30d->merge($aptIds30d);
        
        if ($allContextIds->isNotEmpty()) {
            $aiDiagnostics14d = AiDiagnostic::query()
                ->whereIn('session_id', $allContextIds->all())
                ->where('created_at', '>=', $now->copy()->subDays(14))
                ->get(['risk_level', 'stress_level', 'anxiety_level', 'depression_level']);
        }

        $merged7d = $sessions7d->concat($appointments7d);
        $minutes7d = $this->sumSessionMinutes($merged7d);
        $activeDays7d = $merged7d
            ->map(function ($session) {
                $reference = $session->started_at ?? $session->created_at;
                return $reference ? $reference->toDateString() : null;
            })
            ->filter()
            ->unique()
            ->count();

        $upcoming7d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$now, $now->copy()->addDays(7)])
            ->count();

        $upcoming3d = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$now, $now->copy()->addDays(3)])
            ->count();

        $pendingApproval = Appointment::query()
            ->where('counselor_id', $counselor->id)
            ->where('status', 'scheduled')
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

        $workloadFromSessions = min(100, ($merged7d->count() / 18) * 100);
        $workloadFromMinutes = min(100, ($minutes7d / 900) * 100);
        $workloadIndex = (int) round(($workloadFromSessions * 0.6) + ($workloadFromMinutes * 0.4));

        $riskRatio = $aiDiagnostics14d->count() > 0
            ? $highRiskDiagnostics14d / $aiDiagnostics14d->count()
            : 0.0;
        $riskExposure = (int) round(min(100, ($riskRatio * 100 * 0.65) + ($avgDiagnosticLoad * 0.35)));

        $schedulePressure = (int) round(min(100, ($upcoming3d * 18) + ($pendingApproval * 12) + ($upcoming7d * 6)));

        $recoveryPenalty = 0;
        if ($activeDays7d >= 7) {
            $recoveryPenalty = 15;
        } elseif ($activeDays7d >= 6) {
            $recoveryPenalty = 10;
        } elseif ($activeDays7d >= 5) {
            $recoveryPenalty = 6;
        }

        if ($merged7d->count() === 0 && $upcoming7d === 0) {
            $stress = 18;
            $burnout = 14;
            $mood = 82;
        } else {
            $stress = $this->clampInt(
                round(($workloadIndex * 0.45) + ($riskExposure * 0.30) + ($schedulePressure * 0.25) + $recoveryPenalty)
            );

            $burnout = $this->clampInt(
                round(($stress * 0.58) + ($workloadIndex * 0.22) + ($riskExposure * 0.20) + ($activeDays7d >= 6 ? 6 : 0))
            );

            $mood = $this->clampInt(
                round(100 - (($stress * 0.55) + ($burnout * 0.25)) + ($activeDays7d <= 4 ? 8 : 0))
            );
        }

        $recentSelfCheckIn = CounselorWellnessLog::query()
            ->where('counselor_id', $counselor->id)
            ->where('check_in_version', self::CHECK_IN_VERSION)
            ->latest()
            ->first();

        $source = 'live-computed';
        if (
            $recentSelfCheckIn &&
            $recentSelfCheckIn->created_at &&
            $recentSelfCheckIn->created_at->gte($now->copy()->subHours(48))
        ) {
            $mood = $this->blendWithSelfCheckIn($mood, $recentSelfCheckIn->mood_score);
            $stress = $this->blendWithSelfCheckIn($stress, $recentSelfCheckIn->stress_level);
            $burnout = $this->blendWithSelfCheckIn($burnout, $recentSelfCheckIn->burnout_index);
            $source = 'live-computed+self-check-in';
        }

        $scores = [
            'mood_score' => $mood,
            'stress_level' => $stress,
            'burnout_index' => $burnout,
        ];

        $metrics = [
            'sessions_7d' => $merged7d->count(),
            'sessions_30d' => $sessions30d->count() + $appointments30d->count(),
            'active_days_7d' => $activeDays7d,
            'session_minutes_7d' => $minutes7d,
            'upcoming_appointments_3d' => $upcoming3d,
            'upcoming_appointments_7d' => $upcoming7d,
            'scheduled_pending_approval' => $pendingApproval,
            'high_risk_ai_diagnostics_14d' => $highRiskDiagnostics14d,
            'ai_diagnostics_14d' => $aiDiagnostics14d->count(),
            'avg_distress_signal_14d' => $avgDiagnosticLoad,
            'workload_index' => $workloadIndex,
            'risk_exposure_index' => $riskExposure,
            'schedule_pressure_index' => $schedulePressure,
        ];

        return [
            'generated_at' => $now->toIso8601String(),
            'source' => $source,
            'scores' => $scores,
            'labels' => [
                'wellness' => $this->wellnessLabel($mood),
                'stress' => $this->pressureLabel($stress),
                'burnout' => $this->pressureLabel($burnout),
            ],
            'metrics' => $metrics,
            'recommendations' => $this->buildLiveRecommendations($scores, $metrics),
        ];
    }

    private function sumSessionMinutes($sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            if ($session->started_at !== null && $session->ended_at !== null) {
                $minutes = $session->started_at->diffInMinutes($session->ended_at, false);
                if ($minutes > 0) {
                    $total += $minutes;
                }

                // Do not treat invalid/zero-length intervals as default session durations.
                continue;
            }

            // Check for explicit duration in appointments
            if (isset($session->duration_minutes) && $session->duration_minutes > 0) {
                $total += (int) $session->duration_minutes;
                continue;
            }

            // Fallback duration when explicit timing is missing.
            $total += 50;
        }

        return $total;
    }

    private function blendWithSelfCheckIn(int $computedValue, ?int $selfCheckInValue): int
    {
        if (!is_numeric($selfCheckInValue)) {
            return $computedValue;
        }

        return $this->clampInt(round(($computedValue * 0.45) + (((int) $selfCheckInValue) * 0.55)));
    }

    private function clampInt(float|int $value, int $min = 0, int $max = 100): int
    {
        return (int) max($min, min($max, round($value)));
    }

    private function pressureLabel(int $score): string
    {
        if ($score >= 70) {
            return 'High';
        }

        if ($score >= 40) {
            return 'Moderate';
        }

        return 'Low';
    }

    private function wellnessLabel(int $score): string
    {
        if ($score >= 70) {
            return 'Good';
        }

        if ($score >= 50) {
            return 'Moderate';
        }

        return 'Needs Attention';
    }

    private function buildLiveRecommendations(array $scores, array $metrics): string
    {
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
            $tips[] = 'Stress is currently manageable. Keep your current pacing strategy.';
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
}








