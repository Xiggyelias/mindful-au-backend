<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIWellnessChatController extends Controller
{
    private const WELLNESS_MODEL = 'wellness-assistant-v1';
    private const CONTEXT_WINDOW_MESSAGES = 10;
    private const HISTORY_LIMIT_MESSAGES = 100;

    public function chat(Request $request): JsonResponse
    {
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

        // Build conversation context
        $systemPrompt = "You are a compassionate and supportive AI wellness assistant for university students. Your role is to:
- Provide emotional support and active listening
- Suggest coping strategies and relaxation techniques
- Offer study tips and stress management advice
- Encourage seeking professional help when appropriate
- Be empathetic, non-judgmental, and supportive

Important guidelines:
- Never provide medical diagnoses or treatment advice
- If someone expresses thoughts of self-harm, gently encourage them to speak with a counselor
- Keep responses concise but warm and helpful
- Use techniques from CBT and mindfulness when appropriate
- Validate feelings before offering suggestions";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$historyMessages,
            ['role' => 'user', 'content' => $message]
        ];

        // Try providers in order, then fall back to local deterministic guidance.
        $response = $this->tryKwaipilot($messages)
            ?? $this->tryOpenRouter($messages)
            ?? $this->tryGemini($messages)
            ?? $this->tryOpenAI($messages);

        if (!$response) {
            $response = $this->buildLocalWellnessFallbackResponse($message, $historyMessages);
            Log::info('AI wellness chat provider fallback used.');
        }

        [$userMessageId, $assistantMessageId] = $this->persistMessages(
            (int) $conversation->id,
            $message,
            $response,
            (bool) $created
        );

        return response()->json([
            'response' => $response,
            'conversation_id' => (int) $conversation->id,
            'user_message_id' => $userMessageId,
            'assistant_message_id' => $assistantMessageId,
        ]);
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

    private function persistMessages(int $conversationId, string $userMessage, string $assistantMessage, bool $isNewConversation): array
    {
        $now = now();

        $userMessageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assistantMessageId = DB::table('chat_messages')->insertGetId([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $assistantMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

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

    private function tryKwaipilot(array $messages): ?string
    {
        $apiKey = config('services.kwaipilot.api_key');
        
        if (!$apiKey) {
            return null;
        }

        try {
            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ];

            $baseUrl = config('services.kwaipilot.base_url', 'https://api.kwaipilot.com/v1');

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(rtrim($baseUrl, '/') . '/chat/completions', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }
            Log::warning('Kwaipilot API request failed.', [
                'status' => $response->status(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Kwaipilot API request error.', [
                'exception' => $e::class,
            ]);
        }

        return null;
    }

    private function tryOpenRouter(array $messages): ?string
    {
        $apiKey = config('services.openrouter.api_key');
        
        if (!$apiKey) {
            return null;
        }

        try {
            $payload = [
                'model' => 'openai/gpt-4o',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ];

            $baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

            $response = Http::timeout(30)
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

                $response = Http::timeout(30)->post($endpoint, $payload);

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

    private function tryOpenAI(array $messages): ?string
    {
        $apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');
        
        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('OpenAI API error.', [
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
            return 'Your safety comes first. Please contact emergency services or a trusted counselor right now. If you are alone, move toward another person and tell them clearly that you need support now.';
        }

        if ($this->matchesExactIntent($normalized, [
            'hi',
            'hello',
            'hey',
            'good morning',
            'good afternoon',
            'good evening',
        ])) {
            return 'Hello. I am here with you. Tell me how you are feeling today, and we can take it one step at a time.';
        }

        if ($this->matchesExactIntent($normalized, [
            'can you help me',
            'help me',
            'are you there',
            'i need help',
            'i need support',
            'talk to me',
        ])) {
            return 'Yes, I am here to help. Tell me what feels hardest right now, and I will help you work through one clear next step.';
        }

        if ($this->matchesExactIntent($normalized, [
            'thank you',
            'thanks',
            'thank you so much',
            'okay thanks',
            'ok thanks',
        ])) {
            return 'You are welcome. If you want, tell me what is still weighing on you, and we can keep working through it together.';
        }

        if ($this->matchesExactIntent($normalized, [
            'how are you',
            'who are you',
            'what can you do',
        ])) {
            return 'I am your AI wellness assistant. I can help you talk through stress, anxiety, study pressure, and practical coping steps. Tell me what is going on for you.';
        }

        if ($this->isFollowUpPrompt($normalized)) {
            return $this->buildFollowUpFallbackResponse($conversationTopic);
        }

        if ($conversationTopic === 'anxiety') {
            return 'That sounds heavy, and your reaction makes sense. Start with your body first: breathe in for 4 and out for 6 for one minute, then write the main thought making this feel threatening. After that, choose one 10 to 15 minute task that gives you a sense of control.';
        }

        if ($conversationTopic === 'study') {
            return 'Academic pressure can feel intense. Start with the smallest concrete task first, not the whole workload. Use one short cycle: 25 minutes focus, 5 minutes break, and then review what is still unclear. If you want, tell me the subject or assignment and I will help you break it down.';
        }

        if ($conversationTopic === 'sleep') {
            return 'Sleep strain can increase stress quickly. Focus on tonight rather than solving everything at once: reduce screens, avoid caffeine late, and do one calm wind-down activity before bed. If this continues for several days, discuss it with your counselor so you can create a recovery plan.';
        }

        if ($conversationTopic === 'sadness') {
            return 'I am sorry this feels heavy. Be gentle with yourself for today. Start with one grounding action like drinking water, stepping into fresh air, or messaging one trusted person. If you want, tell me whether this feels more like sadness, loneliness, or exhaustion.';
        }

        if (str_word_count($normalized) <= 4) {
            return 'I am listening. Tell me a little more about what is happening for you right now, and I will respond as clearly as I can.';
        }

        return 'I am here with you. Tell me what feels most difficult right now, and we will break it into one manageable next step together.';
    }

    private function normalizeIntentText(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = preg_replace('/[^\pL\pN\s]/u', ' ', $normalized);

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
            $content = $this->normalizeIntentText((string) ($historyMessages[$i]['content'] ?? ''));
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

        if (Str::contains($normalized, [
            'suicide',
            'kill myself',
            'end my life',
            'self harm',
            'hurt myself',
            'do not feel safe',
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

        if (Str::contains($normalized, ['sad', 'depressed', 'down', 'lonely', 'hopeless'])) {
            return 'sadness';
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
            'crisis' => 'Stay focused on safety right now. Reach out to emergency services, a counselor, or a trusted person immediately. If you can, send one direct message now saying you do not feel safe and need someone with you.',
            'anxiety' => 'Let us take it step by step. First, slow your breathing for one minute. Next, write the exact thought making this feel overwhelming. Then choose one 10 to 15 minute task that helps you regain control. If you want, tell me the thought and I will help you challenge it.',
            'study' => 'Start with the smallest academic action. Open the course material, pick one question or one subsection, and work on it for 15 minutes only. After that, pause and decide the next small task instead of thinking about the whole workload.',
            'sleep' => 'Start with tonight, not the whole week. Put screens aside for a while, dim the room if you can, and do one quiet routine such as breathing, stretching, or writing down tomorrow worries on paper so they are not circling in your head.',
            'sadness' => 'Start with something grounding and human. Drink some water, move to a brighter or calmer place, and send one short message to someone safe. Then tell me whether the hardest part is loneliness, exhaustion, or heavy thoughts.',
            default => 'We can do this one step at a time. Tell me the hardest part in one sentence, and I will help you decide what to do first.',
        };
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
}

