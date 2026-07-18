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
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('student') && ! $user->hasRole('counselor') && ! $user->hasRole('admin')) {
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

            if (! $assignedBySession && ! $assignedByAppointment) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $student = User::findOrFail($studentId);
        if (! $student->hasRole('student')) {
            return response()->json(['message' => 'Target user is not a student'], 422);
        }

        return response()->json($this->buildSummary($student));
    }

    private function buildSummary(User $student): array
    {
        $now = now();
        $mlInsights = $this->mentalHealthMlService->buildStudentMlInsights($student->id);
        $snapshot = $mlInsights['feature_snapshot'] ?? [];

        $diagnostics = Diagnostic::query()
            ->where('student_id', $student->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'total_score', 'risk_level', 'ai_recommendations', 'created_at']);

        $latestDiagnostic = $diagnostics->first();

        $aiDiagnostics30d = AiDiagnostic::query()
            ->where('student_id', $student->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->latest()
            ->limit(20)
            ->get();

        $latestAiDiagnostic = $aiDiagnostics30d->first();

        $sessions30d = CounselingSession::query()
            ->where('student_id', $student->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->get();

        $appointments30d = Appointment::query()
            ->where('student_id', $student->id)
            ->where('scheduled_at', '>=', $now->copy()->subDays(30))
            ->get();

        $upcomingAppointments = Appointment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_at', '>=', $now)
            ->count();

        $cancelledAppointments30d = $appointments30d->where('status', 'cancelled')->count();
        $completedSessions30d = $sessions30d->where('status', 'completed')->count();
        $sessionMinutes30d = (int) ($snapshot['session_minutes_60d'] ?? 0) / 2; // Rough estimate for 30d
        $cancelRate = (float) ($snapshot['cancel_rate_60d'] ?? 0.0);

        $riskScore = (int) ($mlInsights['risk_forecast']['score'] ?? 0);
        $stressLevel = $this->clampInt((int) round(($riskScore * 0.5) + (($latestAiDiagnostic->stress_level ?? $riskScore) * 0.5)));
        $burnoutSeed = is_numeric($latestAiDiagnostic->depression_level ?? null)
            ? (int) $latestAiDiagnostic->depression_level
            : (is_numeric($latestAiDiagnostic->anxiety_level ?? null) ? (int) $latestAiDiagnostic->anxiety_level : $stressLevel);

        $burnoutRisk = $this->clampInt(
            (int) round(($stressLevel * 0.55) + ($burnoutSeed * 0.25) + ($cancelRate * 100 * 0.20))
        );

        // Positive boost: positive mood check-ins and positive chat messages
        // directly lift the wellness score so it can go UP over time.
        $positiveMoods = (int) ($snapshot['positive_mood_logs_14d'] ?? 0);
        $positiveMessages = (int) ($snapshot['positive_messages_30d'] ?? 0);
        $positiveBoost = min(20,
            ($positiveMoods * 3) + ($positiveMessages * 2)
        );

        $wellnessScore = $this->clampInt(
            100 - (int) round(($riskScore * 0.4) + ($stressLevel * 0.35) + ($burnoutRisk * 0.25)) + $positiveBoost
        );

        $scores = [
            'wellness_score' => $wellnessScore,
            'stress_level' => $stressLevel,
            'burnout_risk' => $burnoutRisk,
            'risk_score' => $riskScore,
        ];

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
            'trend_delta' => (int) ($mlInsights['trend']['delta'] ?? 0),
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
                'wellness' => $this->wellnessLabel($scores['wellness_score']),
                'stress' => $this->pressureLabel($scores['stress_level']),
                'burnout' => $this->pressureLabel($scores['burnout_risk']),
                'risk' => $mlInsights['risk_forecast']['level'] ?? 'low',
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

    private function buildLiveInsights(array $scores, array $metrics, string $aiInsight, array $mlInsights = []): string
    {
        $insightParts = [];
        $insightParts[] = sprintf(
            'Live snapshot: %d sessions and %d wellness diagnostics in the last 30 days.',
            (int) ($metrics['sessions_30d'] ?? 0),
            (int) ($metrics['diagnostics_30d'] ?? 0)
        );

        $trendLabel = trim((string) ($mlInsights['trend']['label'] ?? 'stable'));
        $insightParts[] = sprintf('Risk trend is %s compared to your previous check-in.', $trendLabel);

        if ($aiInsight !== '') {
            $insightParts[] = $aiInsight;
        }

        $focusArea = trim((string) ($mlInsights['focus_area'] ?? ''));
        if ($focusArea !== '') {
            $insightParts[] = sprintf('ML support focus: %s.', $focusArea);
        }

        $dominantTopics = array_slice(
            array_values(array_filter($mlInsights['dominant_topics'] ?? [], fn ($item) => is_string($item) && trim($item) !== '')),
            0,
            2
        );
        if (! empty($dominantTopics)) {
            $insightParts[] = 'Recent support themes: '.implode(', ', $dominantTopics).'.';
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

        if ($scores['stress_level'] >= 70) {
            $recommendations[] = 'Stress is high based on recent activity. Schedule a counselor follow-up within 48 hours.';
        } elseif ($scores['stress_level'] >= 40) {
            $recommendations[] = 'Stress is moderate. Practice daily mindfulness and maintain regular check-ins.';
        }

        if (! empty($mlActions)) {
            $recommendations[] = $mlActions[0];
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Maintain healthy routines and continue periodic wellness check-ins.';
        }

        return implode(' ', array_unique($recommendations));
    }

    private function wellnessLabel(int $score): string
    {
        if ($score >= 80) {
            return 'Excellent';
        }
        if ($score >= 60) {
            return 'Good';
        }
        if ($score >= 40) {
            return 'Fair';
        }
        if ($score >= 20) {
            return 'Needs Attention';
        }

        return 'Critical';
    }

    private function pressureLabel(int $score): string
    {
        if ($score >= 80) {
            return 'Critical';
        }
        if ($score >= 60) {
            return 'High';
        }
        if ($score >= 40) {
            return 'Moderate';
        }
        if ($score >= 20) {
            return 'Low';
        }

        return 'Minimal';
    }

    private function parseDiagnosticRecommendations(mixed $value): string
    {
        if (! $value) {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        if (! is_array($value)) {
            return '';
        }

        $parts = [];
        if (! empty($value['primary']) && is_string($value['primary'])) {
            $parts[] = $this->cleanText($value['primary']);
        }

        if (! empty($value['actions']) && is_array($value['actions'])) {
            $actions = array_slice(
                array_values(array_filter($value['actions'], fn ($item) => is_string($item) && trim($item) !== '')),
                0,
                2
            );
            if (! empty($actions)) {
                $parts[] = 'Next steps: '.implode(' ', array_map(fn ($item) => $this->cleanText($item), $actions));
            }
        }

        return implode(' ', array_filter($parts));
    }

    private function cleanText(?string $text): string
    {
        if (! $text) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function clampInt(float|int $value): int
    {
        return (int) max(0, min(100, round($value)));
    }
}
