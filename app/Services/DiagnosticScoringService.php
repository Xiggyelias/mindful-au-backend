<?php

namespace App\Services;

use App\Support\ZimbabweSupportResources;

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

    public function calculateScore(array $responses, array $questionnaire): array
    {
        $categoryScores = [];
        $totalScore = 0;
        $responseCount = 0;

        foreach ($questionnaire['questions'] as $question) {
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

        // Normalize scores
        $normalizedCategoryScores = [];
        foreach ($categoryScores as $category => $data) {
            if ($data['count'] > 0) {
                $normalizedCategoryScores[$category] = round(($data['total'] / $data['count']) / 5 * 100);
            }
        }

        // Calculate average total score
        $averageScore = $responseCount > 0 ? round(($totalScore / $responseCount) / 5 * 100) : 0;

        return [
            'total_score' => $averageScore,
            'category_scores' => $normalizedCategoryScores,
            'risk_level' => $this->determineRiskLevel($averageScore),
        ];
    }

    private function scoreResponse($response, array $question): int
    {
        $type = $question['type'] ?? 'scale';

        return match($type) {
            'scale' => (int)$response,
            'multiple_choice' => $this->scoreMultipleChoice($response, $question),
            'yes_no' => $response === 'yes' ? 4 : 1,
            'text' => $this->scoreTextResponse($response),
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
        if ($length === 0) return 1;
        if ($length < 20) return 2;
        if ($length < 50) return 3;
        if ($length < 100) return 4;
        return 5;
    }

    private function determineRiskLevel(int $score): string
    {
        if ($score <= self::RISK_THRESHOLDS['low']) {
            return 'low';
        } elseif ($score <= self::RISK_THRESHOLDS['medium']) {
            return 'medium';
        } elseif ($score <= self::RISK_THRESHOLDS['high']) {
            return 'high';
        } else {
            return 'critical';
        }
    }

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
                    'Call Zimbabwe emergency services on 112, use 999 from a fixed line, or contact Childline Zimbabwe on 116',
                    'Contact the counseling center immediately',
                    'Reach out to a trusted person for support',
                    'Do not isolate yourself',
                    'Use Friendship Bench Zimbabwe for additional talk support at friendshipbenchzimbabwe.org/need-help',
                ],
            ],
        ];

        $recommendations['primary'] = $baseRecommendations[$riskLevel]['primary'] ?? '';
        $recommendations['actions'] = $baseRecommendations[$riskLevel]['actions'] ?? [];

        if ($riskLevel === 'critical') {
            $recommendations['primary'] .= ' ' . ZimbabweSupportResources::crisisSummaryText();
        }

        // Add category-specific recommendations
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
        ];

        return $alerts[$category] ?? 'This area needs attention. Consider speaking with a counselor.';
    }
}
