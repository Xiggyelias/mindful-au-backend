<?php

namespace App\Http\Controllers;

use App\Services\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $model = $request->input('model', 'nvidia/nemotron-nano-9b-v2:free');
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
        $model = $request->input('model', 'nvidia/nemotron-nano-9b-v2:free');
        $conversationId = $request->input('conversation_id');

        if ($conversationId && !$this->conversationBelongsToUser((int) $conversationId, (int) $user->id)) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation not found',
            ], 404);
        }

        $stream = $this->openRouterService->streamMessage($messages, $model, $conversationId);
        return new StreamedResponse(function () use ($stream): void {
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }

            foreach ($stream as $chunk) {
                $payload = $chunk;
                if (isset($chunk['success']) && $chunk['success'] === false) {
                    $payload = [
                        'success' => false,
                        'error' => $chunk['error'] ?? 'Unknown error occurred',
                        'done' => true,
                    ];
                }

                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

                if (function_exists('ob_get_level') && ob_get_level() > 0 && function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();

                if (($payload['done'] ?? false) === true) {
                    return;
                }
            }

            echo 'data: {"content":"","done":true}' . "\n\n";
            if (function_exists('ob_get_level') && ob_get_level() > 0 && function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
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

        $model = $request->input('model', 'nvidia/nemotron-nano-9b-v2:free');

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
        $model = $request->input('model', 'nvidia/nemotron-nano-9b-v2:free');

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
            'nvidia/nemotron-nano-9b-v2:free' => 'NVIDIA Nemotron Nano 9B',
            'qwen/qwen3-4b:free' => 'Qwen 3 4B',
            'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
        ];

        return $modelMap[$model] ?? $model;
    }

    /**
     * Get provider for model
     */
    private function getProviderForModel(string $model): string
    {
        if (str_contains($model, 'nvidia')) return 'nvidia';
        if (str_contains($model, 'qwen')) return 'qwen';
        if (str_contains($model, 'gpt')) return 'openai';
        if (str_contains($model, 'claude')) return 'anthropic';
        
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
