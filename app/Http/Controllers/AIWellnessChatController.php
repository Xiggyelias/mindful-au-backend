<?php

namespace App\Http\Controllers;

use App\Services\MentalHealthMlService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\SystemSettings;
use App\Services\WebPushService;
use App\Services\OpenRouterService;
use App\Models\User;
use App\Models\Notification;

class AIWellnessChatController extends Controller
{
    private const WELLNESS_MODEL = 'wellness-assistant-v1';
    private const CONTEXT_WINDOW_MESSAGES = 10;
    private const HISTORY_LIMIT_MESSAGES = 100;
    private const DEFAULT_PROVIDER_TIMEOUT_SECONDS = 8;
    private const DEFAULT_PROVIDER_CONNECT_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly MentalHealthMlService $mentalHealthMlService,
        private readonly WebPushService $webPush
    ) {
    }

    public function chat(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:4000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->hasDisallowedContent((string) $value)) {
                        $fail('Message contains unsupported content.');
                    }
                },
            ],
            'history' => 'sometimes|array|max:50',
            'history.*.role' => 'required_with:history|in:user,assistant,system',
            'history.*.content' => [
                'required_with:history',
                'string',
                'max:4000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->hasDisallowedContent((string) $value)) {
                        $fail("{$attribute} contains unsupported content.");
                    }
                },
            ],
            'conversation_id' => 'sometimes|integer',
        ]);

        $user = $request->user();
        $message = $this->sanitizeUserText(trim($validated['message']));
        if ($message === '') {
            return response()->json([
                'message' => 'Message cannot be empty.',
            ], 422);
        }

        $requestedConversationId = isset($validated['conversation_id'])
            ? (int) $validated['conversation_id']
            : null;

        $conversationResolution = $this->resolveConversation(
            (int) $user->id,
            $requestedConversationId,
            $message
        );
        $conversation = $conversationResolution['conversation'];
        $created = $conversationResolution['created'];

        if (!$conversation) {
            return response()->json([
                'message' => 'Conversation not found.',
            ], 404);
        }

        $historyMessages = DB::table('chat_messages')
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(self::CONTEXT_WINDOW_MESSAGES)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(function ($row) {
                return [
                    'role' => $row->role,
                    'content' => $this->sanitizeUserText((string) $row->content),
                ];
            })
            ->all();

        $normalizedMessage = $this->normalizeIntentText($message);
        $conversationTopic = $this->resolveConversationTopic($normalizedMessage, $historyMessages);
        $requiresImmediateHelp = $conversationTopic === 'crisis';
        $providerMode = 'local_fallback';
        $providerName = 'offline_companion';
        $promptSafeContext = $this->mentalHealthMlService->buildPromptSafeStudentContext($user);
        $studentMlInsights = $this->mentalHealthMlService->buildStudentMlInsights($user);

        // Build conversation context
        $systemPrompt = "You are a warm mental health support companion for university students. Listen first—do not fix, diagnose, or lecture.

RULES:
- Be warm, simple, and non-judgmental.
- Never sound robotic, formal, or overly therapist-like.
- Avoid long explanations unless the student asks for more.
- Understand first, then offer gentle guidance.
- Use short sentences and natural language. Use contractions (I'm, you're, it's).
- Acknowledge their emotions before giving any advice.
- Never overwhelm with multiple suggestions at once—one gentle thought or question at a time.
- No bullet lists or numbered steps unless they explicitly ask for strategies.
- Never say \"As an AI...\" or dump generic advice templates.
- Respond to what they actually said, not a script.

STYLE:
- Sound like a caring friend texting back: \"That sounds really heavy. I get why you feel that way.\"
- NOT like: \"I am sorry to hear that you are experiencing distress.\"

END GOAL:
Make them feel heard, safe, and not judged.

CRITICAL:
- Never provide medical diagnoses or treatment advice.
- If they mention suicide or self-harm, give immediate safety guidance to contact emergency services, a counselor, or a trusted person.
- Use mindfulness and CBT insights naturally in conversation, never as clinical exercises.";

        if (!empty($promptSafeContext['prompt_summary']) && is_string($promptSafeContext['prompt_summary'])) {
            $systemPrompt .= "\n- Internal privacy-safe context: {$promptSafeContext['prompt_summary']}";
        }

        $systemPrompt .= "\n- Optimize for low-bandwidth delivery with short paragraphs and no unnecessary filler.";

        if ($requiresImmediateHelp) {
            $response = $this->isFollowUpPrompt($normalizedMessage)
                ? $this->buildFollowUpFallbackResponse('crisis')
                : $this->buildCrisisResponse($normalizedMessage);
            $providerMode = 'safety_guardrail';
            $providerName = 'crisis_guardrail';
            Log::warning('AI wellness chat crisis signal detected.', [
                'user_id' => (int) $user->id,
                'conversation_id' => (int) $conversation->id,
            ]);

            $crisisWords = $this->mentalHealthMlService->detectCrisisInText($message);
            if (empty($crisisWords)) {
                $crisisWords = ['high-risk indicators'];
            }
            try {
                $this->triggerAiCrisisAlert($user, $crisisWords, (int) $conversation->id);
            } catch (\Throwable $e) {
                Log::error('Failed to trigger AI crisis alert: ' . $e->getMessage());
            }
        } else {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$historyMessages,
                ['role' => 'user', 'content' => $message]
            ];

            // Try providers in order, then fall back to local deterministic guidance.
            $response = null;
            foreach ($this->availableAiProviders() as $provider) {
                $candidate = $this->{$provider['method']}($messages);
                if (is_string($candidate) && trim($candidate) !== '') {
                    $response = $candidate;
                    $providerMode = 'external';
                    $providerName = $provider['name'];
                    break;
                }
            }

            if (!is_string($response) || trim($response) === '') {
                $response = $this->buildLocalWellnessFallbackResponse($message, $historyMessages);
                Log::info('AI wellness chat provider fallback used.');
            }
        }
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        if ($latencyMs > 3000) {
            Log::warning('AI wellness chat slow response budget exceeded.', [
                'latency_ms' => $latencyMs,
                'provider_mode' => $providerMode,
                'provider_name' => $providerName,
            ]);
        }

        $mlSignals = [
            'model_version' => MentalHealthMlService::MODEL_VERSION,
            'conversation_topic' => $conversationTopic,
            'focus_area' => $studentMlInsights['focus_area'] ?? null,
            'risk_forecast' => $studentMlInsights['risk_forecast'] ?? null,
            'trend' => $studentMlInsights['trend'] ?? null,
            'dominant_topics' => $studentMlInsights['dominant_topics'] ?? [],
            'recommended_actions' => array_slice($studentMlInsights['recommended_actions'] ?? [], 0, 2),
            'low_bandwidth_mode' => true,
        ];

        [$userMessageId, $assistantMessageId] = $this->persistMessages(
            (int) $conversation->id,
            $message,
            $response,
            (bool) $created,
            [
                'conversation_topic' => $conversationTopic ?? 'general',
                'risk_level' => $requiresImmediateHelp ? 'crisis' : 'normal',
                'requires_immediate_help' => $requiresImmediateHelp,
                'ml_signal_snapshot' => $mlSignals,
            ],
            [
                'conversation_topic' => $conversationTopic ?? 'general',
                'risk_level' => $requiresImmediateHelp ? 'crisis' : 'normal',
                'requires_immediate_help' => $requiresImmediateHelp,
                'provider_mode' => $providerMode,
                'provider_name' => $providerName,
                'latency_ms' => $latencyMs,
                'ml_signal_snapshot' => $mlSignals,
            ]
        );

        return response()->json([
            'response' => $response,
            'conversation_id' => (int) $conversation->id,
            'user_message_id' => $userMessageId,
            'assistant_message_id' => $assistantMessageId,
            'risk_level' => $requiresImmediateHelp ? 'crisis' : 'normal',
            'requires_immediate_help' => $requiresImmediateHelp,
            'show_panic_button' => $requiresImmediateHelp,
            'crisis_hotline' => $requiresImmediateHelp ? $this->resolveCrisisHotline() : null,
            'provider_mode' => $providerMode,
            'provider_name' => $providerName,
            'latency_ms' => $latencyMs,
            'external_ai_configured' => $this->hasConfiguredExternalAiProvider(),
            'ml_signals' => $mlSignals,
        ]);
    }

    public function providerHealthSnapshot(): array
    {
        $providers = $this->configuredAiProviders();
        return [
            'configured_external_providers' => $providers,
            'external_ai_configured' => !empty($providers),
        ];
    }

    public function history(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $conversationId = $request->query('conversation_id');

        $conversation = $this->resolveConversationForHistory(
            $userId,
            $conversationId !== null ? (int) $conversationId : null
        );

        if (!$conversation) {
            return response()->json([
                'conversation' => null,
                'messages' => [],
            ]);
        }

        $messages = DB::table('chat_messages')
            ->where('conversation_id', $conversation->id)
            ->orderBy('id', 'asc')
            ->limit(self::HISTORY_LIMIT_MESSAGES)
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'role' => $row->role,
                    'content' => $row->content,
                    'created_at' => $row->created_at,
                ];
            })
            ->values();

        return response()->json([
            'conversation' => [
                'id' => (int) $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * @return array{conversation: object|null, created: bool}
     */
    private function resolveConversation(int $userId, ?int $requestedConversationId, string $initialMessage): array
    {
        if ($requestedConversationId !== null) {
            $conversation = $this->resolveConversationForHistory($userId, $requestedConversationId);
            if (!$conversation) {
                return [
                    'conversation' => null,
                    'created' => false,
                ];
            }

            return [
                'conversation' => $conversation,
                'created' => false,
            ];
        }

        $conversation = $this->resolveConversationForHistory($userId, null);
        if ($conversation) {
            return [
                'conversation' => $conversation,
                'created' => false,
            ];
        }

        $now = now();
        $conversationId = DB::table('chat_conversations')->insertGetId([
            'user_id' => $userId,
            'title' => Str::limit($initialMessage, 80),
            'model' => self::WELLNESS_MODEL,
            'is_active' => true,
            'last_message_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $conversation = DB::table('chat_conversations')
            ->where('id', $conversationId)
            ->first();

        return [
            'conversation' => $conversation,
            'created' => true,
        ];
    }

    private function resolveConversationForHistory(int $userId, ?int $conversationId): ?object
    {
        $query = DB::table('chat_conversations')
            ->where('user_id', $userId)
            ->where('model', self::WELLNESS_MODEL);

        if ($conversationId !== null) {
            return $query->where('id', $conversationId)->first();
        }

        return $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    private function persistMessages(
        int $conversationId,
        string $userMessage,
        string $assistantMessage,
        bool $isNewConversation,
        array $userMetadata = [],
        array $assistantMetadata = []
    ): array
    {
        $now = now();

        $userMessageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->persistMessageMetadata($userMessageId, $userMetadata);

        $assistantMessageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $assistantMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->persistMessageMetadata($assistantMessageId, $assistantMetadata);

        $updates = [
            'last_message_at' => $now,
            'updated_at' => $now,
        ];

        if ($isNewConversation) {
            $updates['title'] = Str::limit($userMessage, 80);
        }

        DB::table('chat_conversations')
            ->where('id', $conversationId)
            ->update($updates);

        return [$userMessageId, $assistantMessageId];
    }

    private function persistMessageMetadata(int $messageId, array $metadata): void
    {
        if ($messageId <= 0 || empty($metadata)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key) || trim($key) === '' || $value === null) {
                continue;
            }

            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_int($value)) {
                $type = 'integer';
            } elseif (is_float($value)) {
                $type = 'decimal';
            } elseif (is_array($value) || is_object($value)) {
                $type = 'json';
            }

            $rows[] = [
                'message_id' => $messageId,
                'key' => trim($key),
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
                'type' => $type,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('message_metadata')->upsert(
                $rows,
                ['message_id', 'key'],
                ['value', 'type', 'updated_at']
            );
        }
    }

    private function tryOpenRouter(array $messages): ?string
    {
        $apiKey = config('services.openrouter.api_key');
        
        if (!$apiKey) {
            return null;
        }

        try {
            $payload = [
                'model' => OpenRouterService::configuredChatModel(),
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.85,
            ];

            $baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

            $response = $this->providerHttp()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('services.openrouter.site_url', 'https://mindful-au.local'),
                    'X-Title' => config('services.openrouter.site_name', 'Mindful AU'),
                ])
                ->post(rtrim($baseUrl, '/') . '/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }
            Log::warning('OpenRouter API request failed.', [
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::warning('OpenRouter API request error.', [
                'exception' => $e::class,
            ]);
        }

        return null;
    }

    private function tryGemini(array $messages): ?string
    {
        $apiKey = config('services.gemini.api_key');

        if (!$apiKey) {
            return null;
        }

        $configuredModel = trim((string) config('services.gemini.model', 'gemini-1.5-flash'));
        $candidateModels = array_values(array_unique(array_filter([
            $configuredModel,
            'gemini-2.5-flash',
            'gemini-1.5-flash',
        ], static fn ($value): bool => trim((string) $value) !== '')));

        try {
            // Convert messages format for Gemini
            $geminiMessages = [];
            $systemInstructions = [];
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    if (!empty($msg['content'])) {
                        $systemInstructions[] = $msg['content'];
                    }
                    continue;
                }
                $geminiMessages[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg['content']]]
                ];
            }

            if (empty($geminiMessages)) {
                return null;
            }

            $payload = [
                'contents' => $geminiMessages,
            ];

            if (!empty($systemInstructions)) {
                $payload['system_instruction'] = [
                    'parts' => [[
                        'text' => implode("\n\n", $systemInstructions),
                    ]],
                ];
            }

            foreach ($candidateModels as $model) {
                $endpoint = sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                    rawurlencode($model),
                    $apiKey
                );

                $response = $this->providerHttp()->post($endpoint, $payload);

                if ($response->successful()) {
                    $content = $response->json();
                    $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text) {
                        return $text;
                    }

                    Log::warning('Gemini API response missing text payload, attempting fallback model.', [
                        'model' => $model,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $status = $response->status();
                $retryableStatuses = [404, 429, 500, 502, 503, 504];

                if (in_array($status, $retryableStatuses, true)) {
                    Log::warning('Gemini model request failed with retryable status, attempting fallback model.', [
                        'model' => $model,
                        'status' => $status,
                    ]);
                    continue;
                }

                Log::warning('Gemini API request failed with non-retryable status.', [
                    'model' => $model,
                    'status' => $status,
                ]);
                break;
            }
        } catch (\Exception $e) {
            Log::error('Gemini API error.', [
                'exception' => $e::class,
            ]);
        }

        return null;
    }

    private function buildLocalWellnessFallbackResponse(string $message, array $historyMessages = []): string
    {
        $normalized = $this->normalizeIntentText($message);
        $conversationTopic = $this->resolveConversationTopic($normalized, $historyMessages);

        if ($conversationTopic === 'crisis') {
            return 'Your safety comes first. Please contact emergency services or a trusted counselor right now. If you’re alone, move toward another person and tell them clearly you need support now.';
        }

        if ($conversationTopic === 'physical_health') {
            return 'That sounds rough. Rest if you can. What symptom is bothering you most? If anything feels severe or you’re struggling to breathe, please reach out to campus health or emergency support right away.';
        }

        $socialResponse = $this->buildSocialConversationResponse($normalized, $historyMessages);
        if ($socialResponse !== null) {
            return $socialResponse;
        }

        if ($this->isFollowUpPrompt($normalized)) {
            return $this->buildFollowUpFallbackResponse($conversationTopic);
        }

        if ($conversationTopic === 'anxiety') {
            return 'That sounds really heavy, and it makes sense you\'d feel on edge. What\'s been weighing on you most today?';
        }

        if ($conversationTopic === 'study') {
            return 'Academic pressure can pile up fast. What\'s the one thing on your plate that feels hardest right now?';
        }

        if ($conversationTopic === 'sleep') {
            return 'When sleep is off, everything feels harder. Has tonight been the rough part, or has this been going on a while?';
        }

        if ($conversationTopic === 'sadness') {
            return 'That sounds really heavy. I get why you\'d feel low right now. Does it feel more like sadness, loneliness, or just being worn out?';
        }

        if ($conversationTopic === 'relationships') {
            return 'Relationship stuff can hit hard. What part of it is sitting with you most right now?';
        }

        if ($conversationTopic === 'family') {
            return 'Family pressure can feel really personal. What happened—if you want to share?';
        }

        if ($conversationTopic === 'financial') {
            return 'Money stress can make everything feel urgent. What\'s the thing weighing on you most right now?';
        }

        if ($conversationTopic === 'safety') {
            return 'That sounds scary, and you shouldn\'t have to deal with it alone. Your safety matters. Can you get to a safer person or place right now? If someone is threatening you, reach out to campus security, a counselor, or someone you trust as soon as you can.';
        }

        if (str_word_count($normalized) <= 4) {
            return 'I’m here. Tell me a little more about what’s happening for you right now.';
        }

        return 'I’m here with you. What feels most difficult right now?';
    }

    private function normalizeIntentText(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized);
        $normalized = is_string($normalized) ? trim(preg_replace('/\s+/u', ' ', $normalized) ?? '') : '';

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\bi m\b/u', 'i am', $normalized);
        $normalized = preg_replace('/\bim\b/u', 'i am', $normalized);
        $normalized = preg_replace('/^am\b/u', 'i am', $normalized);
        $normalized = preg_replace('/\bu\b/u', 'you', $normalized);
        $normalized = preg_replace('/\bur\b/u', 'your', $normalized);
        $normalized = preg_replace('/\br\b/u', 'are', $normalized);

        return is_string($normalized) ? trim(preg_replace('/\s+/u', ' ', $normalized) ?? '') : '';
    }

    private function matchesExactIntent(string $normalized, array $phrases): bool
    {
        return in_array($normalized, $phrases, true);
    }

    private function resolveConversationTopic(string $normalizedMessage, array $historyMessages): ?string
    {
        $currentTopic = $this->detectTopicFromText($normalizedMessage);
        if ($currentTopic !== null) {
            return $currentTopic;
        }

        for ($i = count($historyMessages) - 1; $i >= 0; $i--) {
            if (($historyMessages[$i]['role'] ?? null) !== 'user') {
                continue;
            }

            $content = $this->normalizeIntentText((string) ($historyMessages[$i]['content'] ?? ''));
            if ($this->isConversationResetCue($content)) {
                return null;
            }
            $topic = $this->detectTopicFromText($content);
            if ($topic !== null) {
                return $topic;
            }
        }

        return null;
    }

    private function detectTopicFromText(string $normalized): ?string
    {
        if ($normalized === '') {
            return null;
        }

        if (
            preg_match('/\b(jump|throw|fall)\s+(off|from)\s+(?:a|the)?\s*(building|bridge|roof|window|balcony|cliff)\b/u', $normalized) === 1
            || preg_match('/\b(overdose|hang myself|cut myself|stab myself|shoot myself|drink poison|take all (?:my )?pills)\b/u', $normalized) === 1
            || preg_match('/\b(i want to die|i wanna die|wish i were dead|dont want to live|do not want to live|don t want to live|end it all|better off without me|no reason to live|cant go on|can t go on|want to disappear forever)\b/u', $normalized) === 1
        ) {
            return 'crisis';
        }

        if (Str::contains($normalized, [
            'suicide',
            'kill myself',
            'end my life',
            'self harm',
            'hurt myself',
            'do not feel safe',
            'not safe',
        ])) {
            return 'crisis';
        }

        if (Str::contains($normalized, ['anxiety', 'anxious', 'panic', 'overwhelmed', 'stress', 'stressed'])) {
            return 'anxiety';
        }

        if (Str::contains($normalized, ['exam', 'deadline', 'assignment', 'study', 'focus', 'concentrate'])) {
            return 'study';
        }

        if (Str::contains($normalized, ['sleep', 'insomnia', 'tired', 'exhausted', 'cannot sleep', 'cant sleep'])) {
            return 'sleep';
        }

        if (Str::contains($normalized, [
            'sick',
            'ill',
            'fever',
            'flu',
            'cough',
            'cold',
            'headache',
            'migraine',
            'nausea',
            'vomit',
            'vomiting',
            'stomach ache',
            'stomachache',
            'diarrhea',
            'body pain',
            'body aches',
        ])) {
            return 'physical_health';
        }

        if (Str::contains($normalized, ['sad', 'depressed', 'down', 'lonely', 'hopeless'])) {
            return 'sadness';
        }

        if (Str::contains($normalized, ['breakup', 'relationship', 'boyfriend', 'girlfriend', 'partner', 'friendship', 'friend'])) {
            return 'relationships';
        }

        if (Str::contains($normalized, ['family', 'mother', 'father', 'parents', 'home', 'sibling', 'guardian'])) {
            return 'family';
        }

        if (Str::contains($normalized, ['money', 'fees', 'tuition', 'rent', 'broke', 'financial', 'debt', 'food'])) {
            return 'financial';
        }

        if (Str::contains($normalized, ['abuse', 'abusive', 'assault', 'harassed', 'threatened', 'unsafe', 'forced'])) {
            return 'safety';
        }

        return null;
    }

    private function isFollowUpPrompt(string $normalized): bool
    {
        if ($normalized === '') {
            return false;
        }

        if ($this->matchesExactIntent($normalized, [
            'yes',
            'yeah',
            'yep',
            'ok',
            'okay',
            'sure',
            'please',
            'continue',
            'go on',
            'and then',
            'what next',
            'tell me more',
            'what should i do',
            'what should i do first',
            'how do i do that',
            'can you explain',
            'why',
        ])) {
            return true;
        }

        return Str::contains($normalized, [
            'tell me more',
            'what should i do',
            'what next',
            'how do i do that',
            'can you explain',
            'what do i do first',
        ]);
    }

    private function buildFollowUpFallbackResponse(?string $topic): string
    {
        return match ($topic) {
            'crisis' => 'Your safety comes first. Can you message or call someone you trust right now and tell them you need support?',
            'anxiety' => 'What\'s the one thought that keeps looping the most?',
            'study' => 'What\'s the smallest piece you could tackle for just 15 minutes?',
            'sleep' => 'Is it more that you can\'t switch off, or that you wake up and can\'t get back to sleep?',
            'physical_health' => 'What symptom is bothering you most right now?',
            'sadness' => 'Does it feel more like loneliness, exhaustion, or just heavy thoughts?',
            'relationships' => 'What happened most recently—if you want to share?',
            'family' => 'What part of it is sitting with you hardest right now?',
            'financial' => 'What\'s the thing that feels most urgent—fees, rent, food, something else?',
            'safety' => 'Are you somewhere safe right now, or do you need to get to a safer place?',
            default => 'What\'s the hardest part in one sentence?',
        };
    }

    private function buildSocialConversationResponse(string $normalized, array $historyMessages): ?string
    {
        $latestAssistantMessage = $this->latestAssistantMessage($historyMessages);
        $assistantAskedAboutUser = $latestAssistantMessage !== null
            && Str::contains($latestAssistantMessage, [
                'how are you feeling',
                'how has your day',
                'what has your day',
                'what s been on your mind',
                'what is on your mind',
                'tell me how you are feeling',
            ]);

        if ($this->isConversationResetCue($normalized)) {
            return 'Hey — I’m here with you. How’s your day been so far?';
        }

        if ($this->matchesExactIntent($normalized, [
            'can you help me',
            'help me',
            'are you there',
            'i need help',
            'i need support',
            'talk to me',
            'i need someone to talk to',
            'i want someone to talk to',
            'i just want to talk',
            'can we talk',
            'lets talk',
            'let s talk',
        ])) {
            return 'Yeah, I’m here. We can talk. You don’t need perfect words. What’s your day felt like so far?';
        }

        if ($this->matchesExactIntent($normalized, [
            'thank you',
            'thanks',
            'thank you so much',
            'okay thanks',
            'ok thanks',
        ])) {
            return 'You’re welcome. I’m still here if you want to keep talking. What’s on your mind now?';
        }

        if ($this->matchesExactIntent($normalized, [
            'how are you',
            'who are you',
            'what can you do',
        ])) {
            return 'I’m here to listen and help you sort through what’s heavy. What’s been on your mind today?';
        }

        if (preg_match('/\b(sick|ill|fever|flu|headache|nausea|vomiting|cough)\b/u', $normalized) === 1) {
            return 'That sounds rough. What symptom is bothering you most? If anything feels severe or you are struggling to breathe, please reach out to a clinic or emergency support right away.';
        }

        if (preg_match('/\b(not good|not okay|not ok|bad|terrible|awful|rough|drained|exhausted|tired)\b/u', $normalized) === 1) {
            return 'Sounds like it\'s been a lot. You don\'t have to carry it alone here. What\'s been feeling hardest today?';
        }

        if (preg_match('/\b(lonely|alone|bored)\b/u', $normalized) === 1) {
            return 'I am here with you. We can just talk for a bit if that helps. What has the day been like for you?';
        }

        $soundsPositive = preg_match('/\b(good|fine|okay|ok|alright|great|better)\b/u', $normalized) === 1
            && preg_match('/\bnot\b/u', $normalized) !== 1;

        if ($soundsPositive) {
            if (preg_match('/\byou\b/u', $normalized) === 1 || $assistantAskedAboutUser) {
                return 'I am glad to hear you are doing okay. Thanks for asking. I am here with you. What has been on your mind today?';
            }

            return 'I am glad to hear that. What has been going well for you today?';
        }

        if (
            $assistantAskedAboutUser
            && (
                preg_match('/\b(idk|i do not know|dont know|don t know|nothing much|same)\b/u', $normalized) === 1
                || str_word_count($normalized) <= 3
            )
        ) {
            return 'That is okay. We do not need to force it. We can just talk. Has today felt calm, heavy, boring, or stressful?';
        }

        return null;
    }

    private function latestAssistantMessage(array $historyMessages): ?string
    {
        for ($i = count($historyMessages) - 1; $i >= 0; $i--) {
            if (($historyMessages[$i]['role'] ?? null) !== 'assistant') {
                continue;
            }

            $content = $this->normalizeIntentText((string) ($historyMessages[$i]['content'] ?? ''));
            if ($content !== '') {
                return $content;
            }
        }

        return null;
    }

    private function isConversationResetCue(string $normalized): bool
    {
        return $this->matchesExactIntent($normalized, [
            'hi',
            'hello',
            'hey',
            'hi there',
            'hello there',
            'good morning',
            'good afternoon',
            'good evening',
            'new topic',
            'start over',
            'can we just talk',
            'let s just talk',
            'lets just talk',
        ]);
    }

    private function buildCrisisResponse(string $normalizedMessage): string
    {
        $firstStep = 'Move away from anything you could use to hurt yourself right now and get closer to another person if you can.';

        if (preg_match('/\b(jump|throw|fall)\s+(off|from)\s+(?:a|the)?\s*(building|bridge|roof|window|balcony|cliff)\b/u', $normalizedMessage) === 1) {
            $firstStep = 'Move away from the edge, roof, balcony, bridge, window, or any high place right now and get closer to another person if you can.';
        }

        $parts = [
            'I am really glad you said this.',
            'I am concerned you may be in immediate danger.',
            $firstStep,
            'Contact emergency services, campus security, a counselor, or a trusted person right now and tell them clearly that you need immediate support.',
            'If you can use the emergency help button in the student dashboard, do that now.',
        ];

        $hotline = $this->resolveCrisisHotline();
        if ($hotline !== null) {
            $parts[] = "Crisis contact: {$hotline}.";
        }

        $parts[] = 'Reply with SAFE if you are with someone now, or ALONE if you are by yourself.';

        return implode(' ', $parts);
    }

    private function resolveCrisisHotline(): ?string
    {
        $hotline = trim(SystemSettings::getString('crisis_hotline', ''));
        return $hotline !== '' ? $hotline : null;
    }

    private function availableAiProviders(): array
    {
        return [
            ['name' => 'openrouter', 'method' => 'tryOpenRouter'],
            ['name' => 'gemini', 'method' => 'tryGemini'],
        ];
    }

    private function configuredAiProviders(): array
    {
        $providers = [];

        if (trim((string) config('services.openrouter.api_key', '')) !== '') {
            $providers[] = 'openrouter';
        }

        if (trim((string) config('services.gemini.api_key', '')) !== '') {
            $providers[] = 'gemini';
        }

        return $providers;
    }

    private function hasConfiguredExternalAiProvider(): bool
    {
        return $this->configuredAiProviders() !== [];
    }

    private function sanitizeUserText(string $value): string
    {
        // Allow tabs/newlines but strip other control characters.
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        return is_string($sanitized) ? trim($sanitized) : trim($value);
    }

    private function hasDisallowedContent(string $value): bool
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            return true;
        }

        return preg_match('/<\s*script\b/i', $value) === 1;
    }

    private function providerHttp(): \Illuminate\Http\Client\PendingRequest
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

    private function triggerAiCrisisAlert(User $student, array $words, int $conversationId): void
    {
        $wordList = implode(', ', $words);
        $studentName = $student->profile?->full_name ?: $student->email;
        
        $activeSession = \App\Models\CounselingSession::where('student_id', $student->id)
            ->where('status', 'active')
            ->first();

        $counselorId = $activeSession?->counselor_id;
        $peerCounselorId = $activeSession?->peer_counselor_id;
        $adminIds = User::whereHas('roles', function($q) {
            $q->where('role', 'admin')->where('approved', true);
        })->pluck('id')->all();

        $recipients = array_unique(array_filter(array_merge(
            $counselorId ? [$counselorId] : [],
            $peerCounselorId ? [$peerCounselorId] : [],
            $adminIds
        )));

        foreach ($recipients as $recipientId) {
            Notification::create([
                'user_id' => $recipientId,
                'title' => '🚨 Crisis Alert: AI Chat Trigger',
                'message' => sprintf(
                    'Student (%s) sent a message in AI Wellness Chat containing high-risk terms: %s. Please review immediately.',
                    $studentName,
                    $wordList
                ),
                'type' => 'error', // High priority
            ]);

            try {
                $this->webPush->sendToUser(
                    (int) $recipientId,
                    'Emergency: AI crisis keywords detected',
                    sprintf(
                        '%s — terms: %s. Review immediately.',
                        $studentName,
                        $wordList
                    ),
                    '/admin/wellness-chat/' . $student->id,
                    [
                        'tag' => 'ai-crisis-' . $student->id . '-' . time(),
                        'urgency' => 'high',
                        'requireInteraction' => true,
                    ]
                );
            } catch (\Throwable $_) {
                // ignore
            }
        }
    }
}
