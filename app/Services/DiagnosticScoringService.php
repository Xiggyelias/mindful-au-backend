<?php

namespace App\Services;

class DiagnosticScoringService
{
    private const QUESTION_WEIGHTS = [
        'anxiety' => 1.2,
        'depression' => 1.3,
        'stress' => 1.1,
        'academic' => 1.0,
        'social' => 0.9,
        'sleep' => 1.15,
        'substance' => 1.5,
        // University categories (used by legacy fallback path)
        'mood' => 1.3,
        'school' => 1.0,
        'campus_life' => 0.9,
        'identity' => 0.85,
        'coping' => 1.1,
        'physical' => 0.8,
    ];

    /**
     * Per-question polarity and category metadata for the built-in university
     * question bank (UNIVERSITY_QUESTIONS in the frontend).  Used by
     * calculateUniversityV1() to score responses whose keys start with "univ_".
     */
    private const UNIVERSITY_QUESTION_META = [
        // ── school ────────────────────────────────────────────────────────────
        'univ_school_engagement' => ['category' => 'school',      'polarity' => 'positive'],
        'univ_school_concentration' => ['category' => 'school',      'polarity' => 'negative'],
        'univ_school_lecturers' => ['category' => 'school',      'polarity' => 'positive'],
        // ── academic ──────────────────────────────────────────────────────────
        'univ_acad_overload' => ['category' => 'academic',    'polarity' => 'negative'],
        'univ_acad_exam_anxiety' => ['category' => 'academic',    'polarity' => 'negative'],
        'univ_acad_procrastination' => ['category' => 'academic',    'polarity' => 'negative'],
        // ── mood ──────────────────────────────────────────────────────────────
        'univ_mood_lowness' => ['category' => 'mood',        'polarity' => 'negative'],
        'univ_mood_motivation' => ['category' => 'mood',        'polarity' => 'negative'],
        'univ_mood_enjoyment' => ['category' => 'mood',        'polarity' => 'negative'],
        // ── anxiety ───────────────────────────────────────────────────────────
        'univ_anxiety_tension' => ['category' => 'anxiety',     'polarity' => 'negative'],
        'univ_anxiety_overwhelm' => ['category' => 'anxiety',     'polarity' => 'negative'],
        'univ_anxiety_worry' => ['category' => 'anxiety',     'polarity' => 'negative'],
        // ── sleep ─────────────────────────────────────────────────────────────
        'univ_sleep_quality' => ['category' => 'sleep',       'polarity' => 'positive'],
        'univ_sleep_fatigue' => ['category' => 'sleep',       'polarity' => 'negative'],
        'univ_sleep_disruption' => ['category' => 'sleep',       'polarity' => 'negative'],
        // ── social ────────────────────────────────────────────────────────────
        'univ_social_belonging' => ['category' => 'social',      'polarity' => 'positive'],
        'univ_social_loneliness' => ['category' => 'social',      'polarity' => 'negative'],
        'univ_social_relationships' => ['category' => 'social',      'polarity' => 'positive'],
        // ── campus_life ───────────────────────────────────────────────────────
        'univ_campus_finances' => ['category' => 'campus_life', 'polarity' => 'negative'],
        'univ_campus_adjustment' => ['category' => 'campus_life', 'polarity' => 'positive'],
        'univ_campus_homesick' => ['category' => 'campus_life', 'polarity' => 'negative'],
        // ── identity ──────────────────────────────────────────────────────────
        'univ_identity_confidence' => ['category' => 'identity',    'polarity' => 'positive'],
        'univ_identity_pressure' => ['category' => 'identity',    'polarity' => 'negative'],
        'univ_identity_direction' => ['category' => 'identity',    'polarity' => 'positive'],
        // ── coping ────────────────────────────────────────────────────────────
        'univ_coping_manage' => ['category' => 'coping',      'polarity' => 'positive'],
        'univ_support_network' => ['category' => 'coping',      'polarity' => 'positive'],
        'univ_support_stigma' => ['category' => 'coping',      'polarity' => 'negative'],
        // ── physical ──────────────────────────────────────────────────────────
        'univ_physical_selfcare' => ['category' => 'physical',    'polarity' => 'positive'],
        'univ_physical_symptoms' => ['category' => 'physical',    'polarity' => 'negative'],
        'univ_physical_restlessness' => ['category' => 'physical',    'polarity' => 'negative'],
    ];

    /**
     * Composite-index weights for each university question category.
     * Higher weights = that domain has more clinical pull on the overall score.
     */
    private const UNIVERSITY_CATEGORY_WEIGHTS = [
        'mood' => 1.35,  // Depression/mood — highest clinical weight
        'anxiety' => 1.30,  // Anxiety and panic
        'academic' => 1.20,  // Primary stressor source for students
        'sleep' => 1.15,  // Functional impact on everything
        'coping' => 1.10,  // Protective/risk factor capacity
        'social' => 1.05,  // Belonging and connection
        'school' => 1.00,  // Engagement and concentration
        'campus_life' => 0.90,  // Contextual pressures
        'identity' => 0.85,  // Self-view and purpose
        'physical' => 0.80,  // Physical wellbeing
    ];

    private const RISK_THRESHOLDS = [
        'low' => 35,   // aligned with MentalHealthMlService::riskLabel (score < 36 = low)
        'medium' => 60,
        'high' => 80,
        'critical' => 100,
    ];

    /** Category weights contributing to composite distress index (pre-counselling). */
    private const PRE_INDEX_WEIGHTS = [
        'emotional_distress' => 1.35,
        'cognitive_patterns' => 1.15,
        'behavioural_patterns' => 1.05,
        'functional_impact' => 1.2,
        'stress_load' => 1.15,
        'social_support' => 1.0,
        'self_resources' => 0.85,
        'coping' => 0.75,
        'context' => 0.35,
        'session_goals' => 0.4,
    ];

    /**
     * @param  array<string, mixed>  $responses
     * @param  array<string, mixed>  $questionnaire  Normalised: optional meta + 'questions' list
     */
    public function calculateScore(array $responses, array $questionnaire): array
    {
        /** @var array<int, array<string, mixed>> $questions */
        $questions = $questionnaire['questions'] ?? [];

        // University question bank: front-end always submits its own built-in
        // questions whose IDs start with "univ_".  These IDs do not appear in
        // any stored questionnaire, so they must be scored with dedicated logic
        // that knows each question's category and polarity.  Detect this before
        // trying any other scoring model.
        if ($this->hasUniversityResponses($responses)) {
            return $this->calculateUniversityV1($responses);
        }

        if (($questionnaire['meta']['scoring_model'] ?? null) === 'pre_counselling_v1') {
            return $this->calculatePreCounsellingV1($responses, $questions);
        }

        $categoryScores = [];
        $totalScore = 0;
        $responseCount = 0;

        foreach ($questions as $question) {
            $questionId = $question['id'];
            $category = $question['category'] ?? 'general';
            $weight = self::QUESTION_WEIGHTS[$category] ?? 1.0;

            if (! isset($responses[$questionId])) {
                continue;
            }

            $response = $responses[$questionId];
            $score = $this->scoreResponse($response, $question);
            $weightedScore = $score * $weight;

            if (! isset($categoryScores[$category])) {
                $categoryScores[$category] = ['total' => 0, 'count' => 0];
            }

            $categoryScores[$category]['total'] += $weightedScore;
            $categoryScores[$category]['count']++;
            $totalScore += $weightedScore;
            $responseCount++;
        }

        $normalizedCategoryScores = [];
        foreach ($categoryScores as $category => $data) {
            if ($data['count'] > 0) {
                $normalizedCategoryScores[$category] = round(($data['total'] / $data['count']) / 5 * 100);
            }
        }

        $averageScore = $responseCount > 0 ? round(($totalScore / $responseCount) / 5 * 100) : 0;

        $riskLevel = $this->determineRiskLevel($averageScore);

        return [
            'total_score' => $averageScore,
            'category_scores' => $normalizedCategoryScores,
            'risk_level' => $riskLevel,
            'notify_counselors' => in_array($riskLevel, ['high', 'critical'], true),
            'counselor_summary' => null,
            'focus_areas' => null,
            'risk_flags' => null,
            'scoring_model' => null,
        ];
    }

    /**
     * Returns true when at least 5 response keys start with "univ_", indicating
     * the front-end submitted from its built-in university question bank rather
     * than from the stored questionnaire.
     */
    private function hasUniversityResponses(array $responses): bool
    {
        $count = 0;
        foreach (array_keys($responses) as $key) {
            if (str_starts_with((string) $key, 'univ_')) {
                $count++;
                if ($count >= 5) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Score a set of university question responses using the built-in question
     * metadata (category + polarity) and weighted composite index.
     *
     * All university questions are scale_1_5 with values 1–5 (already
     * normalised to integers by the front-end's normalizeResponses()).
     *
     * @param  array<string, mixed>  $responses
     */
    private function calculateUniversityV1(array $responses): array
    {
        /** @var array<string, float[]> $byCategory */
        $byCategory = [];

        foreach (self::UNIVERSITY_QUESTION_META as $questionId => $meta) {
            if (! array_key_exists($questionId, $responses)) {
                continue;
            }

            $rawValue = $responses[$questionId];

            // Values should already be integers after normaliseResponses(), but
            // handle string-numerics and empty/null defensively.
            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $intValue = max(1, min(5, (int) $rawValue));
            $distress = $this->distressFromScale($intValue, 5, (string) $meta['polarity']);

            $category = (string) $meta['category'];
            $byCategory[$category][] = $distress;
        }

        if ($byCategory === []) {
            // No recognised university responses could be scored.
            return [
                'total_score' => 0,
                'category_scores' => [],
                'risk_level' => 'low',
                'notify_counselors' => false,
                'counselor_summary' => null,
                'focus_areas' => null,
                'risk_flags' => [],
                'scoring_model' => 'university_v1',
            ];
        }

        // Average distress within each category → 0–100 integer
        $categoryScores = [];
        foreach ($byCategory as $cat => $values) {
            $categoryScores[$cat] = (int) round(array_sum($values) / count($values));
        }

        // Weighted composite index
        $indexTotal = 0.0;
        $indexWeight = 0.0;
        foreach (self::UNIVERSITY_CATEGORY_WEIGHTS as $cat => $w) {
            if (! isset($categoryScores[$cat])) {
                continue;
            }
            $indexTotal += $categoryScores[$cat] * $w;
            $indexWeight += $w;
        }

        $composite = $indexWeight > 0 ? (int) round($indexTotal / $indexWeight) : 0;
        $riskLevel = $this->determineRiskLevel($composite);

        $focusAreas = $this->deriveFocusAreas($categoryScores);
        $counselorSummary = $this->buildCounselorSummary($categoryScores, [], $focusAreas, $riskLevel);

        return [
            'total_score' => $composite,
            'category_scores' => $categoryScores,
            'risk_level' => $riskLevel,
            'notify_counselors' => in_array($riskLevel, ['high', 'critical'], true),
            'counselor_summary' => $counselorSummary,
            'focus_areas' => $focusAreas,
            'risk_flags' => [],
            'scoring_model' => 'university_v1',
        ];
    }

    /**
     * @param  array<string, mixed>  $responses
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function calculatePreCounsellingV1(array $responses, array $questions): array
    {
        /** @var array<string, float[]> $byCategory */
        $byCategory = [];
        $riskFlags = [];

        foreach ($questions as $question) {
            $id = $question['id'];
            $category = (string) ($question['category'] ?? 'general');
            $required = ($question['required'] ?? true) === true;

            if (! array_key_exists($id, $responses)) {
                if ($required) {
                    // Missing required answer — neutral penalty band so submission still validates elsewhere
                    $distress = 50;
                } else {
                    continue;
                }
            } else {
                $distress = $this->scorePreCounsellingItem($responses[$id], $question, $riskFlags);
            }

            if ($distress === null) {
                continue;
            }

            if (! isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }
            $byCategory[$category][] = (float) $distress;
        }

        $categoryScores = [];
        foreach ($byCategory as $cat => $values) {
            if ($cat === 'safety') {
                continue;
            }
            if ($values === []) {
                continue;
            }
            $categoryScores[$cat] = (int) round(array_sum($values) / count($values));
        }

        $indexTotal = 0.0;
        $indexWeight = 0.0;
        foreach (self::PRE_INDEX_WEIGHTS as $cat => $w) {
            if (! isset($categoryScores[$cat])) {
                continue;
            }
            $indexTotal += $categoryScores[$cat] * $w;
            $indexWeight += $w;
        }

        $composite = $indexWeight > 0 ? (int) round($indexTotal / $indexWeight) : 0;

        $riskFromScore = $this->determineRiskLevel($composite);
        $riskLevel = $this->elevateRiskForSafetyFlags($riskFromScore, $riskFlags);

        $notifyCounselors = $riskLevel === 'high'
            || $riskLevel === 'critical'
            || in_array('unsafe_environment', $riskFlags, true)
            || in_array('harm_ideation', $riskFlags, true)
            || in_array('urgent_support_requested', $riskFlags, true);

        $focusAreas = $this->deriveFocusAreas($categoryScores);
        $counselorSummary = $this->buildCounselorSummary($categoryScores, $riskFlags, $focusAreas, $riskLevel);

        return [
            'total_score' => $composite,
            'category_scores' => $categoryScores,
            'risk_level' => $riskLevel,
            'notify_counselors' => $notifyCounselors,
            'counselor_summary' => $counselorSummary,
            'focus_areas' => $focusAreas,
            'risk_flags' => array_values(array_unique($riskFlags)),
            'scoring_model' => 'pre_counselling_v1',
        ];
    }

    /**
     * @param  array<string, mixed>  $riskFlags  Mutated by reference via caller pattern — passed by ref in PHP needs &
     */
    private function scorePreCounsellingItem(mixed $response, array $question, array &$riskFlags): ?float
    {
        $type = $question['type'] ?? 'scale';
        $polarity = $question['scoring']['polarity'] ?? 'negative';
        $id = $question['id'] ?? '';

        if ($polarity === 'none') {
            return null;
        }

        return match ($type) {
            'frequency_5' => $this->distressFromFrequency($response, $polarity),
            'single_choice' => $this->distressFromSingleChoice($response, $question),
            'multi_select' => $this->distressFromMultiSelect($response, $question),
            'scale_1_5' => $this->distressFromScale((int) $response, 5, $polarity),
            'scale_1_10' => $this->distressFromScale((int) $response, 10, $polarity),
            'scale' => $this->distressFromScale((int) $response, 5, $polarity),
            'multiple_choice' => $this->distressFromLegacyMultipleChoice($response, $question),
            'yes_no' => $this->distressFromYesNo($response, $polarity, (string) $id, $riskFlags),
            'text', 'textarea' => null,
            default => $this->distressFromScale((int) $response, 5, $polarity),
        };
    }

    private function distressFromFrequency(mixed $response, string $polarity): float
    {
        $v = strtolower(trim((string) $response));

        // Numeric 1–5 inputs (e.g. from the frontend FREQ_OPTIONS "1"–"5" converted
        // by normalizeResponses): map to the equivalent string label so the score
        // is correct instead of falling to the default 45.
        if (is_numeric($v) && (float) $v >= 1 && (float) $v <= 5) {
            $v = match ((int) round((float) $v)) {
                1 => 'never',
                2 => 'rarely',
                3 => 'sometimes',
                4 => 'often',
                5 => 'always',
                default => 'sometimes',
            };
        }

        $base = match ($v) {
            'never' => 0.0,
            'rarely' => 25.0,
            'sometimes' => 50.0,
            'often' => 75.0,
            'always' => 100.0,
            default => 45.0,
        };

        return $polarity === 'positive' ? (100.0 - $base) : $base;
    }

    private function distressFromScale(int $value, int $max, string $polarity): float
    {
        $value = max(1, min($max, $value));
        $norm = (($value - 1) / max(1, $max - 1)) * 100.0;

        return $polarity === 'positive' ? (100.0 - $norm) : $norm;
    }

    /** @param  array<string, mixed>  $question */
    private function distressFromSingleChoice(mixed $response, array $question): float
    {
        $response = (string) $response;
        $options = $question['options'] ?? [];
        foreach ($options as $opt) {
            if (($opt['value'] ?? null) === $response) {
                return (float) ($opt['severity'] ?? 30);
            }
        }

        return 30.0;
    }

    /** @param  array<string, mixed>  $question */
    private function distressFromMultiSelect(mixed $response, array $question): float
    {
        if (! is_array($response)) {
            return 20.0;
        }
        /** @var list<string> $selected */
        $selected = array_map('strval', $response);

        if ($selected === []) {
            return 15.0;
        }

        if (in_array('none_above', $selected, true)) {
            return 12.0;
        }

        $options = $question['options'] ?? [];
        $weights = [];
        foreach ($options as $opt) {
            $val = (string) ($opt['value'] ?? '');
            if (! in_array($val, $selected, true)) {
                continue;
            }
            $weights[] = isset($opt['weight']) ? (float) $opt['weight'] : 30.0;
        }

        if ($weights === []) {
            return 25.0;
        }

        return max($weights);
    }

    /** @param  array<string, mixed>  $question */
    private function distressFromLegacyMultipleChoice(mixed $response, array $question): float
    {
        $options = $question['options'] ?? [];
        foreach ($options as $index => $option) {
            if (($option['value'] ?? null) === $response) {
                $raw = $option['score'] ?? ($index + 1);

                return round(((int) $raw) / 5 * 100);
            }
        }

        return 20.0;
    }

    /**
     * @param  array<string, mixed>  $riskFlags
     */
    private function distressFromYesNo(mixed $response, string $polarity, string $id, array &$riskFlags): float
    {
        $yes = strtolower((string) $response) === 'yes';

        if ($polarity === 'risk_screen') {
            if ($id === 'risk_feel_safe') {
                if (! $yes) {
                    $riskFlags[] = 'unsafe_environment';

                    return 100.0;
                }

                return 0.0;
            }

            if ($id === 'risk_thoughts_harm') {
                if ($yes) {
                    $riskFlags[] = 'harm_ideation';

                    return 100.0;
                }

                return 0.0;
            }

            if ($id === 'risk_want_urgent') {
                if ($yes) {
                    $riskFlags[] = 'urgent_support_requested';

                    return 100.0;
                }

                return 0.0;
            }
        }

        if ($polarity === 'positive') {
            return $yes ? 20.0 : 70.0;
        }

        return $yes ? 75.0 : 20.0;
    }

    /** @param  array<string, int>  $categoryScores */
    private function deriveFocusAreas(array $categoryScores): array
    {
        $threshold = 52;
        $labels = [
            // Pre-counselling questionnaire categories
            'emotional_distress' => 'Emotional regulation & mood',
            'cognitive_patterns' => 'Worries, concentration, self-criticism',
            'behavioural_patterns' => 'Avoidance & reactions',
            'functional_impact' => 'Sleep, energy, study & relationships',
            'stress_load' => 'Stress sources (academic, financial, relational)',
            'social_support' => 'Connection & support',
            'self_resources' => 'Self-compassion & sense of agency',
            'coping' => 'Coping strategies & support networks',
            'context' => 'Study load & life context',
            // University question bank categories
            'mood' => 'Low mood, motivation & emotional regulation',
            'anxiety' => 'Anxiety, worry & overwhelm management',
            'academic' => 'Academic pressure & study management',
            'sleep' => 'Sleep quality & energy recovery',
            'social' => 'Social connection & campus belonging',
            'school' => 'Study engagement & academic confidence',
            'campus_life' => 'Student life pressures (financial, adjustment)',
            'identity' => 'Self-confidence, purpose & identity',
            'physical' => 'Physical health & self-care',
        ];

        $areas = [];
        foreach ($categoryScores as $cat => $score) {
            if ($score >= $threshold && isset($labels[$cat])) {
                $areas[] = $labels[$cat];
            }
        }

        return $areas;
    }

    /**
     * @param  array<string, int>  $categoryScores
     * @param  list<string>  $riskFlags
     */
    private function buildCounselorSummary(array $categoryScores, array $riskFlags, array $focusAreas, string $riskLevel): string
    {
        $parts = [];

        if ($riskFlags !== []) {
            $flagText = [];
            foreach ($riskFlags as $f) {
                $flagText[] = match ($f) {
                    'unsafe_environment' => 'Student does not currently feel safe in their environment.',
                    'harm_ideation' => 'Student endorsed thoughts of self-harm; follow clinical safety protocols.',
                    'urgent_support_requested' => 'Student requested urgent counsellor outreach.',
                    default => $f,
                };
            }
            $parts[] = implode(' ', $flagText);
        }

        arsort($categoryScores);
        $top = array_slice(array_keys($categoryScores), 0, 3);
        $readable = [];
        foreach ($top as $cat) {
            $score = $categoryScores[$cat] ?? 0;
            if ($score < 45) {
                continue;
            }
            $readable[] = sprintf('%s (%d%% relative index)', str_replace('_', ' ', $cat), $score);
        }
        if ($readable !== []) {
            $parts[] = 'Highest-index domains: '.implode(', ', $readable).'.';
        }

        if ($focusAreas !== []) {
            $parts[] = 'Suggested focus areas for first session: '.implode('; ', $focusAreas).'.';
        }

        $parts[] = sprintf('Automated band: %s (distress composite). This is supportive triage — not a diagnosis.', ucfirst($riskLevel));

        return implode(' ', $parts);
    }

    /** @param  list<string>  $riskFlags */
    private function elevateRiskForSafetyFlags(string $baseRisk, array $riskFlags): string
    {
        if (in_array('harm_ideation', $riskFlags, true) || in_array('unsafe_environment', $riskFlags, true)) {
            return 'critical';
        }

        if (in_array('urgent_support_requested', $riskFlags, true)) {
            return $this->maxRisk($baseRisk, 'high');
        }

        return $baseRisk;
    }

    private function maxRisk(string $a, string $b): string
    {
        $order = ['low' => 0, 'medium' => 1, 'high' => 2, 'critical' => 3];

        return ($order[$a] ?? 0) >= ($order[$b] ?? 0) ? $a : $b;
    }

    private function scoreResponse(mixed $response, array $question): int
    {
        $type = $question['type'] ?? 'scale';

        return match ($type) {
            'scale', 'scale_1_5', 'scale_1_10' => max(1, (int) $response),
            'frequency_5' => $this->scoreFrequencyLegacy((string) $response),
            'multiple_choice', 'single_choice' => $this->scoreMultipleChoice($response, $question),
            'yes_no' => strtolower(trim((string) $response)) === 'yes' ? 4 : 1,
            'text', 'textarea' => $this->scoreTextResponse((string) $response),
            default => max(1, is_numeric($response) ? (int) $response : 1),
        };
    }

    /**
     * Maps a frequency-style response (string label or numeric 1–5) to a 1–5
     * integer for the legacy scoring path.
     */
    private function scoreFrequencyLegacy(string $response): int
    {
        $v = strtolower(trim($response));
        if (is_numeric($v)) {
            return max(1, min(5, (int) round((float) $v)));
        }

        return match ($v) {
            'never' => 1,
            'rarely' => 2,
            'sometimes' => 3,
            'often' => 4,
            'always' => 5,
            default => 3,
        };
    }

    private function scoreMultipleChoice($response, array $question): int
    {
        $options = $question['options'] ?? [];
        foreach ($options as $index => $option) {
            if ($option['value'] === $response) {
                return $option['score'] ?? ($index + 1);
            }
        }

        return 1;
    }

    private function scoreTextResponse(string $response): int
    {
        $length = strlen(trim($response));
        if ($length === 0) {
            return 1;
        }
        if ($length < 20) {
            return 2;
        }
        if ($length < 50) {
            return 3;
        }
        if ($length < 100) {
            return 4;
        }

        return 5;
    }

    private function determineRiskLevel(int $score): string
    {
        if ($score <= self::RISK_THRESHOLDS['low']) {
            return 'low';
        }
        if ($score <= self::RISK_THRESHOLDS['medium']) {
            return 'medium';
        }
        if ($score <= self::RISK_THRESHOLDS['high']) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * @param  array<string, int>  $categoryScores
     */
    public function generateRecommendations(string $riskLevel, array $categoryScores): array
    {
        $recommendations = [];

        $baseRecommendations = [
            'low' => [
                'primary' => 'Continue maintaining your current wellness practices.',
                'actions' => [
                    'Practice regular self-care activities',
                    'Maintain a consistent sleep schedule',
                    'Engage in physical activity 3-4 times per week',
                    'Connect with friends and family regularly',
                ],
            ],
            'medium' => [
                'primary' => 'Consider scheduling a counseling session to discuss your concerns.',
                'actions' => [
                    'Schedule an appointment with a counselor',
                    'Practice stress management techniques (meditation, deep breathing)',
                    'Maintain a journal to track your emotions',
                    'Increase physical activity to 4-5 times per week',
                    'Limit caffeine and alcohol intake',
                ],
            ],
            'high' => [
                'primary' => 'It is recommended that you schedule an urgent counseling appointment.',
                'actions' => [
                    'Contact the counseling center immediately',
                    'Reach out to a trusted friend or family member',
                    'Implement daily stress management practices',
                    'Consider speaking with your academic advisor',
                    'Explore campus mental health resources',
                ],
            ],
            'critical' => [
                'primary' => 'Please contact the counseling center or crisis hotline immediately.',
                'actions' => [
                    'Call the crisis hotline: 988 (US) or your local emergency number',
                    'Contact the counseling center immediately',
                    'Reach out to a trusted person for support',
                    'Do not isolate yourself',
                    'Consider emergency mental health services if needed',
                ],
            ],
        ];

        $recommendations['primary'] = $baseRecommendations[$riskLevel]['primary'] ?? '';
        $recommendations['actions'] = $baseRecommendations[$riskLevel]['actions'] ?? [];

        foreach ($categoryScores as $category => $score) {
            if ($score > 60) {
                $recommendations['category_alerts'][$category] = $this->getCategoryAlert($category, $score);
            }
        }

        return $recommendations;
    }

    private function getCategoryAlert(string $category, int $score): string
    {
        $alerts = [
            // Legacy questionnaire categories
            'anxiety' => 'High anxiety levels detected. Consider anxiety management techniques or speaking with a counselor.',
            'depression' => 'Signs of depression detected. Professional support is recommended.',
            'stress' => 'High stress levels detected. Implement stress reduction strategies.',
            'academic' => 'Academic pressure is elevated. Consider meeting your academic advisor or counselor to manage workload.',
            'social' => 'Social challenges detected. Connecting with peers, clubs or campus support can help.',
            'sleep' => 'Sleep disruption detected. Establish a consistent wind-down routine and limit screens before bed.',
            'substance' => 'Substance use concerns detected. Seek professional support.',
            // Pre-counselling questionnaire categories
            'emotional_distress' => 'Elevated emotional distress noted. Prioritise emotional safety and pacing in session.',
            'cognitive_patterns' => 'Persistent worry or concentration difficulty flagged. Consider CBT-informed strategies.',
            'behavioural_patterns' => 'Avoidance or withdrawal patterns noted. Gentle behavioural activation may help.',
            'functional_impact' => 'Day-to-day functioning appears affected (sleep, energy, relationships, or study).',
            'stress_load' => 'Multiple stress sources are intense. Discuss load management and boundaries.',
            'social_support' => 'Support or belonging may be strained. Explore connection resources on campus.',
            'self_resources' => 'Self-compassion or sense of agency may be low; validate strengths openly in session.',
            'coping' => 'Review coping strategies together; build on helpful ones and reduce avoidance.',
            'context' => 'Contextual load factors may need practical planning alongside emotional support.',
            // University question bank categories
            'mood' => 'Low mood or loss of motivation detected. This may be depression-adjacent; prompt counselor follow-up.',
            'campus_life' => 'Student life pressures (finances, adjustment, homesickness) are impacting wellbeing.',
            'identity' => 'Low confidence or unclear sense of purpose noted. Explore identity and values in session.',
            'physical' => 'Physical health and self-care habits may be neglected. Encourage basic routine restoration.',
            'school' => 'Study engagement or concentration difficulties detected. Academic support may help.',
        ];

        return $alerts[$category] ?? 'This area needs attention. Consider speaking with a counselor.';
    }
}
