<?php

namespace App\Services;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Diagnostic;
use App\Models\StudentMoodLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MentalHealthMlService
{
    public const MODEL_VERSION = 'mindful-lightweight-ml-v1';

    private const DISTRESS_TERMS = [
        'stress',
        'stressed',
        'anxious',
        'anxiety',
        'panic',
        'overwhelmed',
        'hopeless',
        'alone',
        'lonely',
        'drained',
        'tired',
        'exhausted',
        'burnout',
        'can\'t cope',
        'cannot cope',
        'no point',
        'give up',
    ];

    private const CRISIS_TERMS = [
        'suicide',
        'kill myself',
        'end my life',
        'self harm',
        'hurt myself',
        'jump off',
        'wish i were dead',
        'better off without me',
    ];

    private const CHAT_TOPICS = [
        'anxiety' => ['anxiety', 'anxious', 'panic', 'overwhelmed', 'stress', 'stressed'],
        'study' => ['exam', 'assignment', 'deadline', 'study', 'focus', 'concentrate'],
        'sleep' => ['sleep', 'insomnia', 'tired', 'exhausted', 'rest'],
        'sadness' => ['sad', 'depressed', 'hopeless', 'alone', 'lonely', 'empty'],
        'relationships' => ['relationship', 'breakup', 'friend', 'friendship', 'partner'],
        'financial' => ['fees', 'tuition', 'money', 'rent', 'financial', 'debt', 'food'],
    ];

    public function buildStudentMlInsights(User|int $student): array
    {
        $studentId = $student instanceof User ? (int) $student->id : (int) $student;
        $snapshot = $this->buildStudentFeatureSnapshots([$studentId])[$studentId] ?? $this->emptyStudentSnapshot($studentId);

        return $this->buildStudentInsightPayload($snapshot);
    }

    public function buildPromptSafeStudentContext(User|int $student): array
    {
        $insights = $this->buildStudentMlInsights($student);
        $riskForecast = $insights['risk_forecast'];
        $topics = $insights['dominant_topics'];
        $protectiveFactors = $insights['protective_factors'];

        $promptParts = [
            sprintf(
                'Forecast risk is %s (%d/100) with a %s trend.',
                $riskForecast['level'],
                (int) $riskForecast['score'],
                $insights['trend']['label']
            ),
            sprintf('Primary support focus: %s.', $insights['focus_area']),
        ];

        if (!empty($topics)) {
            $promptParts[] = 'Recent non-identifying themes: ' . implode(', ', $topics) . '.';
        }

        if (!empty($protectiveFactors)) {
            $promptParts[] = 'Protective factors: ' . implode(', ', $protectiveFactors) . '.';
        }

        $promptParts[] = 'Use concise, low-bandwidth language and never mention hidden scores, model names, or private profile details.';

        return [
            'model_version' => self::MODEL_VERSION,
            'privacy_mode' => 'aggregated_features_only',
            'prompt_summary' => implode(' ', $promptParts),
            'risk_forecast' => $riskForecast,
            'focus_area' => $insights['focus_area'],
            'recommended_actions' => $insights['recommended_actions'],
            'dominant_topics' => $topics,
        ];
    }

    public function rankCounselorsForStudent(User|int $student, array $options = []): array
    {
        $studentId = $student instanceof User ? (int) $student->id : (int) $student;
        $studentInsights = $this->buildStudentMlInsights($studentId);
        $studentRiskScore = (int) ($studentInsights['risk_forecast']['score'] ?? 0);
        $preferredMode = strtolower(trim((string) ($options['mode'] ?? 'online')));
        $limit = max(1, min(20, (int) ($options['limit'] ?? 8)));
        $onlineThreshold = now()->subMinutes((int) env('COUNSELOR_ONLINE_WINDOW_MINUTES', 10));

        $counselors = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('role', 'counselor')->where('approved', true);
            })
            ->with(['profile:id,user_id,full_name'])
            ->select(['id', 'email', 'last_seen_at'])
            ->get();

        if ($counselors->isEmpty()) {
            return [];
        }

        $counselorIds = $counselors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $metrics = $this->buildCounselorRankingMetrics($counselorIds, $studentId);

        return $counselors
            ->map(function (User $counselor) use ($metrics, $onlineThreshold, $preferredMode, $studentRiskScore, $studentInsights) {
                $id = (int) $counselor->id;
                $summary = $metrics[$id] ?? [
                    'upcoming_appointments' => 0,
                    'completed_appointments' => 0,
                    'scheduled_appointments' => 0,
                    'prior_sessions_with_student' => 0,
                    'high_risk_experience' => 0,
                    'active_sessions' => 0,
                ];

                $completedAppointments = max(0, (int) $summary['completed_appointments']);
                $scheduledAppointments = max(0, (int) $summary['scheduled_appointments']);
                $upcomingAppointments = max(0, (int) $summary['upcoming_appointments']);
                $priorSessions = max(0, (int) $summary['prior_sessions_with_student']);
                $highRiskExperience = max(0, (int) $summary['high_risk_experience']);
                $activeSessions = max(0, (int) $summary['active_sessions']);

                $lastSeenAt = $counselor->last_seen_at;
                $minutesOffline = $lastSeenAt
                    ? max(0, (int) $lastSeenAt->diffInMinutes(now(), false))
                    : 999;
                $isOnline = $lastSeenAt !== null && $lastSeenAt->greaterThanOrEqualTo($onlineThreshold);

                $availabilityScore = $isOnline
                    ? 100
                    : max(35, 85 - min(50, (int) floor($minutesOffline / 15) * 5));
                $workloadScore = max(30, 100 - min(70, ($activeSessions * 22) + ($upcomingAppointments * 6)));
                $reliabilityScore = $scheduledAppointments > 0
                    ? (int) round(($completedAppointments / max(1, $scheduledAppointments)) * 100)
                    : 65;
                $experienceScore = $studentRiskScore >= 70
                    ? min(100, 45 + ($highRiskExperience * 12))
                    : min(100, 55 + ($completedAppointments * 3));
                $continuityScore = $priorSessions > 0 ? min(100, 70 + ($priorSessions * 10)) : 20;
                $modeScore = $preferredMode === 'online'
                    ? ($isOnline ? 100 : 55)
                    : 70;

                $finalScore = $this->clampInt(
                    ($availabilityScore * 0.18)
                    + ($workloadScore * 0.18)
                    + ($reliabilityScore * 0.25)
                    + ($experienceScore * 0.18)
                    + ($continuityScore * 0.16)
                    + ($modeScore * 0.05)
                );

                $reasons = [];
                if ($priorSessions > 0) {
                    $reasons[] = 'Strong continuity from your previous sessions.';
                }
                if ($isOnline) {
                    $reasons[] = 'Currently online for faster coordination.';
                }
                if ($studentRiskScore >= 70 && $highRiskExperience > 0) {
                    $reasons[] = 'Has recent experience supporting higher-risk cases.';
                }
                if ($workloadScore >= 70) {
                    $reasons[] = 'Balanced workload should improve responsiveness.';
                }
                if ($reliabilityScore >= 75) {
                    $reasons[] = 'Strong completion rate across recent appointments.';
                }
                if ($preferredMode === 'online') {
                    $reasons[] = 'Good fit for low-bandwidth online support.';
                }

                $riskFit = $studentRiskScore >= 70 ? 'high-support' : ($studentRiskScore >= 40 ? 'steady-support' : 'routine-support');

                return [
                    'id' => $id,
                    'email' => $counselor->email,
                    'is_online' => $isOnline,
                    'profile' => [
                        'full_name' => $counselor->profile?->full_name,
                    ],
                    'score' => $finalScore,
                    'fit' => $riskFit,
                    'reasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
                    'metrics' => [
                        'availability' => $availabilityScore,
                        'workload' => $workloadScore,
                        'reliability' => $reliabilityScore,
                        'experience' => $experienceScore,
                        'continuity' => $continuityScore,
                    ],
                    'student_focus' => $studentInsights['focus_area'],
                ];
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    public function buildHybridDiagnostic(
        CounselingSession $session,
        array $messages,
        array $analysis,
        ?array $localAnalysis = null
    ): array {
        $studentInsights = $this->buildStudentMlInsights((int) $session->student_id);
        $conversationFeatures = $this->extractConversationFeatures($messages);
        $baseRiskScore = $this->analysisToRiskScore($analysis);
        $localRiskScore = $localAnalysis ? $this->analysisToRiskScore($localAnalysis) : $baseRiskScore;
        $historyRiskScore = (int) ($studentInsights['risk_forecast']['score'] ?? 0);
        $behaviorRiskScore = $this->clampInt(
            ($conversationFeatures['distress_ratio'] * 100 * 0.45)
            + ($conversationFeatures['critical_hits'] * 20)
            + ($conversationFeatures['student_message_count'] >= 6 ? 10 : 0)
            + ($conversationFeatures['dominant_negative_topic'] !== null ? 8 : 0)
        );

        $hybridRisk = $this->clampInt(
            ($baseRiskScore * 0.48)
            + ($localRiskScore * 0.22)
            + ($historyRiskScore * 0.18)
            + ($behaviorRiskScore * 0.12)
        );

        if ($conversationFeatures['critical_hits'] > 0) {
            $hybridRisk = max($hybridRisk, 92);
        }

        $stressLevel = $this->clampInt(
            (($analysis['stress_level'] ?? 0) * 0.75)
            + ($behaviorRiskScore * 0.25)
        );
        $anxietyLevel = $this->clampInt(
            (($analysis['anxiety_level'] ?? 0) * 0.72)
            + ($conversationFeatures['anxiety_topic_hits'] * 7)
            + ($historyRiskScore * 0.12)
        );
        $depressionLevel = $this->clampInt(
            (($analysis['depression_level'] ?? 0) * 0.72)
            + ($conversationFeatures['sadness_topic_hits'] * 7)
            + ($conversationFeatures['critical_hits'] * 12)
        );

        $riskLevel = $conversationFeatures['critical_hits'] > 0
            ? 'critical'
            : $this->riskLabel($hybridRisk);

        if (($analysis['risk_level'] ?? 'low') === 'critical') {
            $riskLevel = 'critical';
            $hybridRisk = max($hybridRisk, 92);
        } elseif (($analysis['risk_level'] ?? 'low') === 'high' && $hybridRisk < 70) {
            $hybridRisk = 72;
            $riskLevel = 'high';
        }

        $insightParts = array_filter([
            $this->cleanText($analysis['insights'] ?? ''),
            sprintf(
                'Hybrid ML review observed %d student messages, %d distress cues, and a %s background trend.',
                $conversationFeatures['student_message_count'],
                $conversationFeatures['distress_hits'],
                $studentInsights['trend']['label']
            ),
            $conversationFeatures['dominant_negative_topic']
                ? sprintf('Dominant pressure theme: %s.', $conversationFeatures['dominant_negative_topic'])
                : null,
        ]);

        $recommendations = [
            $this->cleanText($analysis['recommendations'] ?? ''),
        ];

        if ($riskLevel === 'critical') {
            $recommendations[] = 'Escalate immediately to crisis support and keep a human counselor engaged without delay.';
        } elseif ($riskLevel === 'high') {
            $recommendations[] = 'Prioritize a counselor follow-up within 24 to 48 hours and confirm a concrete safety/support plan.';
        }

        $recommendations = array_merge($recommendations, array_slice($studentInsights['recommended_actions'], 0, 2));
        $recommendationText = implode(' ', array_values(array_unique(array_filter(array_map(
            fn ($item) => $this->cleanText((string) $item),
            $recommendations
        )))));

        return [
            'stress_level' => $stressLevel,
            'anxiety_level' => $anxietyLevel,
            'depression_level' => $depressionLevel,
            'mood' => $analysis['mood'] ?? ($localAnalysis['mood'] ?? 'guarded'),
            'risk_level' => $riskLevel,
            'insights' => implode(' ', $insightParts),
            'recommendations' => $recommendationText,
        ];
    }

    public function buildAdminMlOverview(): array
    {
        $studentIds = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('role', 'student')->where('approved', true);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($studentIds)) {
            return $this->emptyAdminMlOverview();
        }

        $snapshots = $this->buildStudentFeatureSnapshots($studentIds);
        $insights = collect($snapshots)
            ->map(fn (array $snapshot) => $this->buildStudentInsightPayload($snapshot));

        $distribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ];

        $anonymousScores = [];
        $namedScores = [];
        $agreementPool = [];

        foreach ($insights as $studentInsight) {
            $level = (string) ($studentInsight['risk_forecast']['level'] ?? 'low');
            if (array_key_exists($level, $distribution)) {
                $distribution[$level]++;
            }

            $score = (int) ($studentInsight['risk_forecast']['score'] ?? 0);
            if (($studentInsight['feature_snapshot']['anonymous_mode'] ?? false) === true) {
                $anonymousScores[] = $score;
            } else {
                $namedScores[] = $score;
            }

            $diagnosticLabel = (string) ($studentInsight['feature_snapshot']['latest_diagnostic_risk_level'] ?? '');
            $aiLabel = (string) ($studentInsight['feature_snapshot']['latest_ai_risk_level'] ?? '');
            if ($diagnosticLabel !== '' && $aiLabel !== '') {
                $agreementPool[] = abs($this->riskLabelToRank($diagnosticLabel) - $this->riskLabelToRank($aiLabel)) <= 1 ? 1 : 0;
            }
        }

        $studentsNeedingFollowUp = $insights
            ->filter(fn (array $item) => (int) ($item['risk_forecast']['score'] ?? 0) >= 70)
            ->values();
        $risingRiskStudents = $insights
            ->filter(fn (array $item) => (string) ($item['trend']['label'] ?? '') === 'rising')
            ->count();
        $followUpCoverage = $studentsNeedingFollowUp->count() > 0
            ? round(
                ($studentsNeedingFollowUp
                    ->filter(fn (array $item) => (int) (($item['feature_snapshot']['upcoming_appointments'] ?? 0)) > 0)
                    ->count() / $studentsNeedingFollowUp->count()) * 100,
                1
            )
            : 0.0;

        $chatUtilization = $insights
            ->filter(fn (array $item) => (int) (($item['feature_snapshot']['ai_chat_messages_30d'] ?? 0)) > 0)
            ->count();

        $anonymousAverage = !empty($anonymousScores) ? array_sum($anonymousScores) / count($anonymousScores) : 0.0;
        $namedAverage = !empty($namedScores) ? array_sum($namedScores) / count($namedScores) : 0.0;
        $fairnessGap = round(abs($anonymousAverage - $namedAverage), 1);
        $agreementRate = !empty($agreementPool)
            ? round((array_sum($agreementPool) / count($agreementPool)) * 100, 1)
            : 0.0;

        $topActions = $this->buildAdminPriorityActions($insights);

        return [
            'model_version' => self::MODEL_VERSION,
            'students_needing_follow_up' => $studentsNeedingFollowUp->count(),
            'rising_risk_students' => $risingRiskStudents,
            'chat_support_utilization_30d' => $chatUtilization,
            'proactive_follow_up_coverage' => $followUpCoverage,
            'risk_forecast_distribution' => $distribution,
            'top_actions' => $topActions,
            'validation' => [
                'diagnostic_agreement_rate' => $agreementRate,
                'fairness_gap' => $fairnessGap,
                'fairness_status' => $fairnessGap <= 10 ? 'stable' : 'monitor',
                'inference_mode' => 'lightweight_local_first',
                'response_time_budget_ms' => 150,
            ],
            'ethics' => [
                'privacy' => 'Aggregated behavior features only. Names, emails, and identifiers are excluded from ML prompts.',
                'human_review_required' => true,
                'low_bandwidth_mode' => true,
                'auditability' => 'Feature-derived scores with explicit thresholds and explainable match reasons.',
            ],
        ];
    }

    private function emptyStudentSnapshot(int $studentId): array
    {
        return [
            'student_id' => $studentId,
            'anonymous_mode' => false,
            'latest_diagnostic_score' => null,
            'latest_diagnostic_risk_level' => null,
            'previous_diagnostic_score' => null,
            'diagnostic_trend_delta' => 0,
            'latest_ai_score' => null,
            'latest_ai_risk_level' => null,
            'appointments_60d' => 0,
            'cancelled_appointments_60d' => 0,
            'cancel_rate_60d' => 0.0,
            'upcoming_appointments' => 0,
            'sessions_60d' => 0,
            'completed_sessions_60d' => 0,
            'session_minutes_60d' => 0,
            'mood_logs_14d' => 0,
            'low_mood_logs_14d' => 0,
            'ai_chat_messages_30d' => 0,
            'ai_chat_word_count_30d' => 0,
            'distress_messages_30d' => 0,
            'crisis_messages_30d' => 0,
            'topic_counts' => [],
        ];
    }

    /**
     * @param array<int> $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentFeatureSnapshots(array $studentIds): array
    {
        $studentIds = array_values(array_unique(array_map('intval', array_filter($studentIds))));
        if (empty($studentIds)) {
            return [];
        }

        $snapshots = [];
        $anonymousModes = User::query()
            ->with('profile:id,user_id,anonymous_mode')
            ->whereIn('id', $studentIds)
            ->get(['id'])
            ->mapWithKeys(function (User $student) {
                return [(int) $student->id => (bool) ($student->profile?->anonymous_mode ?? false)];
            });

        foreach ($studentIds as $studentId) {
            $snapshots[$studentId] = $this->emptyStudentSnapshot($studentId);
            $snapshots[$studentId]['anonymous_mode'] = (bool) ($anonymousModes[$studentId] ?? false);
        }

        $diagnostics = Diagnostic::query()
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', now()->subDays(180))
            ->orderByDesc('created_at')
            ->get(['student_id', 'total_score', 'risk_level', 'created_at']);

        foreach ($diagnostics as $diagnostic) {
            $studentId = (int) $diagnostic->student_id;
            if ($snapshots[$studentId]['latest_diagnostic_score'] === null) {
                $snapshots[$studentId]['latest_diagnostic_score'] = (int) $diagnostic->total_score;
                $snapshots[$studentId]['latest_diagnostic_risk_level'] = (string) $diagnostic->risk_level;
                continue;
            }

            if ($snapshots[$studentId]['previous_diagnostic_score'] === null) {
                $snapshots[$studentId]['previous_diagnostic_score'] = (int) $diagnostic->total_score;
                $snapshots[$studentId]['diagnostic_trend_delta'] =
                    (int) $snapshots[$studentId]['latest_diagnostic_score'] - (int) $diagnostic->total_score;
            }
        }

        $aiDiagnostics = AiDiagnostic::query()
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderByDesc('created_at')
            ->get([
                'student_id',
                'stress_level',
                'anxiety_level',
                'depression_level',
                'risk_level',
                'created_at',
            ]);

        foreach ($aiDiagnostics as $diagnostic) {
            $studentId = (int) $diagnostic->student_id;
            if ($snapshots[$studentId]['latest_ai_score'] !== null) {
                continue;
            }

            $snapshots[$studentId]['latest_ai_score'] = $this->resolveAiRiskScore($diagnostic);
            $snapshots[$studentId]['latest_ai_risk_level'] = (string) ($diagnostic->risk_level ?? 'low');
        }

        $appointments = Appointment::query()
            ->whereIn('student_id', $studentIds)
            ->where('scheduled_at', '>=', now()->subDays(60))
            ->get(['student_id', 'status', 'scheduled_at']);

        foreach ($appointments as $appointment) {
            $studentId = (int) $appointment->student_id;
            $snapshots[$studentId]['appointments_60d']++;

            if ((string) $appointment->status === 'cancelled') {
                $snapshots[$studentId]['cancelled_appointments_60d']++;
            }

            if (in_array((string) $appointment->status, ['scheduled', 'confirmed'], true) && $appointment->scheduled_at >= now()) {
                $snapshots[$studentId]['upcoming_appointments']++;
            }
        }

        foreach ($snapshots as $studentId => $snapshot) {
            $appointmentsCount = max(0, (int) $snapshot['appointments_60d']);
            $snapshots[$studentId]['cancel_rate_60d'] = $appointmentsCount > 0
                ? ((int) $snapshot['cancelled_appointments_60d'] / $appointmentsCount)
                : 0.0;
        }

        $sessions = CounselingSession::query()
            ->whereIn('student_id', $studentIds)
            ->where('created_at', '>=', now()->subDays(60))
            ->get(['student_id', 'status', 'started_at', 'ended_at']);

        foreach ($sessions as $session) {
            $studentId = (int) $session->student_id;
            $snapshots[$studentId]['sessions_60d']++;

            if ((string) $session->status === 'completed') {
                $snapshots[$studentId]['completed_sessions_60d']++;
            }

            if ($session->started_at && $session->ended_at) {
                $minutes = max(0, (int) $session->started_at->diffInMinutes($session->ended_at, false));
                $snapshots[$studentId]['session_minutes_60d'] += $minutes;
            }
        }

        $moodLogs = StudentMoodLog::query()
            ->whereIn('student_id', $studentIds)
            ->where('logged_on', '>=', now()->subDays(14)->toDateString())
            ->get(['student_id', 'mood', 'logged_on']);

        foreach ($moodLogs as $log) {
            $studentId = (int) $log->student_id;
            $snapshots[$studentId]['mood_logs_14d']++;
            if (in_array((string) $log->mood, ['low', 'stressed', 'tired'], true)) {
                $snapshots[$studentId]['low_mood_logs_14d']++;
            }
        }

        $chatMessages = DB::table('chat_messages')
            ->join('chat_conversations', 'chat_messages.conversation_id', '=', 'chat_conversations.id')
            ->whereIn('chat_conversations.user_id', $studentIds)
            ->where('chat_messages.role', 'user')
            ->where('chat_messages.created_at', '>=', now()->subDays(30))
            ->orderBy('chat_messages.id')
            ->get([
                'chat_conversations.user_id as student_id',
                'chat_messages.content',
            ]);

        foreach ($chatMessages as $row) {
            $studentId = (int) $row->student_id;
            $normalized = $this->normalizeText((string) $row->content);
            if ($normalized === '') {
                continue;
            }

            $snapshots[$studentId]['ai_chat_messages_30d']++;
            $snapshots[$studentId]['ai_chat_word_count_30d'] += str_word_count($normalized);

            $distressHits = $this->countKeywordHits($normalized, self::DISTRESS_TERMS);
            $crisisHits = $this->countKeywordHits($normalized, self::CRISIS_TERMS);

            if ($distressHits > 0) {
                $snapshots[$studentId]['distress_messages_30d']++;
            }
            if ($crisisHits > 0) {
                $snapshots[$studentId]['crisis_messages_30d']++;
            }

            $topic = $this->detectDominantTopic($normalized);
            if ($topic !== null) {
                $snapshots[$studentId]['topic_counts'][$topic] = (int) (($snapshots[$studentId]['topic_counts'][$topic] ?? 0) + 1);
            }
        }

        return $snapshots;
    }

    /**
     * @param array<int> $counselorIds
     * @return array<int, array<string, int>>
     */
    private function buildCounselorRankingMetrics(array $counselorIds, int $studentId): array
    {
        $metrics = [];
        foreach ($counselorIds as $counselorId) {
            $metrics[(int) $counselorId] = [
                'upcoming_appointments' => 0,
                'completed_appointments' => 0,
                'scheduled_appointments' => 0,
                'prior_sessions_with_student' => 0,
                'high_risk_experience' => 0,
                'active_sessions' => 0,
            ];
        }

        $appointments = Appointment::query()
            ->whereIn('counselor_id', $counselorIds)
            ->where('scheduled_at', '>=', now()->subDays(180))
            ->get(['id', 'counselor_id', 'student_id', 'status', 'scheduled_at']);

        foreach ($appointments as $appointment) {
            $counselorId = (int) $appointment->counselor_id;
            if (!isset($metrics[$counselorId])) {
                continue;
            }

            if (in_array((string) $appointment->status, ['completed', 'confirmed', 'scheduled'], true)) {
                $metrics[$counselorId]['scheduled_appointments']++;
            }
            if ((string) $appointment->status === 'completed') {
                $metrics[$counselorId]['completed_appointments']++;
            }
            if (
                in_array((string) $appointment->status, ['scheduled', 'confirmed'], true)
                && $appointment->scheduled_at >= now()
            ) {
                $metrics[$counselorId]['upcoming_appointments']++;
            }
            if ((int) $appointment->student_id === $studentId) {
                $metrics[$counselorId]['prior_sessions_with_student']++;
            }
        }

        $sessions = CounselingSession::query()
            ->whereIn('counselor_id', $counselorIds)
            ->where('created_at', '>=', now()->subDays(180))
            ->get(['id', 'counselor_id', 'student_id', 'status']);

        $sessionIds = [];
        foreach ($sessions as $session) {
            $counselorId = (int) $session->counselor_id;
            if (!isset($metrics[$counselorId])) {
                continue;
            }

            if ((string) $session->status === 'active') {
                $metrics[$counselorId]['active_sessions']++;
            }
            if ((int) $session->student_id === $studentId) {
                $metrics[$counselorId]['prior_sessions_with_student']++;
            }

            $sessionIds[] = (int) $session->id;
        }

        if (!empty($sessionIds)) {
            $highRiskSessionCounts = AiDiagnostic::query()
                ->whereIn('session_id', $sessionIds)
                ->whereIn('risk_level', ['high', 'critical'])
                ->get(['session_id']);

            $sessionCounselorMap = $sessions->pluck('counselor_id', 'id');

            foreach ($highRiskSessionCounts as $diagnostic) {
                $sessionId = (int) ($diagnostic->session_id ?? 0);
                $counselorId = (int) ($sessionCounselorMap[$sessionId] ?? 0);
                if ($counselorId > 0 && isset($metrics[$counselorId])) {
                    $metrics[$counselorId]['high_risk_experience']++;
                }
            }
        }

        return $metrics;
    }

    private function buildStudentInsightPayload(array $snapshot): array
    {
        $riskScore = $this->buildForecastRiskScore($snapshot);
        $engagementScore = $this->buildEngagementScore($snapshot, $riskScore);
        $trendLabel = $this->buildTrendLabel($snapshot);
        $focusArea = $this->buildFocusArea($snapshot, $riskScore);
        $protectiveFactors = $this->buildProtectiveFactors($snapshot, $trendLabel);
        $recommendedActions = $this->buildRecommendedActions($snapshot, $riskScore, $focusArea);

        return [
            'model_version' => self::MODEL_VERSION,
            'risk_forecast' => [
                'score' => $riskScore,
                'level' => $this->riskLabel($riskScore),
                'confidence' => $this->estimateConfidence($snapshot),
            ],
            'engagement' => [
                'score' => $engagementScore,
                'label' => $engagementScore >= 70 ? 'high' : ($engagementScore >= 45 ? 'moderate' : 'low'),
            ],
            'trend' => [
                'label' => $trendLabel,
                'delta' => (int) ($snapshot['diagnostic_trend_delta'] ?? 0),
            ],
            'focus_area' => $focusArea,
            'dominant_topics' => $this->dominantTopicsFromSnapshot($snapshot),
            'protective_factors' => $protectiveFactors,
            'recommended_actions' => $recommendedActions,
            'feature_snapshot' => [
                'anonymous_mode' => (bool) ($snapshot['anonymous_mode'] ?? false),
                'latest_diagnostic_risk_level' => $snapshot['latest_diagnostic_risk_level'] ?? null,
                'latest_ai_risk_level' => $snapshot['latest_ai_risk_level'] ?? null,
                'upcoming_appointments' => (int) ($snapshot['upcoming_appointments'] ?? 0),
                'ai_chat_messages_30d' => (int) ($snapshot['ai_chat_messages_30d'] ?? 0),
                'distress_messages_30d' => (int) ($snapshot['distress_messages_30d'] ?? 0),
                'cancel_rate_60d' => round((float) ($snapshot['cancel_rate_60d'] ?? 0), 2),
                'mood_logs_14d' => (int) ($snapshot['mood_logs_14d'] ?? 0),
                'completed_sessions_60d' => (int) ($snapshot['completed_sessions_60d'] ?? 0),
            ],
        ];
    }

    private function buildForecastRiskScore(array $snapshot): int
    {
        $diagnosticScore = is_numeric($snapshot['latest_diagnostic_score']) ? (int) $snapshot['latest_diagnostic_score'] : null;
        $aiScore = is_numeric($snapshot['latest_ai_score']) ? (int) $snapshot['latest_ai_score'] : null;
        $cancelRate = (float) ($snapshot['cancel_rate_60d'] ?? 0.0);
        $distressRatio = ((int) ($snapshot['distress_messages_30d'] ?? 0)) / max(1, (int) ($snapshot['ai_chat_messages_30d'] ?? 0));
        $base = 0.0;

        if (is_int($diagnosticScore)) {
            $base += $diagnosticScore * 0.40;
        }
        if (is_int($aiScore)) {
            $base += $aiScore * 0.34;
        }
        if (!is_int($diagnosticScore) && !is_int($aiScore)) {
            $base += min(55, ((int) ($snapshot['distress_messages_30d'] ?? 0) * 12) + ((int) ($snapshot['low_mood_logs_14d'] ?? 0) * 6));
        }

        $base += min(18, $distressRatio * 100 * 0.18);
        $base += min(12, $cancelRate * 100 * 0.12);
        $base += min(12, (int) ($snapshot['low_mood_logs_14d'] ?? 0) * 2.5);

        $trendDelta = (int) ($snapshot['diagnostic_trend_delta'] ?? 0);
        if ($trendDelta >= 12) {
            $base += min(12, $trendDelta * 0.45);
        } elseif ($trendDelta <= -12) {
            $base -= min(10, abs($trendDelta) * 0.30);
        }

        if ((int) ($snapshot['crisis_messages_30d'] ?? 0) > 0) {
            $base = max($base, 90);
        }

        $base -= min(10, (int) ($snapshot['upcoming_appointments'] ?? 0) * 4);
        $base -= min(8, (int) ($snapshot['completed_sessions_60d'] ?? 0) * 1.5);

        return $this->clampInt($base);
    }

    private function buildEngagementScore(array $snapshot, int $riskScore): int
    {
        $engagement = 22
            + ((int) ($snapshot['completed_sessions_60d'] ?? 0) * 9)
            + ((int) ($snapshot['upcoming_appointments'] ?? 0) * 7)
            + ((int) ($snapshot['mood_logs_14d'] ?? 0) * 3)
            + min(12, (int) (($snapshot['ai_chat_messages_30d'] ?? 0) * 1.2))
            - ((int) ($snapshot['cancelled_appointments_60d'] ?? 0) * 8);

        if ($riskScore >= 70 && (int) ($snapshot['upcoming_appointments'] ?? 0) === 0) {
            $engagement -= 8;
        }

        return $this->clampInt($engagement);
    }

    private function buildTrendLabel(array $snapshot): string
    {
        $delta = (int) ($snapshot['diagnostic_trend_delta'] ?? 0);
        if ((int) ($snapshot['crisis_messages_30d'] ?? 0) > 0 || $delta >= 12) {
            return 'rising';
        }
        if ($delta <= -12) {
            return 'improving';
        }

        $cancelRate = (float) ($snapshot['cancel_rate_60d'] ?? 0.0);
        if ($cancelRate >= 0.4 && (int) ($snapshot['upcoming_appointments'] ?? 0) === 0) {
            return 'fragile';
        }

        return 'steady';
    }

    private function buildFocusArea(array $snapshot, int $riskScore): string
    {
        if ((int) ($snapshot['crisis_messages_30d'] ?? 0) > 0 || $riskScore >= 85) {
            return 'Immediate safety review';
        }

        $dominantTopic = $this->dominantTopicsFromSnapshot($snapshot)[0] ?? null;
        if ($dominantTopic !== null) {
            return match ($dominantTopic) {
                'anxiety' => 'Stress regulation and grounding',
                'study' => 'Academic pressure stabilization',
                'sleep' => 'Sleep recovery support',
                'sadness' => 'Mood recovery and connection',
                'relationships' => 'Relationship coping support',
                'financial' => 'Practical support planning',
                default => 'Support continuity',
            };
        }

        if ((float) ($snapshot['cancel_rate_60d'] ?? 0.0) >= 0.35) {
            return 'Session continuity and follow-up';
        }

        return 'Routine wellbeing support';
    }

    /**
     * @return array<int, string>
     */
    private function buildProtectiveFactors(array $snapshot, string $trendLabel): array
    {
        $factors = [];

        if ((int) ($snapshot['upcoming_appointments'] ?? 0) > 0) {
            $factors[] = 'Upcoming counselor follow-up already scheduled';
        }
        if ((int) ($snapshot['completed_sessions_60d'] ?? 0) >= 2) {
            $factors[] = 'Recent counseling continuity';
        }
        if ((int) ($snapshot['mood_logs_14d'] ?? 0) >= 4) {
            $factors[] = 'Consistent self-check-ins';
        }
        if ($trendLabel === 'improving') {
            $factors[] = 'Recent wellness trend is improving';
        }
        if ((float) ($snapshot['cancel_rate_60d'] ?? 0.0) <= 0.1 && (int) ($snapshot['appointments_60d'] ?? 0) > 0) {
            $factors[] = 'Low cancellation pattern';
        }

        return array_slice($factors, 0, 3);
    }

    /**
     * @return array<int, string>
     */
    private function buildRecommendedActions(array $snapshot, int $riskScore, string $focusArea): array
    {
        $actions = [];

        if ($riskScore >= 85) {
            $actions[] = 'Escalate to a counselor or crisis contact immediately and keep direct human support active.';
        } elseif ($riskScore >= 70) {
            $actions[] = 'Book or confirm a counselor follow-up within the next 48 hours.';
        } elseif ($riskScore >= 45) {
            $actions[] = 'Keep a structured check-in this week and monitor any change in stress or mood.';
        }

        if ((float) ($snapshot['cancel_rate_60d'] ?? 0.0) >= 0.35) {
            $actions[] = 'Reduce cancellations by choosing one stable session slot for the next two weeks.';
        }

        if ((int) ($snapshot['upcoming_appointments'] ?? 0) === 0) {
            $actions[] = 'No follow-up is scheduled yet. Add one low-bandwidth support session to keep continuity.';
        }

        $actions[] = match ($focusArea) {
            'Stress regulation and grounding' => 'Use one short grounding cycle today: 60 seconds of breathing and one 10-minute task.',
            'Academic pressure stabilization' => 'Break your workload into one short focus block and one recovery break today.',
            'Sleep recovery support' => 'Protect tonight with a simple wind-down routine and reduced screen time before bed.',
            'Mood recovery and connection' => 'Reach out to one trusted person today and avoid carrying the hardest thoughts alone.',
            'Relationship coping support' => 'Pause before reacting and write down the one boundary or support action you need next.',
            'Practical support planning' => 'List the most urgent practical pressure and contact one campus or trusted support point today.',
            default => 'Keep the next step small, concrete, and realistic for a low-bandwidth day.',
        };

        return array_slice(array_values(array_unique(array_filter($actions))), 0, 4);
    }

    /**
     * @return array<int, string>
     */
    private function dominantTopicsFromSnapshot(array $snapshot): array
    {
        $topicCounts = collect($snapshot['topic_counts'] ?? [])
            ->filter(fn ($count) => is_numeric($count) && (int) $count > 0)
            ->sortByDesc(fn ($count) => (int) $count)
            ->take(2);

        return $topicCounts->keys()->values()->all();
    }

    private function estimateConfidence(array $snapshot): int
    {
        $signals = 0;
        if ($snapshot['latest_diagnostic_score'] !== null) $signals++;
        if ($snapshot['latest_ai_score'] !== null) $signals++;
        if ((int) ($snapshot['appointments_60d'] ?? 0) > 0) $signals++;
        if ((int) ($snapshot['sessions_60d'] ?? 0) > 0) $signals++;
        if ((int) ($snapshot['ai_chat_messages_30d'] ?? 0) > 0) $signals++;
        if ((int) ($snapshot['mood_logs_14d'] ?? 0) > 0) $signals++;

        return $this->clampInt(35 + ($signals * 10), 35, 95);
    }

    /**
     * @param Collection<int, array<string, mixed>> $insights
     * @return array<int, string>
     */
    private function buildAdminPriorityActions(Collection $insights): array
    {
        $actions = [];

        $studentsNeedingFollowUp = $insights
            ->filter(fn (array $item) => (int) ($item['risk_forecast']['score'] ?? 0) >= 70);
        $studentsWithoutAppointments = $studentsNeedingFollowUp
            ->filter(fn (array $item) => (int) (($item['feature_snapshot']['upcoming_appointments'] ?? 0)) === 0)
            ->count();

        if ($studentsWithoutAppointments > 0) {
            $actions[] = 'High-risk students without a scheduled follow-up need immediate outreach.';
        }

        $sleepPressure = $insights
            ->filter(fn (array $item) => in_array('sleep', $item['dominant_topics'] ?? [], true))
            ->count();
        if ($sleepPressure > 0) {
            $actions[] = 'Sleep-related strain is rising. Prioritize short recovery guidance and counselor follow-ups.';
        }

        $continuityRisk = $insights
            ->filter(fn (array $item) => (float) (($item['feature_snapshot']['cancel_rate_60d'] ?? 0)) >= 0.35)
            ->count();
        if ($continuityRisk > 0) {
            $actions[] = 'Review cancellation-heavy students and offer more stable appointment slots.';
        }

        if (empty($actions)) {
            $actions[] = 'ML signals are stable. Continue monitoring and keep human review active for elevated-risk cases.';
        }

        return array_slice($actions, 0, 3);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, int|float|string|null>
     */
    private function extractConversationFeatures(array $messages): array
    {
        $studentMessageCount = 0;
        $distressHits = 0;
        $criticalHits = 0;
        $topicCounts = [];
        $anxietyTopicHits = 0;
        $sadnessTopicHits = 0;

        foreach ($messages as $message) {
            $sender = strtolower(trim((string) ($message['sender'] ?? $message['role'] ?? 'user')));
            if (!in_array($sender, ['student', 'user'], true)) {
                continue;
            }

            $normalized = $this->normalizeText((string) ($message['content'] ?? ''));
            if ($normalized === '') {
                continue;
            }

            $studentMessageCount++;
            $distressHits += $this->countKeywordHits($normalized, self::DISTRESS_TERMS);
            $criticalHits += $this->countKeywordHits($normalized, self::CRISIS_TERMS);
            $anxietyTopicHits += $this->countKeywordHits($normalized, self::CHAT_TOPICS['anxiety']);
            $sadnessTopicHits += $this->countKeywordHits($normalized, self::CHAT_TOPICS['sadness']);

            $topic = $this->detectDominantTopic($normalized);
            if ($topic !== null) {
                $topicCounts[$topic] = (int) (($topicCounts[$topic] ?? 0) + 1);
            }
        }

        arsort($topicCounts);
        $dominantNegativeTopic = null;
        foreach (array_keys($topicCounts) as $topic) {
            if (in_array($topic, ['anxiety', 'sadness', 'sleep', 'relationships', 'financial'], true)) {
                $dominantNegativeTopic = $topic;
                break;
            }
        }

        return [
            'student_message_count' => $studentMessageCount,
            'distress_hits' => $distressHits,
            'critical_hits' => $criticalHits,
            'distress_ratio' => round($distressHits / max(1, $studentMessageCount), 3),
            'dominant_negative_topic' => $dominantNegativeTopic,
            'anxiety_topic_hits' => $anxietyTopicHits,
            'sadness_topic_hits' => $sadnessTopicHits,
        ];
    }

    private function detectDominantTopic(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }

        $bestTopic = null;
        $bestScore = 0;

        foreach (self::CHAT_TOPICS as $topic => $terms) {
            $score = $this->countKeywordHits($normalized, $terms);
            if ($score > $bestScore) {
                $bestTopic = $topic;
                $bestScore = $score;
            }
        }

        return $bestScore > 0 ? $bestTopic : null;
    }

    private function analysisToRiskScore(array $analysis): int
    {
        $levels = array_filter([
            $analysis['stress_level'] ?? null,
            $analysis['anxiety_level'] ?? null,
            $analysis['depression_level'] ?? null,
        ], fn ($value) => is_numeric($value));

        $levelAverage = !empty($levels)
            ? (float) (array_sum(array_map('floatval', $levels)) / count($levels))
            : null;

        $mappedRisk = match ((string) ($analysis['risk_level'] ?? '')) {
            'critical' => 92,
            'high' => 75,
            'medium' => 50,
            'low' => 25,
            default => null,
        };

        if ($levelAverage !== null && is_int($mappedRisk)) {
            return $this->clampInt(($levelAverage * 0.72) + ($mappedRisk * 0.28));
        }

        if ($levelAverage !== null) {
            return $this->clampInt($levelAverage);
        }

        return is_int($mappedRisk) ? $mappedRisk : 25;
    }

    private function resolveAiRiskScore(AiDiagnostic $diagnostic): int
    {
        return $this->analysisToRiskScore([
            'stress_level' => $diagnostic->stress_level,
            'anxiety_level' => $diagnostic->anxiety_level,
            'depression_level' => $diagnostic->depression_level,
            'risk_level' => $diagnostic->risk_level,
        ]);
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

    private function riskLabelToRank(string $label): int
    {
        return match (strtolower(trim($label))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    private function normalizeText(string $text): string
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized);
        $normalized = is_string($normalized)
            ? preg_replace('/\s+/u', ' ', $normalized)
            : '';

        return is_string($normalized) ? trim($normalized) : '';
    }

    /**
     * Counts distinct keyword/phrase matches in a normalized string.
     *
     * @param array<int, string> $terms
     */
    private function countKeywordHits(string $text, array $terms): int
    {
        $hits = 0;
        foreach ($terms as $term) {
            $needle = $this->normalizeText((string) $term);
            if ($needle !== '' && str_contains($text, $needle)) {
                $hits++;
            }
        }

        return $hits;
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

    private function emptyAdminMlOverview(): array
    {
        return [
            'model_version' => self::MODEL_VERSION,
            'students_needing_follow_up' => 0,
            'rising_risk_students' => 0,
            'chat_support_utilization_30d' => 0,
            'proactive_follow_up_coverage' => 0.0,
            'risk_forecast_distribution' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'critical' => 0,
            ],
            'top_actions' => [
                'No student ML data is available yet. Complete diagnostics, chats, or sessions to activate monitoring.',
            ],
            'validation' => [
                'diagnostic_agreement_rate' => 0.0,
                'fairness_gap' => 0.0,
                'fairness_status' => 'stable',
                'inference_mode' => 'lightweight_local_first',
                'response_time_budget_ms' => 150,
            ],
            'ethics' => [
                'privacy' => 'Aggregated behavior features only. Names, emails, and identifiers are excluded from ML prompts.',
                'human_review_required' => true,
                'low_bandwidth_mode' => true,
                'auditability' => 'Feature-derived scores with explicit thresholds and explainable match reasons.',
            ],
        ];
    }
}
