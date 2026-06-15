<?php

namespace App\Http\Controllers;

use App\Models\Tip;
use App\Models\TipFavorite;
use App\Models\User;
use App\Services\TipOfDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TipController extends Controller
{
    public function __construct(private readonly TipOfDayService $tipOfDayService) {}

    public function today(Request $request): JsonResponse
    {
        return $this->wellnessTip($request);
    }

    public function wellnessTip(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        return response()->json([
            'tip' => $this->tipOfDayService->resolveForUser($user),
        ]);
    }

    public function favorite(Request $request, Tip $tip): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        TipFavorite::query()->firstOrCreate([
            'user_id' => $user->id,
            'tip_id' => $tip->id,
        ]);

        return response()->json([
            'message' => 'Tip saved successfully.',
            'tip' => $this->favoritePayload($tip, true),
        ]);
    }

    public function unfavorite(Request $request, Tip $tip): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        TipFavorite::query()
            ->where('user_id', $user->id)
            ->where('tip_id', $tip->id)
            ->delete();

        return response()->json([
            'message' => 'Tip removed from saved tips.',
            'tip' => $this->favoritePayload($tip, false),
        ]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        return response()->json([
            'tips' => $user->tipFavorites()
                ->with('tip')
                ->latest()
                ->get()
                ->map(function (TipFavorite $favorite) {
                    return $favorite->tip instanceof Tip
                        ? $this->favoritePayload($favorite->tip, true)
                        : null;
                })
                ->filter()
                ->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json([
            'tips' => Tip::query()
                ->orderByDesc('is_active')
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $tip = Tip::query()->create($this->validatedPayload($request));

        return response()->json([
            'message' => 'Tip created successfully.',
            'tip' => $tip,
        ], 201);
    }

    public function update(Request $request, Tip $tip): JsonResponse
    {
        $this->ensureAdmin($request);
        $tip->fill($this->validatedPayload($request))->save();

        return response()->json([
            'message' => 'Tip updated successfully.',
            'tip' => $tip->fresh(),
        ]);
    }

    public function destroy(Request $request, Tip $tip): JsonResponse
    {
        $this->ensureAdmin($request);
        $tip->delete();

        return response()->json([
            'message' => 'Tip deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|min:3|max:120',
            'content' => 'required|string|min:10|max:400',
            'category' => 'required|string|min:2|max:60',
            'audience' => 'required|in:all,student,counselor,peer_counselor,admin',
            'priority' => 'nullable|integer|min:0|max:100',
            'is_active' => 'sometimes|boolean',
            'mood_tags' => 'nullable|array|max:8',
            'mood_tags.*' => 'string|max:32',
        ]);

        $validated['priority'] = (int) ($validated['priority'] ?? 0);
        $validated['is_active'] = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : true;
        $validated['mood_tags'] = collect($validated['mood_tags'] ?? [])
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $validated['content'] = trim((string) $validated['content']);

        $sentenceCount = $this->sentenceCount($validated['content']);
        if ($sentenceCount < 1 || $sentenceCount > 3) {
            throw ValidationException::withMessages([
                'content' => ['Wellness tips must be brief and limited to 1 to 3 sentences.'],
            ]);
        }

        if ($this->containsUnsafeLanguage($validated['content'])) {
            throw ValidationException::withMessages([
                'content' => ['Tip content must stay supportive and avoid harmful or extreme advice.'],
            ]);
        }

        return $validated;
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('admin'), 403, 'Admin access required');
    }

    private function sentenceCount(string $content): int
    {
        $parts = preg_split('/[.!?]+/', $content) ?: [];

        return collect($parts)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->count();
    }

    private function containsUnsafeLanguage(string $content): bool
    {
        $normalized = strtolower($content);
        $blockedPhrases = [
            'kill yourself',
            'self-harm',
            'starve yourself',
            'stop your medication',
            'skip your medication',
            'diagnose yourself',
            'hurt yourself',
            'hurt others',
        ];

        foreach ($blockedPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function favoritePayload(Tip $tip, bool $isFavorite): array
    {
        return [
            'id' => $tip->id,
            'title' => $tip->title,
            'content' => $tip->content,
            'category' => $tip->category,
            'audience' => $tip->audience,
            'mood_tags' => $tip->mood_tags ?? [],
            'priority' => $tip->priority,
            'is_active' => $tip->is_active,
            'is_favorite' => $isFavorite,
        ];
    }
}
