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
            $response = $this->buildLocalWellnessFallbackResponse($message);
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

    private function buildLocalWellnessFallbackResponse(string $message): string
    {
        $normalized = Str::lower($message);

        $crisisTerms = [
            'suicide',
            'kill myself',
            'end my life',
            'self harm',
            'hurt myself',
        ];
        foreach ($crisisTerms as $term) {
            if (Str::contains($normalized, $term)) {
                return 'Thank you for reaching out. If you might harm yourself, contact emergency services now and message your counselor immediately. If possible, stay with someone you trust while support is arranged.';
            }
        }

        if (Str::contains($normalized, ['anxiety', 'anxious', 'panic', 'overwhelmed', 'stress'])) {
            return 'That sounds heavy, and your reaction makes sense. Try this quick reset now: breathe in for 4 and out for 6 for one minute, write the top 3 tasks only, then do the smallest one for 10 minutes. If this pattern continues, book a counselor check-in.';
        }

        if (Str::contains($normalized, ['exam', 'deadline', 'assignment', 'study'])) {
            return 'Academic pressure can feel intense. Use a short cycle: 25 minutes focus, 5 minutes break, repeat 3 times. Start with the most concrete task first. If workload still feels unmanageable, reach out to your counselor for a support plan.';
        }

        if (Str::contains($normalized, ['sleep', 'insomnia', 'tired', 'exhausted'])) {
            return 'Sleep strain can increase stress quickly. For tonight: avoid caffeine late, dim screens before bed, and do a brief wind-down routine. If this continues for several days, discuss it with your counselor so you can create a recovery plan.';
        }

        return 'I hear you. Let us break this into one manageable next step: name the main pressure, choose one person you can contact today, and complete one 15-minute action now. I can help you structure that if you want.';
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

