<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Support\PaginationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'sometimes|string|max:20',
            'direction' => 'sometimes|in:internal,external',
            'limit' => 'sometimes|integer|min:1|max:200',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = Referral::query()
            ->with(['student.profile', 'referredBy.profile', 'session', 'intakeSubmission', 'events.actor.profile'])
            ->orderByDesc('created_at');

        if ($user->hasRole('admin')) {
            // no scope filter
        } elseif ($user->hasRole('counselor') || $user->hasRole('peer_counselor')) {
            $query->where(function ($q) use ($user) {
                $q->where('referred_by', $user->id)
                    ->orWhereHas('session', function ($sessionQuery) use ($user) {
                        $sessionQuery->where('counselor_id', $user->id);
                    });
            });
        } else {
            $query->where('student_id', $user->id);
        }

        if (! empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }
        if (! empty($validated['direction'])) {
            $query->where('direction', (string) $validated['direction']);
        }

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? 50)));
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, ['status', 'direction'])
            );
        }

        $limit = (int) ($validated['limit'] ?? 50);

        return response()->json($query->limit($limit)->get());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $referral = Referral::query()
            ->with(['student.profile', 'referredBy.profile', 'events.actor.profile', 'session', 'intakeSubmission'])
            ->findOrFail($id);

        if (! $this->canViewReferral($request->user(), $referral)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($referral);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors or admins can create referrals'], 403);
        }

        $validated = $request->validate([
            'session_id' => 'sometimes|nullable|exists:counseling_sessions,id',
            'intake_submission_id' => 'sometimes|nullable|exists:intake_submissions,id',
            'student_id' => 'sometimes|nullable|exists:users,id',
            'direction' => 'required|in:internal,external',
            'target_service' => 'required|string|max:120',
            'destination_details' => 'sometimes|nullable|string|max:2000',
            'consent_granted' => 'required|boolean',
            'shared_fields' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string|max:2000',
        ]);

        if (! $validated['consent_granted'] && ! empty($validated['shared_fields'])) {
            return response()->json([
                'message' => 'Cannot share referral fields without consent.',
            ], 422);
        }

        $referral = Referral::query()->create([
            'session_id' => $validated['session_id'] ?? null,
            'intake_submission_id' => $validated['intake_submission_id'] ?? null,
            'student_id' => $validated['student_id'] ?? null,
            'referred_by' => $user->id,
            'direction' => $validated['direction'],
            'target_service' => $validated['target_service'],
            'destination_details' => $validated['destination_details'] ?? null,
            'consent_granted' => (bool) $validated['consent_granted'],
            'shared_fields' => $validated['shared_fields'] ?? null,
            'status' => 'pending',
            'referred_at' => now(),
            'outcome_notes' => null,
        ]);

        ReferralEvent::query()->create([
            'referral_id' => $referral->id,
            'actor_id' => $user->id,
            'event_type' => 'created',
            'notes' => $validated['notes'] ?? 'Referral created',
            'metadata' => [
                'direction' => $referral->direction,
                'target_service' => $referral->target_service,
            ],
        ]);

        return response()->json($referral->load(['events.actor.profile']), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $referral = Referral::query()->findOrFail($id);
        $user = $request->user();

        if (! $this->canManageReferral($user, $referral)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,accepted,completed,declined,cancelled',
            'outcome_notes' => 'sometimes|nullable|string|max:2000',
            'consent_granted' => 'sometimes|boolean',
            'shared_fields' => 'sometimes|nullable|array',
        ]);

        if (($validated['consent_granted'] ?? $referral->consent_granted) === false && ! empty($validated['shared_fields'])) {
            return response()->json(['message' => 'Cannot share fields without consent.'], 422);
        }

        if (! empty($validated['status']) && in_array($validated['status'], ['completed', 'declined', 'cancelled'], true)) {
            $validated['closed_at'] = now();
        }

        $referral->update($validated);

        if (! empty($validated['status']) || array_key_exists('outcome_notes', $validated)) {
            ReferralEvent::query()->create([
                'referral_id' => $referral->id,
                'actor_id' => $user->id,
                'event_type' => 'status_updated',
                'notes' => $validated['outcome_notes'] ?? ('Status updated to '.($validated['status'] ?? $referral->status)),
                'metadata' => [
                    'status' => $validated['status'] ?? $referral->status,
                ],
            ]);
        }

        return response()->json($referral->fresh(['events.actor.profile']));
    }

    public function addEvent(Request $request, string $id): JsonResponse
    {
        $referral = Referral::query()->findOrFail($id);
        $user = $request->user();
        if (! $this->canManageReferral($user, $referral)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|max:40',
            'notes' => 'sometimes|nullable|string|max:2000',
            'metadata' => 'sometimes|nullable|array',
        ]);

        $event = ReferralEvent::query()->create([
            'referral_id' => $referral->id,
            'actor_id' => $user->id,
            'event_type' => $validated['event_type'],
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json($event->load('actor.profile'), 201);
    }

    private function canViewReferral($user, Referral $referral): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        if ((int) $referral->student_id === (int) $user->id) {
            return true;
        }
        if ($user->hasRole('counselor') && (int) $referral->referred_by === (int) $user->id) {
            return true;
        }

        return $user->hasRole('counselor')
            && $referral->session
            && (int) $referral->session->counselor_id === (int) $user->id;
    }

    private function canManageReferral($user, Referral $referral): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('counselor') && (int) $referral->referred_by === (int) $user->id;
    }
}
