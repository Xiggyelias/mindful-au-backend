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
    ];

    private const RISK_THRESHOLDS = [
        'low' => 30,
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

            if (!isset($responses[$questionId])) {
                continue;
            }

            $response = $responses[$questionId];
            $score = $this->scoreResponse($response, $question);
            $weightedScore = $score * $weight;

            if (!isset($categoryScores[$category])) {
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

            if (!array_key_exists($id, $responses)) {
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

            if (!isset($byCategory[$category])) {
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
            if (!isset($categoryScores[$cat])) {
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
        if (!is_array($response)) {
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
            if (!in_array($val, $selected, true)) {
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
                if (!$yes) {
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
            'emotional_distress' => 'Emotional regulation & mood',
            'cognitive_patterns' => 'Worries, concentration, self-criticism',
            'behavioural_patterns' => 'Avoidance & reactions',
            'functional_impact' => 'Sleep, energy, study & relationships',
            'stress_load' => 'Stress sources (academic, financial, relational)',
            'social_support' => 'Connection & support',
            'self_resources' => 'Self-compassion & sense of agency',
            'coping' => 'Coping strategies & prior help-seeking',
            'context' => 'Study load & life context',
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

    private function scoreResponse($response, array $question): int
    {
        $type = $question['type'] ?? 'scale';

        return match ($type) {
            'scale' => (int) $response,
            'multiple_choice' => $this->scoreMultipleChoice($response, $question),
            'yes_no' => $response === 'yes' ? 4 : 1,
            'text' => $this->scoreTextResponse((string) $response),
            default => 1,
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
            'anxiety' => 'High anxiety levels detected. Consider anxiety management techniques or speaking with a counselor.',
            'depression' => 'Signs of depression detected. Professional support is recommended.',
            'stress' => 'High stress levels detected. Implement stress reduction strategies.',
            'academic' => 'Academic concerns detected. Consider meeting with your academic advisor.',
            'social' => 'Social challenges detected. Consider joining campus clubs or social groups.',
            'sleep' => 'Sleep issues detected. Establish a consistent sleep routine.',
            'substance' => 'Substance use concerns detected. Seek professional support.',
            'emotional_distress' => 'Elevated emotional distress noted. Prioritise emotional safety and pacing in session.',
            'cognitive_patterns' => 'Persistent worry or concentration difficulty flagged. Consider CBT-informed strategies.',
            'behavioural_patterns' => 'Avoidance or withdrawal patterns noted. Gentle behavioural activation may help.',
            'functional_impact' => 'Day-to-day functioning appears affected (sleep, energy, relationships, or study).',
            'stress_load' => 'Multiple stress sources are intense. Discuss load management and boundaries.',
            'social_support' => 'Support or belonging may be strained. Explore connection resources.',
            'self_resources' => 'Self-compassion or sense of agency may be low; validate strengths openly.',
            'coping' => 'Review coping strategies together; reduce harmful patterns if present.',
            'context' => 'Contextual load factors may need practical planning alongside emotional support.',
        ];

        return $alerts[$category] ?? 'This area needs attention. Consider speaking with a counselor.';
    }
}
