<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenRouterService
{
    private const FALLBACK_MODEL = 'mindful/offline-assistant-v1';
    private const FALLBACK_RESPONSE = 'I am currently using local support mode. Share one specific concern, and I can suggest a short coping plan while you connect with a counselor.';
    public const DEFAULT_CHAT_MODEL = 'mistralai/mistral-7b-instruct:free';
    public const DEFAULT_CORE_MODEL = 'qwen/qwen3-next-80b-a3b-thinking';
    public const DEFAULT_HEAVY_ANALYSIS_MODEL = 'deepseek/deepseek-v4-pro';
    public const DEFAULT_SPEED_MODEL = 'liquid/lfm-2.5-1.2b-thinking:free';
    private const DEFAULT_PROVIDER_TIMEOUT_SECONDS = 8;
    private const DEFAULT_PROVIDER_CONNECT_TIMEOUT_SECONDS = 5;

    private Client $client;
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openrouter.api_key', '');
        $this->baseUrl = $this->normalizeBaseUrl(
            (string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1')
        );

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => (string) config('services.openrouter.site_url', 'http://localhost'),
                'X-Title' => (string) config('services.openrouter.site_name', 'AI Chat'),
            ],
            'timeout' => $this->providerTimeoutSeconds(),
            'connect_timeout' => $this->providerConnectTimeoutSeconds(),
        ]);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim(trim($baseUrl), '/');

        return $normalized === '' ? 'https://openrouter.ai/api/v1/' : $normalized . '/';
    }

    /**
     * Send a chat message and get response
     */
    public function sendMessage(array $messages, ?string $model = null, ?int $conversationId = null): array
    {
        $model = self::resolveChatModel($model);

        if (!$this->hasConfiguredApiKey()) {
            return $this->fallbackResult($messages, $model, $conversationId, 'missing_api_key');
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (!is_array($data)) {
                Log::warning('OpenRouter API returned non-JSON payload. Falling back to local assistant.');
                return $this->fallbackResult($messages, $model, $conversationId, 'invalid_json');
            }

            $content = trim($this->extractContent($data));
            if ($content === '') {
                Log::warning('OpenRouter API returned empty content. Falling back to local assistant.');
                return $this->fallbackResult($messages, $model, $conversationId, 'empty_content');
            }

            // Save to database if user is authenticated
            if (Auth::check()) {
                $conversationId = $this->saveConversation($messages, $content, $model, $conversationId);
            }

            return [
                'success' => true,
                'content' => $content,
                'usage' => $data['usage'] ?? null,
                'conversation_id' => $conversationId,
            ];
        } catch (RequestException $e) {
            Log::warning('OpenRouter API error. Falling back to local assistant.', [
                ...$this->requestExceptionContext($e),
            ]);

            return $this->fallbackResult($messages, $model, $conversationId, 'request_exception');
        } catch (\Throwable $e) {
            Log::error('Unexpected OpenRouter error. Falling back to local assistant.', [
                ...$this->throwableContext($e),
            ]);

            return $this->fallbackResult($messages, $model, $conversationId, 'unexpected_error');
        }
    }

    /**
     * Stream chat response
     */
    public function streamMessage(array $messages, ?string $model = null, ?int $conversationId = null): \Generator
    {
        $model = self::resolveChatModel($model);

        if (!$this->hasConfiguredApiKey()) {
            yield from $this->streamFallback($messages, $model, $conversationId);
            return;
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'stream' => true,
                ],
                'stream' => true,
            ]);

            $stream = $response->getBody();
            $fullContent = '';

            foreach ($stream as $chunk) {
                $lines = explode("\n", $chunk);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if ($line === '' || !str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $payload = substr($line, 6);

                    if ($payload === '[DONE]') {
                        // Save conversation to database if user is authenticated
                        if (Auth::check() && $fullContent !== '') {
                            $conversationId = $this->saveConversation($messages, $fullContent, $model, $conversationId);
                            yield ['content' => '', 'done' => true, 'conversation_id' => $conversationId];
                        } else {
                            yield ['content' => '', 'done' => true];
                        }
                        return;
                    }

                    $json = json_decode($payload, true);

                    if (is_array($json) && isset($json['choices'][0]['delta']['content']) && is_string($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];
                        $fullContent .= $content;
                        yield [
                            'content' => $content,
                            'done' => false,
                        ];
                    }
                }
            }

            if ($fullContent !== '') {
                yield ['content' => '', 'done' => true];
                return;
            }

            yield from $this->streamFallback($messages, $model, $conversationId);
        } catch (RequestException $e) {
            Log::warning('OpenRouter streaming error. Falling back to local assistant.', [
                ...$this->requestExceptionContext($e),
            ]);
            yield from $this->streamFallback($messages, $model, $conversationId);
        } catch (\Throwable $e) {
            Log::error('Unexpected OpenRouter streaming error. Falling back to local assistant.', [
                ...$this->throwableContext($e),
            ]);
            yield from $this->streamFallback($messages, $model, $conversationId);
        }
    }

    /**
     * Extract content from response
     */
    private function extractContent(?array $data): string
    {
        if (!$data) {
            return '';
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        if (is_array($content)) {
            return collect($content)
                ->filter(fn ($item) => is_array($item) && isset($item['text']))
                ->map(fn ($item) => $item['text'])
                ->implode('');
        }

        return (string) $content;
    }

    /**
     * Extract error message from exception
     */
    private function extractErrorMessage(RequestException $e): string
    {
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (is_array($data) && isset($data['error']['message'])) {
                return (string) $data['error']['message'];
            }

            return $body;
        }

        return $e->getMessage();
    }

    /**
     * Get available models
     */
    public function getModels(): array
    {
        if (!$this->hasConfiguredApiKey()) {
            return [
                'success' => true,
                'models' => $this->fallbackModels(),
            ];
        }

        try {
            $response = $this->client->get('models');
            $data = json_decode((string) $response->getBody(), true);

            if (!is_array($data)) {
                return [
                    'success' => true,
                    'models' => $this->fallbackModels(),
                ];
            }

            return [
                'success' => true,
                'models' => $data['data'] ?? [],
            ];
        } catch (RequestException $e) {
            Log::warning('OpenRouter models request failed. Returning fallback model list.', [
                ...$this->requestExceptionContext($e),
            ]);

            return [
                'success' => true,
                'models' => $this->fallbackModels(),
            ];
        } catch (\Throwable $e) {
            Log::error('Unexpected OpenRouter models error. Returning fallback model list.', [
                ...$this->throwableContext($e),
            ]);

            return [
                'success' => true,
                'models' => $this->fallbackModels(),
            ];
        }
    }

    /**
     * Save conversation to database
     */
    private function saveConversation(array $messages, string $assistantResponse, string $model, ?int $conversationId = null): int
    {
        $user = Auth::user();

        if (!$user) {
            throw new \RuntimeException('Authenticated user required to persist conversation.');
        }

        // Find or create AI model
        $aiModel = AiModel::findOrCreateByName($model, [
            'display_name' => $this->getDisplayNameForModel($model),
            'provider' => $this->getProviderForModel($model),
        ]);

        $conversation = null;
        if ($conversationId) {
            $conversation = ChatConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();
        }

        if (!$conversation) {
            $conversation = ChatConversation::create([
                'user_id' => $user->id,
                'ai_model_id' => $aiModel->id,
                'title' => $this->generateConversationTitle($messages),
                'last_message_at' => now(),
            ]);
            $conversationId = (int) $conversation->id;
        } else {
            $conversation->ai_model_id = $aiModel->id;
            $conversation->updateLastMessageAt();
            $conversation->save();
        }

        // Save messages to database
        foreach ($messages as $message) {
            $chatMessage = ChatMessage::create([
                'conversation_id' => $conversationId,
                'role' => $message['role'],
                'content' => $message['content'],
            ]);

            // Add any relevant metadata for user messages
            if ($message['role'] === 'user') {
                $chatMessage->addMetadata('message_length', strlen($message['content']), 'integer');
                $chatMessage->addMetadata('word_count', str_word_count($message['content']), 'integer');
            }
        }

        // Save assistant response with metadata
        $assistantMessage = ChatMessage::create([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $assistantResponse,
        ]);

        // Add metadata for assistant response
        $assistantMessage->addMetadata('message_length', strlen($assistantResponse), 'integer');
        $assistantMessage->addMetadata('word_count', str_word_count($assistantResponse), 'integer');
        $assistantMessage->addMetadata('model_used', $model, 'string');

        return $conversationId;
    }

    /**
     * Generate conversation title from messages
     */
    private function generateConversationTitle(array $messages): string
    {
        foreach ($messages as $message) {
            if ($message['role'] === 'user') {
                return substr($message['content'], 0, 50) .
                    (strlen($message['content']) > 50 ? '...' : '');
            }
        }

        return 'New Chat';
    }

    /**
     * Get user conversations
     */
    public function getUserConversations(): array
    {
        $user = Auth::user();

        $conversations = ChatConversation::where('user_id', $user->id)
            ->with(['latestMessage', 'aiModel'])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                    'model' => $conversation->getModelName(),
                    'ai_model' => $conversation->aiModel?->display_name ?? $conversation->getModelName(),
                    'last_message_at' => $conversation->last_message_at,
                    'message_count' => $conversation->messages()->count(),
                    'latest_message' => $conversation->latestMessage?->content ?? '',
                ];
            });

        return [
            'success' => true,
            'conversations' => $conversations,
        ];
    }

    /**
     * Get conversation messages
     */
    public function getConversationMessages(int $conversationId): array
    {
        $user = Auth::user();

        $conversation = ChatConversation::where('user_id', $user->id)
            ->where('id', $conversationId)
            ->with(['aiModel'])
            ->first();

        if (!$conversation) {
            return [
                'success' => false,
                'error' => 'Conversation not found',
            ];
        }

        $messages = $conversation->messages()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                ];
            });

        return [
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->getModelName(),
                'ai_model' => $conversation->aiModel?->display_name ?? $conversation->getModelName(),
                'created_at' => $conversation->created_at,
                'messages' => $messages,
            ],
        ];
    }

    private function hasConfiguredApiKey(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public static function configuredChatModel(): string
    {
        return self::configuredModel('chat_model', self::DEFAULT_CHAT_MODEL);
    }

    public static function configuredCoreModel(): string
    {
        return self::configuredModel('core_model', self::DEFAULT_CORE_MODEL);
    }

    public static function configuredHeavyAnalysisModel(): string
    {
        return self::configuredModel('heavy_analysis_model', self::DEFAULT_HEAVY_ANALYSIS_MODEL);
    }

    public static function configuredSpeedModel(): string
    {
        return self::configuredModel('speed_model', self::DEFAULT_SPEED_MODEL);
    }

    public static function resolveChatModel(?string $model = null): string
    {
        $candidate = trim((string) ($model ?? ''));

        if ($candidate === '' || self::isOpenAiModelName($candidate)) {
            return self::configuredChatModel();
        }

        return $candidate;
    }

    private static function configuredModel(string $key, string $fallback): string
    {
        $model = trim((string) config("services.openrouter.{$key}", $fallback));

        if ($model === '' || self::isOpenAiModelName($model)) {
            return $fallback;
        }

        return $model;
    }

    private static function isOpenAiModelName(string $model): bool
    {
        $lower = Str::lower($model);

        return Str::contains($lower, ['openai/', 'gpt-', 'gpt_']);
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

    private function fallbackResult(array $messages, string $requestedModel, ?int $conversationId, string $reason): array
    {
        $content = $this->buildLocalFallbackResponse($messages);
        $model = self::FALLBACK_MODEL;
        $resolvedConversationId = $conversationId;

        if (Auth::check()) {
            try {
                $resolvedConversationId = $this->saveConversation($messages, $content, $model, $conversationId);
            } catch (\Throwable $e) {
                Log::warning('Failed to persist fallback OpenRouter conversation.', [
                    ...$this->throwableContext($e),
                ]);
            }
        }

        Log::info('OpenRouter fallback response used.', [
            'reason' => $reason,
            'requested_model' => $requestedModel,
        ]);

        return [
            'success' => true,
            'content' => $content,
            'usage' => null,
            'conversation_id' => $resolvedConversationId,
            'model' => $model,
            'fallback' => true,
        ];
    }

    private function streamFallback(array $messages, string $requestedModel, ?int $conversationId): \Generator
    {
        $result = $this->fallbackResult($messages, $requestedModel, $conversationId, 'stream_fallback');
        $content = (string) ($result['content'] ?? '');
        $resolvedConversationId = $result['conversation_id'] ?? $conversationId;

        if ($content !== '') {
            yield [
                'content' => $content,
                'done' => false,
            ];
        }

        yield [
            'content' => '',
            'done' => true,
            'conversation_id' => $resolvedConversationId,
        ];
    }

    private function buildLocalFallbackResponse(array $messages): string
    {
        $userMessage = $this->latestUserMessage($messages);
        if ($userMessage === '') {
            return self::FALLBACK_RESPONSE;
        }

        $normalized = Str::lower($userMessage);

        $crisisTerms = [
            'suicide',
            'kill myself',
            'end my life',
            'self harm',
            'hurt myself',
        ];
        foreach ($crisisTerms as $term) {
            if (Str::contains($normalized, $term)) {
                return 'I am really glad you reached out. If you might harm yourself, please contact emergency services right now and message your counselor immediately. If possible, stay with someone you trust while support is on the way.';
            }
        }

        if (Str::contains($normalized, ['anxious', 'anxiety', 'panic', 'overwhelmed', 'stress'])) {
            return 'It sounds like stress is high right now. Try this 3-step reset: 1) inhale for 4, exhale for 6 for one minute, 2) write the top 3 urgent tasks only, 3) start a 10-minute timer for the first task. If this keeps happening, please schedule a counselor check-in.';
        }

        if (Str::contains($normalized, ['sleep', 'insomnia', 'tired', 'exhausted'])) {
            return 'Sleep strain can amplify stress quickly. Keep tonight simple: no caffeine late, dim screens 1 hour before bed, and do a short wind-down routine. If sleep problems persist for several days, consult your counselor for a focused plan.';
        }

        if (Str::contains($normalized, ['exam', 'deadline', 'assignment', 'study'])) {
            return 'For study pressure, use a short focus cycle: 25 minutes focused work, 5 minutes break, then repeat 3 times. Start with the smallest task that unlocks progress. If workload still feels unmanageable, ask your counselor for support planning.';
        }

        return 'Thanks for sharing that. A practical next step is to name one immediate pressure point, one support person you can contact today, and one action you can finish in the next 15 minutes. I can help you structure that now.';
    }

    private function latestUserMessage(array $messages): string
    {
        for ($idx = count($messages) - 1; $idx >= 0; $idx--) {
            $message = $messages[$idx] ?? null;
            if (!is_array($message)) {
                continue;
            }

            if (($message['role'] ?? null) !== 'user') {
                continue;
            }

            $content = trim((string) ($message['content'] ?? ''));
            if ($content !== '') {
                return $content;
            }
        }

        return '';
    }

    private function fallbackModels(): array
    {
        return [
            [
                'id' => self::configuredChatModel(),
                'name' => self::configuredChatModel(),
                'provider' => 'meta',
                'description' => 'Llama 3.3 chat interface for natural student wellness conversations.',
                'context_length' => 131072,
            ],
            [
                'id' => self::configuredCoreModel(),
                'name' => self::configuredCoreModel(),
                'provider' => 'qwen',
                'description' => 'Qwen3 80B core reasoning model for deeper app analysis.',
                'context_length' => 262144,
            ],
            [
                'id' => self::configuredHeavyAnalysisModel(),
                'name' => self::configuredHeavyAnalysisModel(),
                'provider' => 'deepseek',
                'description' => 'DeepSeek heavy analysis model for very large inputs.',
                'context_length' => 1048576,
            ],
            [
                'id' => self::configuredSpeedModel(),
                'name' => self::configuredSpeedModel(),
                'provider' => 'liquid',
                'description' => 'Liquid fast fallback model for lightweight thinking tasks.',
                'context_length' => 32768,
            ],
            [
                'id' => self::FALLBACK_MODEL,
                'name' => self::FALLBACK_MODEL,
                'provider' => 'local',
                'description' => 'Local offline fallback assistant for resilience when cloud AI providers are unavailable.',
                'context_length' => 4096,
            ],
        ];
    }

    /**
     * Get display name for model
     */
    private function getDisplayNameForModel(string $model): string
    {
        $modelMap = [
            self::FALLBACK_MODEL => 'Mindful Offline Assistant',
            self::DEFAULT_CHAT_MODEL => 'Llama 3.3 70B Instruct',
            self::DEFAULT_CORE_MODEL => 'Qwen3 Next 80B Thinking',
            self::DEFAULT_HEAVY_ANALYSIS_MODEL => 'DeepSeek V4 Pro',
            self::DEFAULT_SPEED_MODEL => 'LFM2.5 1.2B Thinking',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
        ];

        return $modelMap[$model] ?? $model;
    }

    /**
     * Get provider for model
     */
    private function getProviderForModel(string $model): string
    {
        $lower = Str::lower($model);

        if ($model === self::FALLBACK_MODEL) return 'local';
        if (str_contains($lower, 'meta-llama') || str_contains($lower, 'llama')) return 'meta';
        if (str_contains($lower, 'deepseek')) return 'deepseek';
        if (str_contains($lower, 'liquid') || str_contains($lower, 'lfm')) return 'liquid';
        if (str_contains($lower, 'nvidia')) return 'nvidia';
        if (str_contains($lower, 'qwen')) return 'qwen';
        if (str_contains($lower, 'claude')) return 'anthropic';

        return 'openrouter';
    }

    private function requestExceptionContext(RequestException $e): array
    {
        $status = $e->hasResponse() ? $e->getResponse()?->getStatusCode() : null;

        return [
            'exception' => $e::class,
            'status' => $status,
        ];
    }

    private function throwableContext(\Throwable $e): array
    {
        return [
            'exception' => $e::class,
        ];
    }
}
