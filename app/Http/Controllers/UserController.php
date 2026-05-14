<?php

namespace App\Http\Controllers;

use App\Support\PaginationPayload;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = User::query()->with(['profile', 'roles'])->orderByDesc('id');
        $limit = (int) ($validated['limit'] ?? 150);
        $limit = max(1, min(500, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));

        if ($usePagination) {
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, [])
            );
        }

        $users = $query->limit($limit)->get();

        return response()->json($users);
    }

    public function counselors(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'lightweight' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:300',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        // Students, counselors, and admins should be able to see counselors
        $hasPortalRole = $user->hasRole('admin')
            || $user->hasRole('counselor')
            || $user->hasRole('student');

        if (!$hasPortalRole) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isAdmin = $user->hasRole('admin');
        $lightweight = (bool) ($validated['lightweight'] ?? false);
        $limit = (int) ($validated['limit'] ?? ($lightweight ? 150 : 0));
        $limit = max(0, min(300, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? ($limit > 0 ? $limit : 50))));
        $onlineThreshold = now()->subMinutes((int) env('COUNSELOR_ONLINE_WINDOW_MINUTES', 10));

        if ($lightweight) {
            $buildLightweightQuery = function () use ($isAdmin): \Illuminate\Database\Eloquent\Builder {
                $query = User::query()
                    ->with(['profile:id,user_id,full_name'])
                    ->select(['id', 'email', 'last_seen_at'])
                    ->orderByDesc('last_seen_at');

                if ($isAdmin) {
                    $query->whereHas('roles', function ($roleQuery) {
                        $roleQuery->whereIn('role', ['counselor', 'peer_counselor']);
                    });
                } else {
                    $query->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('role', 'counselor')->where('approved', true);
                    });
                }

                return $query;
            };

            if ($usePagination) {
                $query = $buildLightweightQuery();
                $paginator = $query
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->appends($request->query());
                $lightweightCounselors = collect($paginator->items());
            } else {
                $cacheSeconds = max(5, (int) env('COUNSELORS_LIGHTWEIGHT_CACHE_SECONDS', 15));
                $cacheKey = sprintf('users:counselors:lightweight:%s:%d', $isAdmin ? 'admin' : 'portal', $limit);

                $lightweightCounselors = (!$isAdmin)
                    ? Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use ($buildLightweightQuery, $limit) {
                        $query = $buildLightweightQuery();
                        if ($limit > 0) {
                            $query->limit($limit);
                        }
                        return $query->get();
                    })
                    : (function () use ($buildLightweightQuery, $limit) {
                        $query = $buildLightweightQuery();
                        if ($limit > 0) {
                            $query->limit($limit);
                        }
                        return $query->get();
                    })();
            }

            $response = $lightweightCounselors->map(function (User $counselor) use ($onlineThreshold) {
                $lastSeenAt = $counselor->last_seen_at;
                if (is_string($lastSeenAt)) {
                    $lastSeenAt = Carbon::parse($lastSeenAt);
                }

                $isOnline = $lastSeenAt instanceof Carbon
                    && $lastSeenAt->greaterThanOrEqualTo($onlineThreshold);

                return [
                    'id' => (int) $counselor->id,
                    'email' => $counselor->email,
                    'last_seen_at' => $counselor->last_seen_at,
                    'is_online' => $isOnline,
                    'profile' => [
                        'full_name' => $counselor->profile?->full_name,
                    ],
                ];
            })->values();

            if ($usePagination) {
                $payload = PaginationPayload::fromPaginator(
                    $paginator,
                    $request,
                    ['lightweight']
                );
                $payload['data'] = $response->all();
                return response()->json($payload);
            }

            return response()->json($response);
        }

        // Admins can see all staff counseling roles (counselor + peer counselor).
        // Students and counselors only see approved counselors.
        $query = User::query()->whereHas('roles', function($query) use ($isAdmin) {
                if ($isAdmin) {
                    $query->whereIn('role', ['counselor', 'peer_counselor']);
                    return;
                }

                $query->where('role', 'counselor')->where('approved', true);
            })
            ->with(['profile', 'roles'])
            ->orderByDesc('last_seen_at');

        if ($usePagination) {
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $counselors = collect($paginator->items());
        } else {
            if ($limit > 0) {
                $query->limit($limit);
            }
            $counselors = $query->get();
        }

        $counselors = $counselors->map(function (User $counselor) use ($onlineThreshold) {
            $lastSeenAt = $counselor->last_seen_at;
            if (is_string($lastSeenAt)) {
                $lastSeenAt = Carbon::parse($lastSeenAt);
            }

            $isOnline = $lastSeenAt instanceof Carbon
                && $lastSeenAt->greaterThanOrEqualTo($onlineThreshold);

            $counselor->setAttribute('is_online', $isOnline);

            return $counselor;
        })->values();

        if ($usePagination) {
            $payload = PaginationPayload::fromPaginator($paginator, $request, []);
            $payload['data'] = $counselors->all();
            return response()->json($payload);
        }

        return response()->json($counselors);
    }

    public function students(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $studentsQuery = User::query()
            ->whereHas('roles', function($query) {
                $query->where('role', 'student');
            })
            ->with(['profile', 'roles']);

        // Admins can manage both pending and approved students.
        // Counselors should only see approved students they can serve.
        if (!$user->hasRole('admin')) {
            $studentsQuery->whereHas('roles', function($query) {
                $query->where('role', 'student')->where('approved', true);
            });
        }

        $studentsQuery->orderByDesc('created_at');
        $limit = (int) ($validated['limit'] ?? 150);
        $limit = max(1, min(500, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));

        if ($usePagination) {
            $paginator = $studentsQuery
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $students = collect($paginator->items());
        } else {
            $students = $studentsQuery
                ->limit($limit)
                ->get();
        }

        // Apply anonymous mode for counselors
        if ($user->hasRole('counselor') && !$user->hasRole('admin')) {
            $students = $students->map(function($student) {
                if ($student->profile && $student->profile->anonymous_mode) {
                    $student->profile->full_name = $student->getAnonymousName();
                    $student->email = 'anonymous@africau.edu';
                }
                return $student;
            });
        }

        if ($usePagination) {
            $payload = PaginationPayload::fromPaginator($paginator, $request, []);
            $payload['data'] = $students->values()->all();
            return response()->json($payload);
        }

        return response()->json($students);
    }

    public function peerCounselors(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('role', 'peer_counselor')->where('approved', true);
            })
            ->with(['profile', 'roles'])
            ->orderByDesc('last_seen_at');

        $limit = (int) ($validated['limit'] ?? 150);
        $limit = max(1, min(500, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));

        if ($usePagination) {
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $peerCounselors = collect($paginator->items());
        } else {
            $peerCounselors = $query->limit($limit)->get();
        }

        $onlineThreshold = now()->subMinutes((int) env('COUNSELOR_ONLINE_WINDOW_MINUTES', 10));

        $peerCounselors = $peerCounselors->map(function (User $peer) use ($onlineThreshold) {
            $lastSeenAt = $peer->last_seen_at;
            if (is_string($lastSeenAt)) {
                $lastSeenAt = Carbon::parse($lastSeenAt);
            }

            $isOnline = $lastSeenAt instanceof Carbon
                && $lastSeenAt->greaterThanOrEqualTo($onlineThreshold);

            $peer->setAttribute('is_online', $isOnline);
            $peer->setAttribute('is_available', (bool) ($peer->profile?->peer_available ?? true));
            return $peer;
        })->values();

        if ($usePagination) {
            $payload = PaginationPayload::fromPaginator($paginator, $request, []);
            $payload['data'] = $peerCounselors->all();
            return response()->json($payload);
        }

        return response()->json($peerCounselors);
    }

    public function approveCounselor(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $counselor = User::findOrFail($id);
        $counselorRole = $counselor->roles()
            ->whereIn('role', ['counselor', 'peer_counselor'])
            ->first();

        if (!$counselorRole) {
            return response()->json(['message' => 'User is not a staff counselor'], 400);
        }

        if ($counselorRole->approved) {
            return response()->json(['message' => 'Account already approved'], 200);
        }

        $counselorRole->update(['approved' => true]);

        return response()->json([
            'message' => 'Account approved successfully',
            'counselor' => $counselor->load('profile', 'roles'),
        ]);
    }

    public function approveCounselorsBulk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:users,id',
        ]);

        UserRole::whereIn('user_id', $validated['ids'])
            ->whereIn('role', ['counselor', 'peer_counselor'])
            ->update(['approved' => true]);

        $counselors = User::whereIn('id', $validated['ids'])->with(['profile', 'roles'])->get();

        return response()->json([
            'message' => 'Accounts approved successfully',
            'counselors' => $counselors,
        ]);
    }

    public function rejectCounselor(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $counselor = User::findOrFail($id);
        $counselorRole = $counselor->roles()
            ->whereIn('role', ['counselor', 'peer_counselor'])
            ->first();

        if (!$counselorRole) {
            return response()->json(['message' => 'User is not a staff counselor'], 400);
        }

        // Remove the counselor role (soft delete of approval)
        $counselorRole->delete();

        return response()->json([
            'message' => 'Account rejected/removed successfully',
            'counselor' => $counselor->load('profile', 'roles'),
        ]);
    }

    public function destroyCounselor(Request $request, $id): JsonResponse
    {
        $admin = $request->user();
        if (!$admin->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $counselor = User::with('roles')->findOrFail($id);

        if ((int) $counselor->id === (int) $admin->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $hasCounselorRole = $counselor->roles->contains(
            fn ($role) => in_array($role->role, ['counselor', 'peer_counselor'], true)
        );
        if (!$hasCounselorRole) {
            return response()->json(['message' => 'User is not a staff counselor'], 400);
        }

        $hasAdminRole = $counselor->roles->contains(fn ($role) => $role->role === 'admin');
        if ($hasAdminRole) {
            return response()->json([
                'message' => 'Cannot delete an admin account from counselor management.',
            ], 422);
        }

        $email = $counselor->email;
        $counselor->delete();

        return response()->json([
            'message' => 'Counselor account deleted successfully',
            'email' => $email,
        ]);
    }
}
