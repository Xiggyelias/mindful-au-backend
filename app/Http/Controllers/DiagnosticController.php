<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Diagnostic;
use App\Models\DiagnosticQuestionnaire;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Support\SystemSettings;
use App\Services\DiagnosticScoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiagnosticController extends Controller
{
    private DiagnosticScoringService $scoringService;

    public function __construct(DiagnosticScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function getQuestionnaire(): JsonResponse
    {
        $questionnaire = DiagnosticQuestionnaire::where('status', 'active')->latest()->first();

        if (!$questionnaire) {
            return response()->json(['message' => 'No active questionnaire available'], 404);
        }

        return response()->json($questionnaire);
    }

    public function analyze(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can submit diagnostics'], 403);
        }

        $validated = $request->validate([
            'responses' => 'required|array|min:1|max:200',
            'questionnaire_id' => 'required|integer|exists:diagnostic_questionnaires,id',
            'is_anonymous' => 'boolean',
        ]);

        $questionnaire = DiagnosticQuestionnaire::findOrFail($validated['questionnaire_id']);
        $user = $request->user();

        // Calculate scores
        $scoreData = $this->scoringService->calculateScore(
            $validated['responses'],
            $questionnaire->questions
        );

        // Generate recommendations
        $recommendations = $this->scoringService->generateRecommendations(
            $scoreData['risk_level'],
            $scoreData['category_scores']
        );

        // Create diagnostic record
        $diagnostic = new Diagnostic([
            'student_id' => $user->id,
            'responses' => $validated['responses'],
            'total_score' => $scoreData['total_score'],
            'risk_level' => $scoreData['risk_level'],
            'category_scores' => $scoreData['category_scores'],
            'ai_recommendations' => $recommendations,
            'is_anonymous' => $validated['is_anonymous'] ?? false,
        ]);

        if ($diagnostic->is_anonymous) {
            $diagnostic->anonymous_id = 'ANON-' . Str::random(12);
        }

        $diagnostic->save();

        // Log for counselors if high risk
        if (in_array($scoreData['risk_level'], ['high', 'critical'])) {
            $this->notifyCounselors($user, $diagnostic);
        }

        return response()->json([
            'message' => 'Diagnostic analysis completed',
            'diagnostic' => [
                'id' => $diagnostic->id,
                'total_score' => $diagnostic->total_score,
                'risk_level' => $diagnostic->risk_level,
                'category_scores' => $diagnostic->category_scores,
                'ai_recommendations' => $diagnostic->ai_recommendations,
                'created_at' => $diagnostic->created_at,
            ],
        ], 201);
    }

    public function getHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('student')) {
            return response()->json(['message' => 'Only students can view this history'], 403);
        }

        $diagnostics = Diagnostic::where('student_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($diagnostics);
    }

    public function getLatest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('student')) {
            return response()->json(['message' => 'Only students can view this data'], 403);
        }

        $diagnostic = Diagnostic::where('student_id', $user->id)
            ->latest()
            ->first();

        if (!$diagnostic) {
            return response()->json(['message' => 'No diagnostic found'], 404);
        }

        return response()->json($diagnostic);
    }

    public function getTrends(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('student')) {
            return response()->json(['message' => 'Only students can view trends'], 403);
        }

        $days = (int) $request->query('days', 30);
        $days = max(1, min(365, $days));

        $diagnostics = Diagnostic::where('student_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'asc')
            ->get();

        $trends = $diagnostics->map(function ($diagnostic) {
            return [
                'date' => $diagnostic->created_at->format('Y-m-d'),
                'score' => $diagnostic->total_score,
                'risk_level' => $diagnostic->risk_level,
                'categories' => $diagnostic->category_scores,
            ];
        });

        return response()->json([
            'days' => $days,
            'trends' => $trends,
            'latest' => $diagnostics->last(),
        ]);
    }

    public function getCounselorDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isAdmin = $user->hasRole('admin');
        $studentIds = $this->resolveObservedStudentIds($user->id, $isAdmin);

        if ($studentIds->isEmpty()) {
            return response()->json([
                'high_risk' => [],
                'high_risk_students' => [],
                'recent' => [],
                'risk_distribution' => [
                    ['risk_level' => 'low', 'count' => 0],
                    ['risk_level' => 'medium', 'count' => 0],
                    ['risk_level' => 'high', 'count' => 0],
                    ['risk_level' => 'critical', 'count' => 0],
                ],
                'student_observations' => [],
                'summary' => [
                    'students_observed' => 0,
                    'high_or_critical' => 0,
                    'worsening_trend' => 0,
                ],
            ]);
        }

        $diagnosticScope = Diagnostic::query()
            ->whereIn('student_id', $studentIds->all());

        $highRiskDiagnostics = (clone $diagnosticScope)
            ->whereIn('risk_level', ['high', 'critical'])
            ->with('student.profile')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentDiagnostics = (clone $diagnosticScope)
            ->with('student.profile')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $observations = $this->buildStudentObservations($studentIds, $user->id, $isAdmin);

        $riskDistributionMap = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ];
        foreach ($observations as $observation) {
            $level = (string) ($observation['risk_level'] ?? 'low');
            if (!array_key_exists($level, $riskDistributionMap)) {
                continue;
            }
            $riskDistributionMap[$level]++;
        }

        $riskDistribution = collect($riskDistributionMap)
            ->map(fn (int $count, string $riskLevel) => [
                'risk_level' => $riskLevel,
                'count' => $count,
            ])
            ->values();

        $highRiskObservations = $observations
            ->filter(fn (array $item) => in_array($item['risk_level'], ['high', 'critical'], true))
            ->values();

        $highRiskStudents = $highRiskObservations
            ->take(10)
            ->values();

        $worseningTrendCount = $observations
            ->filter(fn (array $item) => ($item['trend']['label'] ?? null) === 'worsening')
            ->count();

        return response()->json([
            'high_risk' => $highRiskDiagnostics,
            'high_risk_students' => $highRiskStudents,
            'recent' => $recentDiagnostics,
            'risk_distribution' => $riskDistribution,
            'student_observations' => $observations->values(),
            'summary' => [
                'students_observed' => $observations->count(),
                'high_or_critical' => $highRiskObservations->count(),
                'worsening_trend' => $worseningTrendCount,
            ],
        ]);
    }

    private function notifyCounselors(mixed $user, Diagnostic $diagnostic): void
    {
        if (!SystemSettings::getBool('ai_risk_alerts', true)) {
            return;
        }

        $studentName = $user->profile?->full_name
            ?: ($user->email ? Str::before($user->email, '@') : "Student #{$user->id}");

        $riskLevel = (string) $diagnostic->risk_level;
        $title = $riskLevel === 'critical' ? 'Critical AI Risk Alert' : 'High AI Risk Alert';
        $message = sprintf(
            'Diagnostic submission flagged %s risk for %s.',
            strtoupper($riskLevel),
            $studentName
        );

        $recipients = User::query()
            ->whereHas('roles', function (Builder $query) {
                $query->whereIn('role', ['admin', 'counselor'])->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($recipients as $recipientId) {
            Notification::query()->create([
                'user_id' => (int) $recipientId,
                'title' => $title,
                'message' => $message,
                'type' => 'warning',
            ]);
        }
    }

    private function resolveObservedStudentIds(int $userId, bool $isAdmin): Collection
    {
        if ($isAdmin) {
            return User::query()
                ->whereHas('roles', function (Builder $query) {
                    $query->where('role', 'student')->where('approved', true);
                })
                ->pluck('id')
                ->unique()
                ->values();
        }

        $sessionStudentIds = CounselingSession::query()
            ->where('counselor_id', $userId)
            ->pluck('student_id');

        $appointmentStudentIds = Appointment::query()
            ->where('counselor_id', $userId)
            ->pluck('student_id');

        return $sessionStudentIds
            ->merge($appointmentStudentIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function buildStudentObservations(Collection $studentIds, int $userId, bool $isAdmin): Collection
    {
        $students = User::query()
            ->with('profile')
            ->whereIn('id', $studentIds->all())
            ->get()
            ->keyBy('id');

        $diagnosticsByStudent = Diagnostic::query()
            ->whereIn('student_id', $studentIds->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id');

        $aiDiagnosticsByStudent = AiDiagnostic::query()
            ->whereIn('student_id', $studentIds->all())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id');

        $sessionsScope = CounselingSession::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('created_at', '>=', now()->subDays(30));
        if (!$isAdmin) {
            $sessionsScope->where('counselor_id', $userId);
        }
        $sessions = $sessionsScope->get();
        $sessionsByStudent = $sessions->groupBy('student_id');
        $sessionIds = $sessions->pluck('id')->unique()->values();

        $appointmentsScope = Appointment::query()
            ->whereIn('student_id', $studentIds->all())
            ->where('scheduled_at', '>=', now()->subDays(30));
        if (!$isAdmin) {
            $appointmentsScope->where('counselor_id', $userId);
        }
        $appointmentsByStudent = $appointmentsScope->get()->groupBy('student_id');

        $messagesByStudent = collect();
        if ($sessionIds->isNotEmpty()) {
            $messagesByStudent = Message::query()
                ->whereIn('session_id', $sessionIds->all())
                ->whereIn('sender_id', $studentIds->all())
                ->where('message_type', 'text')
                ->where('is_encrypted', false)
                ->where('created_at', '>=', now()->subDays(30))
                ->get()
                ->groupBy('sender_id');
        }

        $riskOrder = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ];

        return $studentIds
            ->map(function (int $studentId) use (
                $students,
                $diagnosticsByStudent,
                $aiDiagnosticsByStudent,
                $sessionsByStudent,
                $appointmentsByStudent,
                $messagesByStudent
            ) {
                $student = $students->get($studentId);
                if (!$student) {
                    return null;
                }

                $studentDiagnostics = $diagnosticsByStudent->get($studentId, collect())->values();
                $studentAiDiagnostics = $aiDiagnosticsByStudent->get($studentId, collect())->values();
                $studentSessions = $sessionsByStudent->get($studentId, collect());
                $studentAppointments = $appointmentsByStudent->get($studentId, collect());
                $studentMessages = $messagesByStudent->get($studentId, collect());

                $latestDiagnostic = $studentDiagnostics->first();
                $previousDiagnostic = $studentDiagnostics->slice(1, 1)->first();
                $latestAiDiagnostic = $studentAiDiagnostics->first();

                $diagnosticScore = $latestDiagnostic?->total_score;
                $aiScore = $this->estimateAiRiskScore($latestAiDiagnostic);
                $trend = $this->computeTrend($latestDiagnostic, $previousDiagnostic);
                $messageSignals = $this->computeMessageSignals($studentMessages);

                $appointmentsTotal = $studentAppointments->count();
                $appointmentsCancelled = $studentAppointments
                    ->where('status', 'cancelled')
                    ->count();
                $cancelRate = $appointmentsTotal > 0
                    ? $appointmentsCancelled / $appointmentsTotal
                    : 0.0;

                $composite = $this->computeCompositeRiskScore(
                    $diagnosticScore,
                    $aiScore,
                    $trend['delta'],
                    $messageSignals['signal_score'],
                    $cancelRate
                );

                $riskLevel = $this->riskLevelFromScore($composite);
                $confidence = $this->computeConfidenceScore(
                    $latestDiagnostic !== null,
                    $latestAiDiagnostic !== null,
                    $studentSessions->count(),
                    $studentMessages->count()
                );
                $reasons = $this->buildObservationReasons(
                    $latestDiagnostic,
                    $latestAiDiagnostic,
                    $trend,
                    $messageSignals,
                    $cancelRate,
                    $appointmentsTotal
                );

                return [
                    'student_id' => (int) $studentId,
                    'student' => [
                        'id' => (int) $studentId,
                        'name' => $student->profile?->full_name
                            ?: ($student->email ? Str::before($student->email, '@') : "Student #{$studentId}"),
                        'email' => $student->email,
                    ],
                    'risk_level' => $riskLevel,
                    'risk_score' => $composite,
                    'confidence' => $confidence,
                    'trend' => $trend,
                    'signals' => [
                        ...$messageSignals,
                        'cancel_rate' => round($cancelRate * 100),
                    ],
                    'activity' => [
                        'sessions_30d' => $studentSessions->count(),
                        'appointments_30d' => $appointmentsTotal,
                        'cancelled_appointments_30d' => $appointmentsCancelled,
                    ],
                    'latest_diagnostic' => $latestDiagnostic ? [
                        'id' => (int) $latestDiagnostic->id,
                        'risk_level' => $latestDiagnostic->risk_level,
                        'total_score' => (int) $latestDiagnostic->total_score,
                        'created_at' => $latestDiagnostic->created_at,
                    ] : null,
                    'latest_ai_diagnostic' => $latestAiDiagnostic ? [
                        'id' => (int) $latestAiDiagnostic->id,
                        'risk_level' => $latestAiDiagnostic->risk_level,
                        'stress_level' => $latestAiDiagnostic->stress_level,
                        'anxiety_level' => $latestAiDiagnostic->anxiety_level,
                        'depression_level' => $latestAiDiagnostic->depression_level,
                        'mood' => $latestAiDiagnostic->mood,
                        'created_at' => $latestAiDiagnostic->created_at,
                    ] : null,
                    'reasons' => $reasons,
                    'recommended_action' => $this->buildRecommendedAction($riskLevel),
                    'updated_at' => now(),
                ];
            })
            ->filter()
            ->sort(function (array $a, array $b) use ($riskOrder) {
                $aOrder = $riskOrder[$a['risk_level']] ?? 0;
                $bOrder = $riskOrder[$b['risk_level']] ?? 0;
                if ($aOrder !== $bOrder) {
                    return $bOrder <=> $aOrder;
                }
                return $b['risk_score'] <=> $a['risk_score'];
            })
            ->values();
    }

    private function estimateAiRiskScore(mixed $latestAiDiagnostic): ?int
    {
        if (!$latestAiDiagnostic) {
            return null;
        }

        $levels = array_filter([
            $latestAiDiagnostic->stress_level,
            $latestAiDiagnostic->anxiety_level,
            $latestAiDiagnostic->depression_level,
        ], fn ($value) => is_numeric($value));

        $numericFromLevels = !empty($levels)
            ? (int) round(array_sum($levels) / count($levels))
            : null;

        $riskMap = [
            'low' => 25,
            'medium' => 50,
            'high' => 75,
            'critical' => 92,
        ];
        $mappedRisk = $riskMap[$latestAiDiagnostic->risk_level] ?? null;

        if ($numericFromLevels !== null && $mappedRisk !== null) {
            return (int) round(($numericFromLevels * 0.7) + ($mappedRisk * 0.3));
        }

        if ($numericFromLevels !== null) {
            return $numericFromLevels;
        }

        return $mappedRisk;
    }

    private function computeTrend(mixed $latestDiagnostic, mixed $previousDiagnostic): array
    {
        if (!$latestDiagnostic || !$previousDiagnostic) {
            return [
                'label' => 'insufficient_data',
                'delta' => 0,
            ];
        }

        $latestScore = (int) $latestDiagnostic->total_score;
        $previousScore = (int) $previousDiagnostic->total_score;
        $delta = $latestScore - $previousScore;

        if ($delta >= 10) {
            $label = 'worsening';
        } elseif ($delta <= -10) {
            $label = 'improving';
        } else {
            $label = 'stable';
        }

        return [
            'label' => $label,
            'delta' => $delta,
        ];
    }

    private function computeMessageSignals(Collection $messages): array
    {
        $criticalTerms = [
            'suicide',
            'kill myself',
            'end my life',
            'self harm',
            'hurt myself',
        ];
        $highTerms = [
            'hopeless',
            'worthless',
            'panic attack',
            "can't cope",
            'overwhelmed',
        ];
        $mediumTerms = [
            'stressed',
            'anxious',
            'depressed',
            'lonely',
            'insomnia',
            'tired',
        ];

        $criticalHits = 0;
        $highHits = 0;
        $mediumHits = 0;

        foreach ($messages as $message) {
            $content = Str::lower((string) $message->content);

            foreach ($criticalTerms as $term) {
                if (Str::contains($content, $term)) {
                    $criticalHits++;
                }
            }
            foreach ($highTerms as $term) {
                if (Str::contains($content, $term)) {
                    $highHits++;
                }
            }
            foreach ($mediumTerms as $term) {
                if (Str::contains($content, $term)) {
                    $mediumHits++;
                }
            }
        }

        $signalScore = min(100, ($criticalHits * 40) + ($highHits * 20) + ($mediumHits * 8));

        return [
            'message_count' => $messages->count(),
            'critical_hits' => $criticalHits,
            'high_hits' => $highHits,
            'medium_hits' => $mediumHits,
            'signal_score' => $signalScore,
        ];
    }

    private function computeCompositeRiskScore(
        ?int $diagnosticScore,
        ?int $aiScore,
        int $trendDelta,
        int $signalScore,
        float $cancelRate
    ): int {
        $score = 0.0;
        $weight = 0.0;

        if ($diagnosticScore !== null) {
            $score += $diagnosticScore * 0.55;
            $weight += 0.55;
        }

        if ($aiScore !== null) {
            $score += $aiScore * 0.45;
            $weight += 0.45;
        }

        $baseScore = $weight > 0 ? $score / $weight : 25.0;

        $trendBonus = 0.0;
        if ($trendDelta >= 10) {
            $trendBonus = min(12.0, $trendDelta * 0.5);
        } elseif ($trendDelta <= -10) {
            $trendBonus = max(-10.0, $trendDelta * 0.35);
        }

        $signalBonus = min(15.0, $signalScore * 0.2);
        $attendanceBonus = $cancelRate >= 0.5 ? 8.0 : 0.0;

        $finalScore = $baseScore + $trendBonus + $signalBonus + $attendanceBonus;

        return (int) max(0, min(100, round($finalScore)));
    }

    private function riskLevelFromScore(int $score): string
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

    private function computeConfidenceScore(
        bool $hasDiagnostic,
        bool $hasAiDiagnostic,
        int $sessionCount,
        int $messageCount
    ): int {
        $confidence = 20;
        if ($hasDiagnostic) {
            $confidence += 28;
        }
        if ($hasAiDiagnostic) {
            $confidence += 25;
        }
        $confidence += min(15, $sessionCount * 3);
        $confidence += min(12, (int) floor($messageCount / 3));

        return max(0, min(100, $confidence));
    }

    private function buildObservationReasons(
        mixed $latestDiagnostic,
        mixed $latestAiDiagnostic,
        array $trend,
        array $messageSignals,
        float $cancelRate,
        int $appointmentsTotal
    ): array {
        $reasons = [];

        if ($latestDiagnostic && (int) $latestDiagnostic->total_score >= 60) {
            $reasons[] = sprintf(
                'Self-assessment risk is elevated (%d%%, %s).',
                (int) $latestDiagnostic->total_score,
                (string) $latestDiagnostic->risk_level
            );
        }

        if ($latestAiDiagnostic && in_array($latestAiDiagnostic->risk_level, ['high', 'critical'], true)) {
            $reasons[] = sprintf(
                'Session analysis marked %s risk with mood "%s".',
                (string) $latestAiDiagnostic->risk_level,
                (string) ($latestAiDiagnostic->mood ?? 'unknown')
            );
        }

        if (($trend['label'] ?? null) === 'worsening') {
            $reasons[] = sprintf(
                'Risk trend worsened by %+d points since the previous assessment.',
                (int) ($trend['delta'] ?? 0)
            );
        }

        if (($messageSignals['critical_hits'] ?? 0) > 0) {
            $reasons[] = 'Critical-risk phrases appeared in recent unencrypted student messages.';
        } elseif (($messageSignals['high_hits'] ?? 0) > 1) {
            $reasons[] = 'Multiple high-distress phrases appeared in recent messages.';
        }

        if ($appointmentsTotal >= 2 && $cancelRate >= 0.5) {
            $reasons[] = 'Frequent appointment cancellations may indicate disengagement.';
        }

        if (empty($reasons)) {
            $reasons[] = 'No acute risk signals detected from current data.';
        }

        return $reasons;
    }

    private function buildRecommendedAction(string $riskLevel): string
    {
        return match ($riskLevel) {
            'critical' => 'Immediate outreach today. Follow crisis protocol and coordinate urgent support.',
            'high' => 'Schedule a direct follow-up within 24-48 hours and review a safety/support plan.',
            'medium' => 'Check in this week, reinforce coping plans, and monitor trend changes closely.',
            default => 'Continue routine monitoring and encourage healthy routines.',
        };
    }
}
