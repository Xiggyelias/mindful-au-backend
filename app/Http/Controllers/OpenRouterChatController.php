<?php

namespace App\Http\Controllers;

use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OpenRouterChatController extends Controller
{
    private OpenRouterService $openRouterService;

    public function __construct(OpenRouterService $openRouterService)
    {
        $this->openRouterService = $openRouterService;
    }

    /**
     * Send a chat message and get response
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => 'required|array|min:1|max:40',
            'messages.*.role' => 'required|in:user,assistant,system',
            'messages.*.content' => 'required|string|max:4000',
            'model' => 'sometimes|string|max:255',
            'conversation_id' => 'sometimes|integer|exists:chat_conversations,id',
        ]);

        $user = $request->user();
        $messages = $request->input('messages');
        $model = OpenRouterService::resolveChatModel($request->input('model'));
        $conversationId = $request->input('conversation_id');

        if ($conversationId && !$this->conversationBelongsToUser((int) $conversationId, (int) $user->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $result = $this->openRouterService->sendMessage($messages, $model, $conversationId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'content' => $result['content'],
                'usage' => $result['usage'],
                'conversation_id' => $result['conversation_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred',
        ], 500);
    }

    /**
     * Stream chat response
     */
    public function streamMessage(Request $request): Response
    {
        $request->validate([
            'messages' => 'required|array|min:1|max:40',
            'messages.*.role' => 'required|in:user,assistant,system',
            'messages.*.content' => 'required|string|max:4000',
            'model' => 'sometimes|string|max:255',
            'conversation_id' => 'sometimes|integer|exists:chat_conversations,id',
        ]);

        $user = $request->user();
        $messages = $request->input('messages');
        $model = OpenRouterService::resolveChatModel($request->input('model'));
        $conversationId = $request->input('conversation_id');

        if ($conversationId && !$this->conversationBelongsToUser((int) $conversationId, (int) $user->id)) {
            return response(json_encode([
                'success' => false,
                'error' => 'Conversation not found',
            ], JSON_UNESCAPED_UNICODE), 404, ['Content-Type' => 'application/json']);
        }

        $response = new Response();
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');

        $stream = $this->openRouterService->streamMessage($messages, $model, $conversationId);

        foreach ($stream as $chunk) {
            if (isset($chunk['success']) && !$chunk['success']) {
                $response->setContent("data: " . json_encode([
                    'success' => false,
                    'error' => $chunk['error'] ?? 'Unknown error occurred',
                    'done' => true,
                ]) . "\n\n");
                break;
            }

            $response->setContent("data: " . json_encode($chunk) . "\n\n");
            $response->send();
        }

        return $response;
    }

    /**
     * Get available models
     */
    public function getModels(): JsonResponse
    {
        $result = $this->openRouterService->getModels();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'models' => $result['models'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred',
        ], 500);
    }

    /**
     * Simple chat endpoint for testing
     */
    public function simpleChat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'model' => 'sometimes|string|max:255',
        ]);

        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return response()->json([
                'success' => false,
                'error' => 'Message cannot be empty',
            ], 422);
        }

        $model = OpenRouterService::resolveChatModel($request->input('model'));

        $messages = [
            ['role' => 'user', 'content' => $message]
        ];

        $result = $this->openRouterService->sendMessage($messages, $model);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'response' => $result['content'],
                'model' => $model,
                'usage' => $result['usage'],
                'conversation_id' => $result['conversation_id'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred',
        ], 500);
    }

    /**
     * Get user's chat conversations
     */
    public function getConversations(): JsonResponse
    {
        $result = $this->openRouterService->getUserConversations();

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred',
        ], 500);
    }

    /**
     * Get conversation messages
     */
    public function getConversationMessages(Request $request, int $conversationId): JsonResponse
    {
        $result = $this->openRouterService->getConversationMessages($conversationId);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error occurred',
        ], 404);
    }

    /**
     * Create new conversation
     */
    public function createConversation(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'model' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $title = $request->input('title', 'New Chat');
        $model = OpenRouterService::resolveChatModel($request->input('model'));

        // Find or create AI model
        $aiModel = \App\Models\AiModel::findOrCreateByName($model, [
            'display_name' => $this->getDisplayNameForModel($model),
            'provider' => $this->getProviderForModel($model),
        ]);

        $conversation = \App\Models\ChatConversation::create([
            'user_id' => $user->id,
            'ai_model_id' => $aiModel->id,
            'title' => $title,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->getModelName(),
                'ai_model' => $aiModel->display_name,
                'created_at' => $conversation->created_at,
                'message_count' => 0,
            ],
        ]);
    }

    /**
     * Delete conversation
     */
    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        
        $conversation = \App\Models\ChatConversation::where('user_id', $user->id)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully',
        ]);
    }

    /**
     * Get display name for model
     */
    private function getDisplayNameForModel(string $model): string
    {
        $modelMap = [
            OpenRouterService::DEFAULT_CHAT_MODEL => 'Llama 3.3 70B Instruct',
            OpenRouterService::DEFAULT_CORE_MODEL => 'Qwen3 Next 80B Thinking',
            OpenRouterService::DEFAULT_HEAVY_ANALYSIS_MODEL => 'DeepSeek V4 Pro',
            OpenRouterService::DEFAULT_SPEED_MODEL => 'LFM2.5 1.2B Thinking',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
        ];

        return $modelMap[$model] ?? $model;
    }

    /**
     * Get provider for model
     */
    private function getProviderForModel(string $model): string
    {
        $lower = strtolower($model);

        if (str_contains($lower, 'meta-llama') || str_contains($lower, 'llama')) return 'meta';
        if (str_contains($lower, 'deepseek')) return 'deepseek';
        if (str_contains($lower, 'liquid') || str_contains($lower, 'lfm')) return 'liquid';
        if (str_contains($lower, 'nvidia')) return 'nvidia';
        if (str_contains($lower, 'qwen')) return 'qwen';
        if (str_contains($lower, 'claude')) return 'anthropic';
        
        return 'openrouter';
    }

    private function conversationBelongsToUser(int $conversationId, int $userId): bool
    {
        return DB::table('chat_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->exists();
    }
}
