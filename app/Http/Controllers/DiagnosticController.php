<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Diagnostic;
use App\Models\DiagnosticQuestionnaire;
use App\Models\Notification;
use App\Models\User;
use App\Services\DiagnosticScoringService;
use App\Services\MentalHealthMlService;
use App\Support\SystemSettings;
use Database\Seeders\DiagnosticQuestionnaireSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class DiagnosticController extends Controller
{
    private DiagnosticScoringService $scoringService;

    private MentalHealthMlService $mlService;

    public function __construct(
        DiagnosticScoringService $scoringService,
        MentalHealthMlService $mlService
    ) {
        $this->scoringService = $scoringService;
        $this->mlService = $mlService;
    }

    /**
     * Scoring expects ['questions' => [...]]; legacy rows may store only a list.
     */
    private function normalizeQuestionnaireForScoring(array $questions): array
    {
        if (isset($questions['questions']) && is_array($questions['questions'])) {
            return $questions;
        }

        return ['questions' => $questions];
    }

    public function getQuestionnaire(): JsonResponse
    {
        if (! DiagnosticQuestionnaire::query()->exists()) {
            try {
                Artisan::call('db:seed', [
                    '--class' => DiagnosticQuestionnaireSeeder::class,
                    '--force' => true,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $questionnaire = DiagnosticQuestionnaire::where('status', 'active')->latest()->first()
            ?? DiagnosticQuestionnaire::query()->latest()->first();

        if (! $questionnaire) {
            return response()->json(['message' => 'No active questionnaire available'], 404);
        }

        return response()->json($questionnaire);
    }

    public function analyze(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can submit diagnostics'], 403);
        }

        $validated = $request->validate([
            'responses' => 'required|array|min:1|max:200',
            'questionnaire_id' => 'required|integer|exists:diagnostic_questionnaires,id',
            'is_anonymous' => 'boolean',
        ]);

        $questionnaire = DiagnosticQuestionnaire::findOrFail($validated['questionnaire_id']);
        $user = $request->user();

        $questionsForScoring = $this->normalizeQuestionnaireForScoring(
            is_array($questionnaire->questions) ? $questionnaire->questions : []
        );

        // Calculate scores
        $scoreData = $this->scoringService->calculateScore(
            $validated['responses'],
            $questionsForScoring
        );

        // Generate recommendations
        $recommendations = $this->scoringService->generateRecommendations(
            $scoreData['risk_level'],
            $scoreData['category_scores']
        );

        if (! empty($scoreData['counselor_summary'])) {
            $recommendations['counselor_summary'] = $scoreData['counselor_summary'];
        }
        if (! empty($scoreData['focus_areas'])) {
            $recommendations['focus_areas'] = $scoreData['focus_areas'];
        }
        if (! empty($scoreData['risk_flags'])) {
            $recommendations['risk_flags'] = $scoreData['risk_flags'];
        }
        if (! empty($scoreData['scoring_model'])) {
            $recommendations['scoring_model'] = $scoreData['scoring_model'];
        }

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
            $diagnostic->anonymous_id = 'ANON-'.Str::random(12);
        }

        $diagnostic->save();

        $user->update(['needs_assessment' => false]);

        // Log for counselors if elevated risk / safety concerns
        if (! empty($scoreData['notify_counselors'])) {
            $this->notifyCounselors($user, $diagnostic, $scoreData['risk_flags'] ?? []);
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
        if (! $user->hasRole('student')) {
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
        if (! $user->hasRole('student')) {
            return response()->json(['message' => 'Only students can view this data'], 403);
        }

        $diagnostic = Diagnostic::where('student_id', $user->id)
            ->latest()
            ->first();

        if (! $diagnostic) {
            return response()->json(['message' => 'No diagnostic found'], 404);
        }

        return response()->json($diagnostic);
    }

    public function getTrends(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('student')) {
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
        if (! $user->hasRole('counselor') && ! $user->hasRole('admin')) {
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

        $observations = $this->buildStudentObservations($studentIds);

        $riskDistributionMap = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ];
        foreach ($observations as $observation) {
            $level = (string) ($observation['risk_level'] ?? 'low');
            if (! array_key_exists($level, $riskDistributionMap)) {
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

    /** @param  list<string>  $riskFlags */
    private function notifyCounselors(User $user, Diagnostic $diagnostic, array $riskFlags = []): void
    {
        if (! SystemSettings::getBool('ai_risk_alerts', true)) {
            return;
        }

        $studentName = $user->profile?->full_name
            ?: ($user->email ? Str::before($user->email, '@') : "Student #{$user->id}");

        $riskLevel = (string) $diagnostic->risk_level;
        $flagSummary = '';
        if ($riskFlags !== []) {
            $flagSummary = ' Flags: '.implode(', ', $riskFlags).'.';
        }
        $title = $riskLevel === 'critical' ? 'Critical AI Risk Alert' : 'High AI Risk Alert';
        $message = sprintf(
            'Diagnostic submission flagged %s risk for %s.%s',
            strtoupper($riskLevel),
            $studentName,
            $flagSummary
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
            // Unbounded "all students" caused timeouts; prioritize students with recent signals.
            $fromDiagnostics = Diagnostic::query()
                ->where('created_at', '>=', now()->subDays(365))
                ->orderByDesc('created_at')
                ->limit(600)
                ->pluck('student_id');

            $fromSessions = CounselingSession::query()
                ->where('updated_at', '>=', now()->subDays(180))
                ->orderByDesc('updated_at')
                ->limit(400)
                ->pluck('student_id');

            return $fromDiagnostics
                ->merge($fromSessions)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->take(350)
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

    private function buildStudentObservations(Collection $studentIds): Collection
    {
        return $studentIds
            ->map(function (int $studentId) {
                try {
                    $insights = $this->mlService->buildStudentMlInsights($studentId);
                } catch (\Throwable $e) {
                    report($e);

                    return null;
                }
                $snapshot = $insights['feature_snapshot'] ?? [];

                $student = User::with('profile')->find($studentId);
                if (! $student) {
                    return null;
                }

                $riskLevel = strtolower((string) ($insights['risk_forecast']['level'] ?? 'low'));
                if (! in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
                    $riskLevel = 'low';
                }
                $riskScore = (int) ($insights['risk_forecast']['score'] ?? 0);

                $riskIndicators = $insights['risk_indicators'] ?? [];
                $protectiveFactors = $insights['protective_factors'] ?? [];
                if (! is_array($riskIndicators)) {
                    $riskIndicators = [];
                }
                if (! is_array($protectiveFactors)) {
                    $protectiveFactors = [];
                }

                $trend = $insights['trend'] ?? ['label' => 'stable', 'delta' => 0];
                if (! is_array($trend)) {
                    $trend = ['label' => 'stable', 'delta' => 0];
                }
                $trendLabelRaw = (string) ($trend['label'] ?? 'stable');
                $allowedTrends = ['improving', 'stable', 'worsening', 'insufficient_data'];
                if (! in_array($trendLabelRaw, $allowedTrends, true)) {
                    $trend['label'] = 'insufficient_data';
                }

                $recommendedActions = $insights['recommended_actions'] ?? [];
                $primaryAction = is_array($recommendedActions) && $recommendedActions !== []
                    ? (string) $recommendedActions[0]
                    : 'Continue routine monitoring.';

                return [
                    'student_id' => (int) $studentId,
                    'student' => [
                        'id' => (int) $studentId,
                        'name' => $student->profile?->full_name
                            ?: ($student->email ? Str::before($student->email, '@') : "Student #{$studentId}"),
                        'email' => (string) ($student->email ?? ''),
                    ],
                    'risk_level' => $riskLevel,
                    'risk_score' => $riskScore,
                    'confidence' => (int) ($insights['risk_forecast']['confidence'] ?? 75),
                    'trend' => [
                        'label' => (string) ($trend['label'] ?? 'stable'),
                        'delta' => (int) ($trend['delta'] ?? 0),
                    ],
                    'signals' => [
                        'distress_hits' => (int) ($snapshot['distress_messages_30d'] ?? 0),
                        'crisis_hits' => (int) ($snapshot['crisis_messages_30d'] ?? 0),
                        'cancel_rate' => round((float) ($snapshot['cancel_rate_60d'] ?? 0) * 100),
                    ],
                    'activity' => [
                        'sessions_30d' => (int) ($snapshot['completed_sessions_60d'] ?? 0),
                        'appointments_30d' => (int) ($snapshot['upcoming_appointments'] ?? 0),
                    ],
                    'reasons' => array_values(array_unique(array_merge($riskIndicators, $protectiveFactors))),
                    'recommended_action' => $primaryAction,
                    'updated_at' => now(),
                ];
            })
            ->filter()
            ->values();
    }

    public function assignNewAssessment(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('counselor') && ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
        ]);

        $student = User::findOrFail($validated['student_id']);
        if (! $student->hasRole('student')) {
            return response()->json(['message' => 'Assessment can only be assigned to students'], 422);
        }

        $student->update(['needs_assessment' => true]);

        Notification::query()->create([
            'user_id' => $student->id,
            'title' => 'New Assessment Assigned',
            'message' => 'A counselor has assigned a new wellness assessment for you to complete.',
            'type' => 'info',
            'meta' => [
                'assessment_assigned' => true,
                'path' => '/student/diagnostic-assessment',
            ],
        ]);

        return response()->json([
            'message' => 'New assessment assigned successfully',
            'student_id' => $student->id,
        ]);
    }
}
