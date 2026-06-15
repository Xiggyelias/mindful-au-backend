<?php

namespace App\Services;

use App\Models\AiDiagnostic;
use App\Models\CounselingSession;
use App\Models\User;
use App\Support\AnalyticsCache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIDiagnosticService
{
    private const DEFAULT_PROVIDER_TIMEOUT_SECONDS = 8;

    private const DEFAULT_PROVIDER_CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly MentalHealthMlService $mentalHealthMlService
    ) {}

    public function analyzeSession(CounselingSession $session, array $messages): AiDiagnostic
    {
        $conversationText = $this->extractConversationText($messages, (int) $session->student_id);
        $promptContext = $this->mentalHealthMlService->buildPromptSafeStudentContext((int) $session->student_id);
        $localAnalysis = $this->analyzeLocally($conversationText);

        $analysis = $localAnalysis;
        if ($this->externalDiagnosticsEnabled()) {
            $analysis = $this->analyzeWithOpenRouter($conversationText, $promptContext)
                ?? $this->analyzeWithGemini($conversationText, $promptContext)
                ?? $this->analyzeWithEcoBot($conversationText)
                ?? $localAnalysis;
        }

        if (! $analysis) {
            throw new \RuntimeException('AI provider unavailable for session diagnostics.');
        }

        $analysis = $this->mentalHealthMlService->buildHybridDiagnostic(
            $session,
            $messages,
            $analysis,
            $localAnalysis
        );

        $diagnostic = AiDiagnostic::create([
            'student_id' => $session->student_id,
            'session_id' => $session->id,
            'stress_level' => $analysis['stress_level'],
            'anxiety_level' => $analysis['anxiety_level'],
            'depression_level' => $analysis['depression_level'],
            'mood' => $analysis['mood'],
            'risk_level' => $analysis['risk_level'],
            'insights' => $analysis['insights'],
            'recommendations' => $analysis['recommendations'],
        ]);

        AnalyticsCache::clear();

        return $diagnostic;
    }

    public function analyzeCounselorWellness(User $counselor, array $recentSessions): array
    {
        $workload = count($recentSessions);
        $stressIndicators = $this->calculateStressIndicators($recentSessions);

        $prompt = "Analyze counselor wellness based on:
        - Workload: {$workload} sessions
        - Stress indicators: ".json_encode($stressIndicators).'
        
        Provide mood_score (0-100), stress_level (0-100), burnout_index (0-100), and recommendations.';

        $analysis = $this->analyzeCounselorWellnessWithOpenRouter($prompt)
            ?? $this->analyzeCounselorWellnessWithGemini($prompt)
            ?? $this->analyzeCounselorWellnessWithEcoBot($prompt)
            ?? $this->analyzeCounselorWellnessLocally($stressIndicators);

        if (! $analysis) {
            throw new \RuntimeException('AI provider unavailable for counselor wellness.');
        }

        return $analysis;
    }

    private function analyzeWithOpenRouter(string $text, array $context = []): ?array
    {
        $apiKey = config('services.openrouter.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $prompt = $this->buildDiagnosticPrompt($text, $context);
            foreach ($this->openRouterAnalysisModels() as $model) {
                $response = $this->providerHttp()
                    ->withHeaders($this->openRouterHeaders((string) $apiKey))
                    ->post($this->openRouterChatEndpoint(), [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a professional counseling diagnostic assistant. Respond ONLY with valid JSON.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => 1000,
                        'temperature' => 0.3,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $content = $data['choices'][0]['message']['content'] ?? null;
                    if (! $content) {
                        continue;
                    }

                    preg_match('/\{.*\}/s', $content, $matches);
                    if (empty($matches)) {
                        continue;
                    }

                    $analysis = $this->validateAnalysisData(json_decode($matches[0], true));
                    if ($analysis !== null) {
                        return $analysis;
                    }
                }

                Log::warning('OpenRouter diagnostic model failed, trying next configured model.', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logProviderException('OpenRouter diagnostic request failed.', $e);
        }

        return null;
    }

    private function analyzeCounselorWellnessWithOpenRouter(string $prompt): ?array
    {
        $apiKey = config('services.openrouter.api_key');
        if (! $apiKey) {
            return null;
        }

        try {
            $wellnessPrompt = "Analyze counselor wellness and provide a JSON response with:
            - mood_score: integer 0-100
            - stress_level: integer 0-100
            - burnout_index: integer 0-100
            - recommendations: string with actionable advice

            Data: {$prompt}

            Respond ONLY with valid JSON in this format:
            {
                \"mood_score\": 75,
                \"stress_level\": 45,
                \"burnout_index\": 30,
                \"recommendations\": \"...\"
            }";

            foreach ($this->openRouterAnalysisModels() as $model) {
                $response = $this->providerHttp()
                    ->withHeaders($this->openRouterHeaders((string) $apiKey))
                    ->post($this->openRouterChatEndpoint(), [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a professional counselor wellness assistant. Respond ONLY with valid JSON.'],
                            ['role' => 'user', 'content' => $wellnessPrompt],
                        ],
                        'max_tokens' => 1000,
                        'temperature' => 0.3,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $content = $data['choices'][0]['message']['content'] ?? null;
                    if (! $content) {
                        continue;
                    }

                    preg_match('/\{.*\}/s', $content, $matches);
                    if (empty($matches)) {
                        continue;
                    }

                    $analysis = $this->validateCounselorWellnessData(json_decode($matches[0], true));
                    if ($analysis !== null) {
                        return $analysis;
                    }
                }

                Log::warning('OpenRouter counselor wellness model failed, trying next configured model.', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logProviderException('OpenRouter counselor wellness request failed.', $e);
        }

        return null;
    }

    private function analyzeCounselorWellnessWithGemini(string $prompt): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $wellnessPrompt = "Analyze counselor wellness and provide a JSON response with:
            - mood_score: integer 0-100
            - stress_level: integer 0-100
            - burnout_index: integer 0-100
            - recommendations: string with actionable advice

            Data: {$prompt}

            Respond ONLY with valid JSON in this format:
            {
                \"mood_score\": 75,
                \"stress_level\": 45,
                \"burnout_index\": 30,
                \"recommendations\": \"...\"
            }";

            $response = $this->providerHttp()
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey,
                    [
                        'contents' => [['parts' => [['text' => $wellnessPrompt]]]],
                        'system_instruction' => ['parts' => [['text' => 'You are a professional counselor wellness assistant. Respond ONLY with valid JSON.']]],
                    ]
                );

            if ($response->successful()) {
                $content = $response->json();
                $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (! $text) {
                    return null;
                }

                // Extract JSON from response
                preg_match('/\{.*\}/s', $text, $matches);
                if (empty($matches)) {
                    return null;
                }

                $data = json_decode($matches[0], true);

                return $this->validateCounselorWellnessData($data);
            }
        } catch (\Throwable $e) {
            $this->logProviderException('Gemini counselor wellness request failed.', $e);
        }

        return null;
    }

    private function analyzeCounselorWellnessWithEcoBot(string $prompt): ?array
    {
        $endpoint = config('services.ecobot.endpoint');
        $apiKey = config('services.ecobot.api_key');

        if (! $endpoint || ! $apiKey) {
            return null;
        }

        try {
            $response = $this->providerHttp()
                ->withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->post($endpoint.'/analyze', [
                    'text' => $prompt,
                    'type' => 'counselor_wellness',
                ]);

            if ($response->successful()) {
                return $this->validateCounselorWellnessData($response->json());
            }
        } catch (\Throwable $e) {
            $this->logProviderException('EcoBot counselor wellness request failed.', $e);
        }

        return null;
    }

    private function validateCounselorWellnessData(?array $data): ?array
    {
        if (! $data) {
            return null;
        }

        return [
            'mood_score' => isset($data['mood_score']) ? min(100, max(0, (int) $data['mood_score'])) : null,
            'stress_level' => isset($data['stress_level']) ? min(100, max(0, (int) $data['stress_level'])) : null,
            'burnout_index' => isset($data['burnout_index']) ? min(100, max(0, (int) $data['burnout_index'])) : null,
            'recommendations' => $data['recommendations'] ?? null,
        ];
    }

    private function analyzeWithGemini(string $text, array $context = []): ?array
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = $this->providerHttp()
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='.$apiKey,
                    [
                        'contents' => [['parts' => [['text' => $this->buildDiagnosticPrompt($text, $context)]]]],
                        'system_instruction' => ['parts' => [['text' => 'You are a professional counseling diagnostic assistant. Respond ONLY with valid JSON.']]],
                    ]
                );

            if ($response->successful()) {
                $content = $response->json();

                return $this->parseGeminiResponse($content);
            }
        } catch (\Throwable $e) {
            $this->logProviderException('Gemini diagnostic request failed.', $e);
        }

        return null;
    }

    private function analyzeWithEcoBot(string $text): ?array
    {
        $endpoint = config('services.ecobot.endpoint');
        $apiKey = config('services.ecobot.api_key');

        if (! $endpoint || ! $apiKey) {
            return null;
        }

        try {
            $response = $this->providerHttp()
                ->withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->post($endpoint.'/analyze', [
                    'text' => $text,
                    'type' => 'counseling_session',
                ]);

            if ($response->successful()) {
                return $this->parseEcoBotResponse($response->json());
            }
        } catch (\Throwable $e) {
            $this->logProviderException('EcoBot diagnostic request failed.', $e);
        }

        return null;
    }

    private function buildDiagnosticPrompt(string $conversation, array $context = []): string
    {
        $contextParts = [];
        if (! empty($context['prompt_summary']) && is_string($context['prompt_summary'])) {
            $contextParts[] = 'Aggregated student context: '.trim($context['prompt_summary']);
        }

        $recommendedActions = array_slice(
            array_values(array_filter($context['recommended_actions'] ?? [], fn ($item) => is_string($item) && trim($item) !== '')),
            0,
            2
        );
        if (! empty($recommendedActions)) {
            $contextParts[] = 'Preferred support directions: '.implode(' ', $recommendedActions);
        }

        $contextBlock = empty($contextParts)
            ? ''
            : "\n\nContext (privacy-safe aggregated features only):\n- ".implode("\n- ", $contextParts);

        return "Analyze this counseling session conversation and provide a JSON response with:
        - stress_level: integer 0-100
        - anxiety_level: integer 0-100
        - depression_level: integer 0-100
        - mood: string (e.g., 'anxious', 'calm', 'sad', 'hopeful')
        - risk_level: 'low', 'medium', 'high', or 'critical'
        - insights: detailed text analysis
        - recommendations: actionable recommendations

        Conversation: {$conversation}{$contextBlock}

        Respond ONLY with valid JSON in this format:
        {
            \"stress_level\": 45,
            \"anxiety_level\": 60,
            \"depression_level\": 30,
            \"mood\": \"anxious\",
            \"risk_level\": \"medium\",
            \"insights\": \"...\",
            \"recommendations\": \"...\"
        }";
    }

    private function parseGeminiResponse(array $response): ?array
    {
        try {
            $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! $text) {
                return null;
            }

            // Extract JSON from response
            preg_match('/\{.*\}/s', $text, $matches);
            if (empty($matches)) {
                return null;
            }

            $data = json_decode($matches[0], true);

            return $this->validateAnalysisData($data);
        } catch (\Throwable $e) {
            $this->logProviderException('Gemini response parsing failed.', $e);

            return null;
        }
    }

    private function parseEcoBotResponse(array $response): ?array
    {
        return $this->validateAnalysisData([
            'stress_level' => $response['stress_level'] ?? null,
            'anxiety_level' => $response['anxiety_level'] ?? null,
            'depression_level' => $response['depression_level'] ?? null,
            'mood' => $response['mood'] ?? null,
            'risk_level' => $response['risk_level'] ?? null,
            'insights' => $response['insights'] ?? null,
            'recommendations' => $response['recommendations'] ?? null,
        ]);
    }

    private function extractConversationText(array $messages, ?int $studentId = null): string
    {
        return implode("\n", array_map(function ($msg) use ($studentId) {
            $label = 'User';
            if (isset($msg['sender_id']) && $studentId !== null) {
                $label = (int) $msg['sender_id'] === $studentId ? 'Student' : 'Counselor';
            } elseif (isset($msg['role'])) {
                $label = ucfirst((string) $msg['role']);
            } elseif (isset($msg['sender'])) {
                $label = ucfirst((string) $msg['sender']);
            }

            return $label.': '.($msg['content'] ?? '');
        }, $messages));
    }

    private function calculateStressIndicators(array $sessions): array
    {
        $highRiskCount = 0;
        foreach ($sessions as $session) {
            if (isset($session['risk_level']) && ($session['risk_level'] === 'high' || $session['risk_level'] === 'critical')) {
                $highRiskCount++;
            }
        }

        $totalDuration = 0;
        $sessionCount = 0;
        foreach ($sessions as $session) {
            if (isset($session['started_at']) && isset($session['ended_at'])) {
                $start = is_string($session['started_at']) ? strtotime($session['started_at']) : $session['started_at'];
                $end = is_string($session['ended_at']) ? strtotime($session['ended_at']) : $session['ended_at'];
                if ($start && $end) {
                    $totalDuration += ($end - $start) / 60; // Duration in minutes
                    $sessionCount++;
                }
            }
        }

        return [
            'total_sessions' => count($sessions),
            'high_risk_sessions' => $highRiskCount,
            'avg_session_duration' => $sessionCount > 0 ? $totalDuration / $sessionCount : 0,
        ];
    }

    private function analyzeLocally(string $conversation): array
    {
        $normalized = strtolower($conversation);

        $stressTerms = ['stress', 'overwhelmed', 'pressure', 'burnout', 'deadline', 'panic'];
        $anxietyTerms = ['anxious', 'anxiety', 'worried', 'fear', 'nervous', 'restless'];
        $depressionTerms = ['sad', 'hopeless', 'worthless', 'empty', 'depressed', 'alone', 'lonely'];
        $criticalTerms = ['suicide', 'kill myself', 'end my life', 'self harm', 'hurt myself'];
        $highRiskTerms = ["can't cope", 'cannot cope', 'breakdown', 'no point', 'give up'];

        $stressHits = $this->countKeywordHits($normalized, $stressTerms);
        $anxietyHits = $this->countKeywordHits($normalized, $anxietyTerms);
        $depressionHits = $this->countKeywordHits($normalized, $depressionTerms);
        $criticalHits = $this->countKeywordHits($normalized, $criticalTerms);
        $highRiskHits = $this->countKeywordHits($normalized, $highRiskTerms);

        $stressLevel = $this->clampScore(18 + ($stressHits * 11) + ($highRiskHits * 6));
        $anxietyLevel = $this->clampScore(15 + ($anxietyHits * 12) + ($highRiskHits * 5));
        $depressionLevel = $this->clampScore(14 + ($depressionHits * 12) + ($criticalHits * 8));

        $aggregate = max($stressLevel, $anxietyLevel, $depressionLevel);
        if ($criticalHits > 0 || $aggregate >= 85) {
            $riskLevel = 'critical';
        } elseif ($highRiskHits > 0 || $aggregate >= 70) {
            $riskLevel = 'high';
        } elseif ($aggregate >= 45) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }

        $mood = 'neutral';
        if ($criticalHits > 0) {
            $mood = 'distressed';
        } elseif ($depressionLevel >= max($stressLevel, $anxietyLevel)) {
            $mood = 'low';
        } elseif ($anxietyLevel >= $stressLevel) {
            $mood = 'anxious';
        } elseif ($stressLevel >= 40) {
            $mood = 'overwhelmed';
        }

        $insights = sprintf(
            'Local diagnostic fallback detected %d stress cues, %d anxiety cues, %d low-mood cues, %d high-risk cues, and %d critical cues in this session.',
            $stressHits,
            $anxietyHits,
            $depressionHits,
            $highRiskHits,
            $criticalHits
        );

        $recommendations = match ($riskLevel) {
            'critical' => 'Activate crisis protocol immediately, contact emergency support, and keep direct counselor follow-up active.',
            'high' => 'Schedule urgent counselor follow-up within 24 hours and create a written safety and support plan.',
            'medium' => 'Plan near-term counseling check-ins, reinforce coping routines, and monitor symptom trend changes.',
            default => 'Continue routine support, encourage healthy coping habits, and keep periodic monitoring.',
        };

        return [
            'stress_level' => $stressLevel,
            'anxiety_level' => $anxietyLevel,
            'depression_level' => $depressionLevel,
            'mood' => $mood,
            'risk_level' => $riskLevel,
            'insights' => $insights,
            'recommendations' => $recommendations,
        ];
    }

    private function analyzeCounselorWellnessLocally(array $stressIndicators): array
    {
        $totalSessions = (int) ($stressIndicators['total_sessions'] ?? 0);
        $highRiskSessions = (int) ($stressIndicators['high_risk_sessions'] ?? 0);
        $avgDuration = (float) ($stressIndicators['avg_session_duration'] ?? 0);

        $stressLevel = $this->clampScore(($totalSessions * 6.5) + ($highRiskSessions * 14) + ($avgDuration * 0.22));
        $burnoutIndex = $this->clampScore(($stressLevel * 0.65) + ($highRiskSessions * 10) + max(0, $totalSessions - 8) * 3);
        $moodScore = $this->clampScore(100 - (($stressLevel * 0.55) + ($burnoutIndex * 0.35)));

        $recommendations = match (true) {
            $burnoutIndex >= 75 => 'Burnout risk is elevated. Reduce non-urgent load this week, schedule peer debriefing, and protect recovery blocks after sessions.',
            $stressLevel >= 60 => 'Stress is moderate to high. Add short recovery breaks between sessions and limit context switching on high-risk days.',
            default => 'Wellness trend is stable. Maintain current boundaries, hydration, and short reset breaks.',
        };

        return [
            'mood_score' => $moodScore,
            'stress_level' => $stressLevel,
            'burnout_index' => $burnoutIndex,
            'recommendations' => $recommendations,
        ];
    }

    private function countKeywordHits(string $text, array $terms): int
    {
        $hits = 0;
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($text, strtolower((string) $term))) {
                $hits++;
            }
        }

        return $hits;
    }

    private function clampScore(float|int $value): int
    {
        return (int) max(0, min(100, round($value)));
    }

    private function validateAnalysisData(?array $data): ?array
    {
        if (! $data) {
            return null;
        }

        return [
            'stress_level' => isset($data['stress_level']) ? min(100, max(0, (int) $data['stress_level'])) : null,
            'anxiety_level' => isset($data['anxiety_level']) ? min(100, max(0, (int) $data['anxiety_level'])) : null,
            'depression_level' => isset($data['depression_level']) ? min(100, max(0, (int) $data['depression_level'])) : null,
            'mood' => $data['mood'] ?? null,
            'risk_level' => in_array($data['risk_level'] ?? null, ['low', 'medium', 'high', 'critical'])
                ? $data['risk_level']
                : 'low',
            'insights' => $data['insights'] ?? null,
            'recommendations' => $data['recommendations'] ?? null,
        ];
    }

    private function openRouterAnalysisModels(): array
    {
        return array_values(array_unique(array_filter([
            OpenRouterService::configuredCoreModel(),
            OpenRouterService::configuredHeavyAnalysisModel(),
            OpenRouterService::configuredSpeedModel(),
        ], static fn ($model): bool => trim((string) $model) !== '')));
    }

    private function openRouterHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('services.openrouter.site_url', 'https://mindful-au.local'),
            'X-Title' => config('services.openrouter.site_name', 'Mindful AU'),
        ];
    }

    private function openRouterChatEndpoint(): string
    {
        return rtrim(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/').'/chat/completions';
    }

    private function logProviderException(string $message, \Throwable $e): void
    {
        Log::error($message, [
            'exception' => $e::class,
        ]);
    }

    private function externalDiagnosticsEnabled(): bool
    {
        return (bool) config('services.ai.external_diagnostics_enabled', false);
    }

    private function providerHttp(): PendingRequest
    {
        return Http::connectTimeout($this->providerConnectTimeoutSeconds())
            ->timeout($this->providerTimeoutSeconds());
    }

    private function providerTimeoutSeconds(): int
    {
        $timeout = (int) config('services.ai.provider_timeout_seconds', self::DEFAULT_PROVIDER_TIMEOUT_SECONDS);

        return max(3, min(30, $timeout));
    }

    private function providerConnectTimeoutSeconds(): int
    {
        $timeout = (int) config('services.ai.provider_connect_timeout_seconds', self::DEFAULT_PROVIDER_CONNECT_TIMEOUT_SECONDS);

        return max(1, min(10, $timeout));
    }
}
