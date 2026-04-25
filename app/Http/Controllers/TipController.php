<?php

namespace App\Http\Controllers;

use App\Models\Tip;
use App\Models\User;
use App\Services\TipOfDayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function __construct(private readonly TipOfDayService $tipOfDayService)
    {
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        return response()->json([
            'tip' => $this->tipOfDayService->resolveForUser($user),
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
            'content' => 'required|string|min:10|max:600',
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

        return $validated;
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('admin'), 403, 'Admin access required');
    }
}
