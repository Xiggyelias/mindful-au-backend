<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Diagnostic;
use App\Models\User;
use App\Services\MentalHealthMlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentWellnessController extends Controller
{
    public function __construct(
        private readonly MentalHealthMlService $mentalHealthMlService
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('student') && !$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $studentId = $user->hasRole('student')
            ? $user->id
            : (int) $request->query('student_id');

        if ($studentId <= 0) {
            return response()->json(['message' => 'student_id is required'], 422);
        }

        if ($user->hasRole('counselor')) {
            $assignedBySession = CounselingSession::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            $assignedByAppointment = Appointment::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            if (!$assignedBySession && !$assignedByAppointment) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $student = User::findOrFail($studentId);
        if (!$student->hasRole('student')) {
            return response()->json(['message' => 'Target user is not a student'], 422);
        }

        return response()->json($this->buildSummary($student));
    }

    private function buildSummary(User $student): array
    {
        $now = now();
        $mlInsights = $this->mentalHealthMlService->buildStudentMlInsights($student);

        $diagnostics = Diagnostic::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'total_score', 'risk_level', 'ai_recommendations', 'created_at']);

        $latestDiagnostic = $diagnostics->first();
        $previousDiagnostic = $diagnostics->slice(1, 1)->first();

        $aiDiagnostics30d = AiDiagnostic::query()
            ->where('student_id', $student->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->latest()
            ->limit(20)
            ->get([
                'id',
                'stress_level',
                'anxiety_level',
                'depression_level',
                'mood',
                'risk_level',
                'insights',
                'recommendations',
                'created_at',
            ]);

        $latestAiDiagnostic = $aiDiagnostics30d->first();

        $sessions30d = CounselingSession::query()
            ->where('student_id', $student->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->get(['id', 'status', 'started_at', 'ended_at', 'created_at']);

        $appointments30d = Appointment::query()
            ->where('student_id', $student->id)
            ->where('scheduled_at', '>=', $now->copy()->subDays(30))
            ->get(['id', 'status', 'scheduled_at']);

        $upcomingAppointments = Appointment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_at', '>=', $now)
            ->count();

        $cancelledAppointments30d = $appointments30d->where('status', 'cancelled')->count();
        $completedSessions30d = $sessions30d->where('status', 'completed')->count();
        $sessionMinutes30d = $this->sumSessionMinutes($sessions30d);
        $cancelRate = $appointments30d->count() > 0
            ? $cancelledAppointments30d / $appointments30d->count()
            : 0.0;

        $diagnosticRisk = $latestDiagnostic ? (int) $latestDiagnostic->total_score : null;
        $aiRisk = $this->resolveAiRiskScore($latestAiDiagnostic);

        $trendDelta = 0;
        if ($latestDiagnostic && $previousDiagnostic) {
            $trendDelta = (int) $latestDiagnostic->total_score - (int) $previousDiagnostic->total_score;
        }

        $baseRisk = null;
        if (is_int($diagnosticRisk) && is_int($aiRisk)) {
            $baseRisk = (int) round(($diagnosticRisk * 0.6) + ($aiRisk * 0.4));
        } elseif (is_int($diagnosticRisk)) {
            $baseRisk = $diagnosticRisk;
        } elseif (is_int($aiRisk)) {
            $baseRisk = $aiRisk;
        } else {
            $activityLoad = min(100, ($sessions30d->count() * 6) + ($upcomingAppointments * 4) + ($cancelRate * 40));
            $baseRisk = $activityLoad > 0 ? (int) round($activityLoad * 0.35) : null;
        }

        $scores = [
            'wellness_score' => null,
            'stress_level' => null,
            'burnout_risk' => null,
            'risk_score' => null,
        ];

        if (is_int($baseRisk)) {
            $trendAdjustment = 0;
            if ($trendDelta >= 10) {
                $trendAdjustment = min(12, (int) round($trendDelta * 0.5));
            } elseif ($trendDelta <= -10) {
                $trendAdjustment = max(-10, (int) round($trendDelta * 0.35));
            }

            $cancelAdjustment = $cancelRate >= 0.5
                ? 10
                : ($cancelRate >= 0.25 ? 5 : 0);
            $engagementAdjustment = min(10, $completedSessions30d * 2);

            $riskScore = $this->clampInt($baseRisk + $trendAdjustment + $cancelAdjustment - $engagementAdjustment);
            $stressLevel = $this->clampInt((int) round(($riskScore * 0.5) + (($latestAiDiagnostic->stress_level ?? $riskScore) * 0.5)));
            $burnoutSeed = is_numeric($latestAiDiagnostic->depression_level ?? null)
                ? (int) $latestAiDiagnostic->depression_level
                : (is_numeric($latestAiDiagnostic->anxiety_level ?? null) ? (int) $latestAiDiagnostic->anxiety_level : $stressLevel);
            $burnoutRisk = $this->clampInt(
                (int) round(($stressLevel * 0.55) + ($burnoutSeed * 0.25) + ($cancelRate * 100 * 0.20))
            );
            $wellnessScore = $this->clampInt(
                100 - (int) round(($riskScore * 0.4) + ($stressLevel * 0.35) + ($burnoutRisk * 0.25))
            );

            $scores = [
                'wellness_score' => $wellnessScore,
                'stress_level' => $stressLevel,
                'burnout_risk' => $burnoutRisk,
                'risk_score' => $riskScore,
            ];
        }

        $parsedDiagnosticRecommendation = $this->parseDiagnosticRecommendations($latestDiagnostic?->ai_recommendations);
        $aiRecommendation = $this->cleanText($latestAiDiagnostic?->recommendations);
        $aiInsight = $this->cleanText($latestAiDiagnostic?->insights);

        $metrics = [
            'sessions_30d' => $sessions30d->count(),
            'completed_sessions_30d' => $completedSessions30d,
            'session_minutes_30d' => $sessionMinutes30d,
            'appointments_30d' => $appointments30d->count(),
            'cancelled_appointments_30d' => $cancelledAppointments30d,
            'upcoming_appointments' => $upcomingAppointments,
            'diagnostics_30d' => Diagnostic::query()
                ->where('student_id', $student->id)
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->count(),
            'ai_diagnostics_30d' => $aiDiagnostics30d->count(),
            'trend_delta' => $trendDelta,
        ];

        $recommendations = $this->buildLiveRecommendations(
            $scores,
            $metrics,
            $parsedDiagnosticRecommendation,
            $aiRecommendation,
            $mlInsights['recommended_actions'] ?? []
        );

        $insights = $this->buildLiveInsights(
            $scores,
            $metrics,
            $aiInsight,
            $mlInsights
        );

        $history = $diagnostics->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'created_at' => $item->created_at,
                'risk_level' => (string) $item->risk_level,
                'risk_score' => (int) $item->total_score,
                'wellness_score' => $this->clampInt(100 - (int) $item->total_score),
            ];
        })->values();

        return [
            'generated_at' => $now->toIso8601String(),
            'source' => 'live-computed',
            'scores' => $scores,
            'labels' => [
                'wellness' => is_int($scores['wellness_score']) ? $this->wellnessLabel($scores['wellness_score']) : 'No data',
                'stress' => is_int($scores['stress_level']) ? $this->pressureLabel($scores['stress_level']) : 'No data',
                'burnout' => is_int($scores['burnout_risk']) ? $this->pressureLabel($scores['burnout_risk']) : 'No data',
                'risk' => is_int($scores['risk_score']) ? $this->riskLabel($scores['risk_score']) : 'unknown',
            ],
            'mood' => $latestAiDiagnostic?->mood,
            'insights' => $insights,
            'recommendations' => $recommendations,
            'ml_insights' => $mlInsights,
            'metrics' => $metrics,
            'latest_diagnostic' => $latestDiagnostic,
            'latest_ai_diagnostic' => $latestAiDiagnostic,
            'history' => $history,
        ];
    }

    private function resolveAiRiskScore(?AiDiagnostic $diagnostic): ?int
    {
        if (!$diagnostic) {
            return null;
        }

        $levels = array_filter([
            $diagnostic->stress_level,
            $diagnostic->anxiety_level,
            $diagnostic->depression_level,
        ], fn ($value) => is_numeric($value));

        $levelScore = !empty($levels)
            ? (int) round(array_sum($levels) / count($levels))
            : null;

        $map = [
            'low' => 25,
            'medium' => 50,
            'high' => 75,
            'critical' => 92,
        ];
        $mappedRisk = $map[(string) ($diagnostic->risk_level ?? '')] ?? null;

        if (is_int($levelScore) && is_int($mappedRisk)) {
            return (int) round(($levelScore * 0.7) + ($mappedRisk * 0.3));
        }

        if (is_int($levelScore)) {
            return $levelScore;
        }

        return $mappedRisk;
    }

    private function sumSessionMinutes($sessions): int
    {
        $total = 0;
        foreach ($sessions as $session) {
            if ($session->started_at && $session->ended_at) {
                $minutes = $session->started_at->diffInMinutes($session->ended_at, false);
                if ($minutes > 0) {
                    $total += $minutes;
                    continue;
                }
            }

            // Fallback duration when explicit timing is missing.
            $total += 45;
        }

        return $total;
    }

    private function parseDiagnosticRecommendations(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        $parts = [];

        if (!empty($value['primary']) && is_string($value['primary'])) {
            $parts[] = $this->cleanText($value['primary']);
        }

        if (!empty($value['actions']) && is_array($value['actions'])) {
            $actions = array_slice(
                array_values(array_filter($value['actions'], fn ($item) => is_string($item) && trim($item) !== '')),
                0,
                2
            );
            if (!empty($actions)) {
                $parts[] = 'Next steps: ' . implode(' ', array_map(fn ($item) => $this->cleanText($item), $actions));
            }
        }

        if (!empty($value['category_alerts']) && is_array($value['category_alerts'])) {
            $alerts = array_slice(
                array_values(array_filter($value['category_alerts'], fn ($item) => is_string($item) && trim($item) !== '')),
                0,
                1
            );
            if (!empty($alerts)) {
                $parts[] = $this->cleanText($alerts[0]);
            }
        }

        return implode(' ', array_filter($parts));
    }

    private function buildLiveInsights(array $scores, array $metrics, string $aiInsight, array $mlInsights = []): string
    {
        if (!is_int($scores['wellness_score'])) {
            return 'No live wellness insight yet. Complete a diagnostic assessment or counseling session to generate one.';
        }

        $insightParts = [];
        $insightParts[] = sprintf(
            'Live snapshot: %d sessions and %d wellness diagnostics in the last 30 days.',
            (int) ($metrics['sessions_30d'] ?? 0),
            (int) ($metrics['diagnostics_30d'] ?? 0)
        );

        if (($metrics['trend_delta'] ?? 0) >= 10) {
            $insightParts[] = 'Risk trend is worsening compared to your previous check-in.';
        } elseif (($metrics['trend_delta'] ?? 0) <= -10) {
            $insightParts[] = 'Risk trend is improving compared to your previous check-in.';
        } else {
            $insightParts[] = 'Risk trend is relatively stable right now.';
        }

        if ($aiInsight !== '') {
            $insightParts[] = $aiInsight;
        }

        $focusArea = trim((string) ($mlInsights['focus_area'] ?? ''));
        if ($focusArea !== '') {
            $insightParts[] = sprintf('ML support focus: %s.', $focusArea);
        }

        $trendLabel = trim((string) ($mlInsights['trend']['label'] ?? ''));
        if ($trendLabel !== '') {
            $insightParts[] = sprintf('Forecast trend is %s.', $trendLabel);
        }

        $dominantTopics = array_slice(
            array_values(array_filter($mlInsights['dominant_topics'] ?? [], fn ($item) => is_string($item) && trim($item) !== '')),
            0,
            2
        );
        if (!empty($dominantTopics)) {
            $insightParts[] = 'Recent support themes: ' . implode(', ', $dominantTopics) . '.';
        }

        return implode(' ', $insightParts);
    }

    private function buildLiveRecommendations(
        array $scores,
        array $metrics,
        string $diagnosticRecommendation,
        string $aiRecommendation,
        array $mlActions = []
    ): string {
        $recommendations = [];

        if ($diagnosticRecommendation !== '') {
            $recommendations[] = $diagnosticRecommendation;
        }

        if ($aiRecommendation !== '') {
            $recommendations[] = $aiRecommendation;
        }

        if (is_int($scores['stress_level'])) {
            if ($scores['stress_level'] >= 70) {
                $recommendations[] = 'Stress is high based on recent activity. Schedule a counselor follow-up within 48 hours.';
            } elseif ($scores['stress_level'] >= 40) {
                $recommendations[] = 'Stress is moderate. Keep structured breaks and daily recovery routines this week.';
            }
        }

        if (is_int($scores['burnout_risk']) && $scores['burnout_risk'] >= 60) {
            $recommendations[] = 'Burnout risk is elevated. Reduce overload and prioritize sleep, hydration, and support check-ins.';
        }

        if (($metrics['upcoming_appointments'] ?? 0) === 0) {
            $recommendations[] = 'No upcoming sessions are scheduled. Book a follow-up session to maintain continuity.';
        }

        if (($metrics['cancelled_appointments_30d'] ?? 0) > 1) {
            $recommendations[] = 'Multiple recent cancellations were detected. Choose consistent session slots to improve progress.';
        }

        foreach ($mlActions as $action) {
            if (is_string($action) && trim($action) !== '') {
                $recommendations[] = $this->cleanText($action);
            }
        }

        $final = implode(' ', array_unique(array_filter($recommendations)));

        if ($final !== '') {
            return $final;
        }

        return 'Live data is stable right now. Maintain your current healthy routines and regular check-ins.';
    }

    private function cleanText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(
            preg_replace('/\s+/', ' ', str_replace(['```json', '```'], '', $value)) ?? ''
        );
    }

    private function clampInt(float|int $value, int $min = 0, int $max = 100): int
    {
        return (int) max($min, min($max, round($value)));
    }

    private function wellnessLabel(int $score): string
    {
        if ($score >= 70) {
            return 'Good';
        }
        if ($score >= 50) {
            return 'Balanced';
        }
        return 'Needs Attention';
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

    private function riskLabel(int $score): string
    {
        if ($score >= 81) {
            return 'critical';
        }
        if ($score >= 61) {
            return 'high';
        }
        if ($score >= 36) {
            return 'medium';
        }
        return 'low';
    }
}

