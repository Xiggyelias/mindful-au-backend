<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\ActivityLog;
use App\Models\CounselingSession;
use App\Models\Escalation;
use App\Models\Notification;
use App\Models\PanicLog;
use App\Models\PeerAssignment;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionController extends Controller
{
    private const ANONYMOUS_SESSION_TTL_HOURS = 24;
    /**
     * Cached risk lookups for the current request to avoid N+1 diagnostics queries.
     *
     * @var array<int, string|null>
     */
    private array $sessionRiskLevelCache = [];

    /**
     * @var array<int, string|null>
     */
    private array $studentRiskLevelCache = [];

    private bool $riskCachePrimed = false;

    public function index(Request $request): JsonResponse
    {
        $this->expireStaleAnonymousSessions();
        $user = $request->user();
        $lightweight = $request->boolean('lightweight');
        $isAdmin = $user->hasRole('admin');
        $isCounselor = !$isAdmin && $user->hasRole('counselor');
        $isPeerCounselor = !$isAdmin && !$isCounselor && $user->hasRole('peer_counselor');
        $isStudent = !$isAdmin && !$isCounselor && !$isPeerCounselor && $user->hasRole('student');
        $scopeRole = $isAdmin
            ? 'admin'
            : ($isCounselor
                ? 'counselor'
                : ($isPeerCounselor ? 'peer_counselor' : 'student'));

        if (!$isAdmin && !$isCounselor && !$isPeerCounselor && !$isStudent) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'session_type' => 'nullable|in:chat,video,voice',
            'status' => 'nullable|in:pending,active,completed,cancelled',
            'open_only' => 'nullable|boolean',
            'as_role' => 'nullable|in:admin,counselor,peer_counselor,student',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $requestedRole = $validated['as_role'] ?? null;
        if ($requestedRole !== null && !$user->hasRole($requestedRole)) {
            return response()->json(['message' => 'Unauthorized for requested role scope'], 403);
        }

        if ($requestedRole !== null) {
            $isAdmin = $requestedRole === 'admin';
            $isCounselor = $requestedRole === 'counselor';
            $isPeerCounselor = $requestedRole === 'peer_counselor';
            $isStudent = $requestedRole === 'student';
        }
        $scopeRole = $isAdmin
            ? 'admin'
            : ($isCounselor
                ? 'counselor'
                : ($isPeerCounselor ? 'peer_counselor' : 'student'));

        $query = CounselingSession::query()->with(
            $lightweight
                ? [
                    'student:id,email,last_seen_at',
                    'student.profile:id,user_id,full_name',
                    'counselor:id,email,last_seen_at',
                    'counselor.profile:id,user_id,full_name',
                    'peerCounselor:id,email,last_seen_at',
                    'peerCounselor.profile:id,user_id,full_name',
                ]
                : [
                    'student.profile',
                    'counselor.profile',
                    'peerCounselor.profile',
                    'assignedByUser.profile',
                    'identityRevealedByUser.profile',
                ]
        );

        if ($lightweight) {
            $query->select([
                'id',
                'student_id',
                'counselor_id',
                'peer_counselor_id',
                'assigned_role',
                'is_anonymous',
                'anonymous_id',
                'status',
                'session_type',
                'created_at',
                'updated_at',
            ]);
        }

        // Role priority matters for multi-role users (e.g., counselor + student).
        if ($isAdmin) {
            // no scope filter
        } elseif ($isCounselor) {
            $query->where('counselor_id', $user->id);
        } elseif ($isPeerCounselor) {
            $query
                ->where('peer_counselor_id', $user->id)
                ->where('assigned_role', 'peer_counselor');
        } elseif ($isStudent) {
            $query->where('student_id', $user->id);
        }

        if (!empty($validated['session_type'])) {
            $query->where('session_type', $validated['session_type']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (($validated['open_only'] ?? false) === true) {
            $query->whereNotIn('status', ['completed', 'cancelled']);
        }

        $query->orderByDesc('updated_at')->orderByDesc('id');

        $limit = !empty($validated['limit']) ? (int) $validated['limit'] : ($lightweight ? 200 : 150);
        $limit = max(1, min(500, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? $limit)));

        $lightweightCacheKey = null;
        $lightweightCacheTtlSeconds = $lightweight
            ? max(0, (int) env('SESSIONS_LIGHTWEIGHT_CACHE_SECONDS', 5))
            : 0;

        if ($lightweight && $lightweightCacheTtlSeconds > 0) {
            $cacheParams = [
                'user_id' => (int) $user->id,
                'scope_role' => $scopeRole,
                'session_type' => $validated['session_type'] ?? null,
                'status' => $validated['status'] ?? null,
                'open_only' => ($validated['open_only'] ?? false) === true,
                'limit' => $limit,
                'use_pagination' => $usePagination,
                'page' => $page,
                'per_page' => $perPage,
            ];

            $canonicalCacheParams = $this->normalizeCachePayload($cacheParams);
            $encodedParams = json_encode($canonicalCacheParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $cacheFingerprint = is_string($encodedParams)
                ? $encodedParams
                : serialize($canonicalCacheParams);
            $lightweightCacheKey = 'sessions:index:lw:v2:' . hash_hmac(
                'sha256',
                $cacheFingerprint,
                (string) config('app.key', 'aucms-cache-key')
            );

            $cachedPayload = Cache::get($lightweightCacheKey);
            if (is_array($cachedPayload)) {
                return response()
                    ->json($cachedPayload)
                    ->header('X-Sessions-Cache', 'hit');
            }
        }

        $paginator = null;
        if ($usePagination) {
            $paginator = (clone $query)
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $sessions = collect($paginator->items());
        } else {
            $query->limit($limit);
            $sessions = $query->get();
        }

        if ($lightweight) {
            $sessions->each(fn (CounselingSession $session) => $this->appendViewerSignals($session, $user));

            $responseBody = [];
            if ($usePagination) {
                $totalRows = max(0, (int) ($paginator?->total() ?? 0));
                $totalPages = max(1, (int) ($paginator?->lastPage() ?? 1));
                $responseBody = [
                    'data' => $sessions,
                    'meta' => [
                        'page' => (int) ($paginator?->currentPage() ?? $page),
                        'per_page' => (int) ($paginator?->perPage() ?? $perPage),
                        'total' => $totalRows,
                        'total_pages' => $totalPages,
                        'has_next' => (bool) ($paginator?->hasMorePages() ?? false),
                        'has_prev' => ((int) ($paginator?->currentPage() ?? $page)) > 1,
                        'filters' => $this->extractSessionListFilters($validated),
                    ],
                    'links' => $this->paginationLinks($paginator),
                ];
            } else {
                $responseBody = $sessions->values()->all();
            }

            if ($usePagination && isset($responseBody['data']) && $responseBody['data'] instanceof \Illuminate\Support\Collection) {
                $responseBody['data'] = $responseBody['data']->values()->all();
            }

            if ($lightweightCacheKey !== null) {
                Cache::put($lightweightCacheKey, $responseBody, now()->addSeconds($lightweightCacheTtlSeconds));
            }

            return response()
                ->json($responseBody)
                ->header('X-Sessions-Cache', $lightweightCacheKey !== null ? 'miss' : 'off');
        }

        $this->primeRiskLevelCache($sessions);
        $sessions->each(fn (CounselingSession $session) => $this->appendRiskSignals($session, $user, $request));

        if ($usePagination) {
            $totalRows = max(0, (int) ($paginator?->total() ?? 0));
            $totalPages = max(1, (int) ($paginator?->lastPage() ?? 1));
            return response()->json([
                'data' => $sessions,
                'meta' => [
                    'page' => (int) ($paginator?->currentPage() ?? $page),
                    'per_page' => (int) ($paginator?->perPage() ?? $perPage),
                    'total' => $totalRows,
                    'total_pages' => $totalPages,
                    'has_next' => (bool) ($paginator?->hasMorePages() ?? false),
                    'has_prev' => ((int) ($paginator?->currentPage() ?? $page)) > 1,
                    'filters' => $this->extractSessionListFilters($validated),
                ],
                'links' => $this->paginationLinks($paginator),
            ]);
        }

        return response()->json($sessions);
    }

    public function chatList(Request $request): JsonResponse
    {
        $this->expireStaleAnonymousSessions();
        $requestStart = microtime(true);
        $user = $request->user();
        $validated = $request->validate([
            'open_only' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
            'as_role' => 'nullable|in:admin,counselor,peer_counselor,student',
            'debug_timing' => 'nullable|boolean',
        ]);

        $isAdmin = $user->hasRole('admin');
        $isCounselor = $user->hasRole('counselor');
        $isPeerCounselor = $user->hasRole('peer_counselor');
        $isStudent = $user->hasRole('student');
        $requestedRole = $validated['as_role'] ?? null;

        if (!$isAdmin && !$isCounselor && !$isPeerCounselor && !$isStudent) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($requestedRole !== null && !$user->hasRole($requestedRole)) {
            return response()->json(['message' => 'Unauthorized for requested role scope'], 403);
        }

        $effectiveRole = $requestedRole;
        if ($effectiveRole === null) {
            if ($isAdmin) {
                $effectiveRole = 'admin';
            } elseif ($isCounselor) {
                $effectiveRole = 'counselor';
            } elseif ($isPeerCounselor) {
                $effectiveRole = 'peer_counselor';
            } elseif ($isStudent) {
                $effectiveRole = 'student';
            }
        }

        $limit = (int) ($validated['limit'] ?? 150);
        $limit = max(1, min(500, $limit));
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = (int) ($validated['per_page'] ?? $limit);
        $perPage = max(1, min(200, $perPage));
        $openOnly = array_key_exists('open_only', $validated) ? (bool) $validated['open_only'] : true;

        $scopedSessionQuery = DB::table('counseling_sessions as s')
            ->where('s.session_type', 'chat');

        if ($effectiveRole === 'admin') {
            // no scope restriction
        } elseif ($effectiveRole === 'counselor') {
            $scopedSessionQuery->where('s.counselor_id', $user->id);
        } elseif ($effectiveRole === 'peer_counselor') {
            $scopedSessionQuery->where('s.peer_counselor_id', $user->id)
                ->where('s.assigned_role', 'peer_counselor');
        } else {
            $scopedSessionQuery->where('s.student_id', $user->id);
        }

        if ($openOnly) {
            $scopedSessionQuery->whereNotIn('s.status', ['completed', 'cancelled']);
        }

        $viewerId = (int) $user->id;

        $queryStart = microtime(true);
        $total = null;
        $rowsPaginator = null;

        // Single aggregated scan for unread counts instead of a correlated subquery per row
        // (N sessions × messages count-subquery was a major latency source on counselor chat list).
        $unreadAggregates = DB::table('messages')
            ->select('session_id', DB::raw('COUNT(*) as unread_count'))
            ->where('recipient_id', $viewerId)
            ->whereNull('seen_at')
            ->groupBy('session_id');

        $orderedQuery = (clone $scopedSessionQuery)
            ->leftJoinSub($unreadAggregates, 'unread_agg', function ($join): void {
                $join->on('unread_agg.session_id', '=', 's.id');
            })
            ->leftJoin('users as student', 'student.id', '=', 's.student_id')
            ->leftJoin('profiles as student_profile', 'student_profile.user_id', '=', 's.student_id')
            ->leftJoin('users as peer', 'peer.id', '=', 's.peer_counselor_id')
            ->leftJoin('profiles as peer_profile', 'peer_profile.user_id', '=', 's.peer_counselor_id')
            ->select([
                's.id',
                's.student_id',
                's.counselor_id',
                's.peer_counselor_id',
                's.assigned_role',
                's.session_type',
                's.status',
                's.is_anonymous',
                's.anonymous_id',
                's.identity_revealed_at',
                's.created_at',
                's.updated_at',
                'student.email as student_email',
                'student.last_seen_at as student_last_seen_at',
                'student_profile.full_name as student_full_name',
                'peer.email as peer_email',
                'peer_profile.full_name as peer_full_name',
                DB::raw('COALESCE(unread_agg.unread_count, 0) as unread_count'),
            ])
            ->orderByDesc('s.updated_at')
            ->orderByDesc('s.id');

        if (!$usePagination) {
            $orderedQuery->limit($limit);
        }

        if ($usePagination) {
            $rowsPaginator = $orderedQuery
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());
            $rows = collect($rowsPaginator->items());
            $total = (int) $rowsPaginator->total();
        } else {
            $rows = $orderedQuery->get();
        }
        $queryDurationMs = (microtime(true) - $queryStart) * 1000;

        $viewerIsAdmin = $isAdmin;
        $studentOnlineThreshold = now()->subMinutes(
            max(1, (int) env('CHAT_PARTICIPANT_ONLINE_WINDOW_MINUTES', 10))
        );

        $transformStart = microtime(true);
        $sessions = $rows->map(function ($row) use ($viewerId, $viewerIsAdmin, $studentOnlineThreshold) {
            $isAnonymous = (bool) $row->is_anonymous;
            $dbAnonymousId = trim((string) ($row->anonymous_id ?? ''));

            $identityVisible = !$isAnonymous
                || (int) $row->student_id === $viewerId
                || (
                    !empty($row->identity_revealed_at)
                    && (
                        $viewerIsAdmin
                        || (int) $row->counselor_id === $viewerId
                    )
                );
            $visibleStudentId = $identityVisible ? (int) $row->student_id : 0;

            $studentName = $identityVisible
                ? (
                    trim((string) ($row->student_full_name ?? '')) !== ''
                    ? (string) $row->student_full_name
                    : (
                        trim((string) ($row->student_email ?? '')) !== ''
                        ? Str::before((string) $row->student_email, '@')
                        : 'Student #' . (int) $row->student_id
                    )
                )
                : 'Anonymous User';

            $anonymousIdForPayload = ($isAnonymous && $identityVisible && $dbAnonymousId !== '')
                ? $dbAnonymousId
                : null;

            $studentLastSeenAt = null;
            if (!empty($row->student_last_seen_at)) {
                try {
                    $studentLastSeenAt = Carbon::parse((string) $row->student_last_seen_at);
                } catch (\Throwable) {
                    $studentLastSeenAt = null;
                }
            }
            $studentIsOnline = $studentLastSeenAt instanceof Carbon
                && $studentLastSeenAt->greaterThanOrEqualTo($studentOnlineThreshold);

            return [
                'id' => (int) $row->id,
                'student_id' => $visibleStudentId,
                // Real DB student id for WebCrypto routing when viewer sees student_id = 0 (anonymous masked).
                'chat_peer_student_id' => $isAnonymous && (int) $row->student_id > 0
                    ? (int) $row->student_id
                    : null,
                'counselor_id' => $row->counselor_id ? (int) $row->counselor_id : null,
                'peer_counselor_id' => $row->peer_counselor_id ? (int) $row->peer_counselor_id : null,
                'assigned_role' => $row->assigned_role,
                'session_type' => $row->session_type,
                'status' => $row->status,
                'is_anonymous' => $isAnonymous,
                'anonymous_id' => $anonymousIdForPayload,
                'identity_visible_to_viewer' => $identityVisible,
                'created_at' => ! empty($row->created_at)
                    ? Carbon::parse((string) $row->created_at)->toIso8601String()
                    : null,
                'updated_at' => ! empty($row->updated_at)
                    ? Carbon::parse((string) $row->updated_at)->toIso8601String()
                    : null,
                'unread_count' => max(0, (int) ($row->unread_count ?? 0)),
                'student' => [
                    'id' => $visibleStudentId,
                    'email' => $identityVisible ? $row->student_email : null,
                    'last_seen_at' => $studentLastSeenAt?->toIso8601String(),
                    'is_online' => $studentIsOnline,
                    'profile' => [
                        'full_name' => $studentName,
                    ],
                ],
                'peer_counselor' => $row->peer_counselor_id ? [
                    'id' => (int) $row->peer_counselor_id,
                    'email' => $row->peer_email,
                    'profile' => [
                        'full_name' => $row->peer_full_name,
                    ],
                ] : null,
            ];
        })->values();
        $transformDurationMs = (microtime(true) - $transformStart) * 1000;
        $totalDurationMs = (microtime(true) - $requestStart) * 1000;

        $debugTiming = (bool) ($validated['debug_timing'] ?? false);
        $slowThresholdMs = max(100, (int) env('CHAT_LIST_SLOW_LOG_MS', 1500));
        if ($debugTiming || $totalDurationMs >= $slowThresholdMs) {
            Log::info('chat-list timing', [
                'user_id' => (int) $user->id,
                'effective_role' => $effectiveRole,
                'open_only' => $openOnly,
                'limit' => $limit,
                'use_pagination' => $usePagination,
                'page' => $usePagination ? $page : null,
                'per_page' => $usePagination ? $perPage : null,
                'total' => $total,
                'rows' => $rows->count(),
                'query_ms' => round($queryDurationMs, 2),
                'transform_ms' => round($transformDurationMs, 2),
                'total_ms' => round($totalDurationMs, 2),
            ]);
        }

        $responseBody = $sessions;
        if ($usePagination) {
            $totalRows = max(0, (int) ($total ?? 0));
            $currentPage = (int) ($rowsPaginator?->currentPage() ?? $page);
            $effectivePerPage = (int) ($rowsPaginator?->perPage() ?? $perPage);
            $totalPages = max(1, (int) ($rowsPaginator?->lastPage() ?? 1));
            $responseBody = [
                'data' => $sessions,
                'meta' => [
                    'page' => $currentPage,
                    'per_page' => $effectivePerPage,
                    'total' => $totalRows,
                    'total_pages' => $totalPages,
                    'has_next' => (bool) ($rowsPaginator?->hasMorePages() ?? false),
                    'has_prev' => $currentPage > 1,
                    'filters' => $this->extractChatListFilters($validated, $effectiveRole, $openOnly),
                ],
                'links' => $this->paginationLinks($rowsPaginator),
            ];
        }

        $response = response()->json($responseBody)
            ->header('X-Chat-List-Total-Ms', (string) round($totalDurationMs, 2))
            ->header('X-Chat-List-Query-Ms', (string) round($queryDurationMs, 2))
            ->header('X-Chat-List-Transform-Ms', (string) round($transformDurationMs, 2))
            ->header('X-Chat-List-Count', (string) $rows->count());

        if ($usePagination) {
            $currentPage = (int) ($rowsPaginator?->currentPage() ?? $page);
            $effectivePerPage = (int) ($rowsPaginator?->perPage() ?? $perPage);
            $response
                ->header('X-Chat-List-Page', (string) $currentPage)
                ->header('X-Chat-List-Per-Page', (string) $effectivePerPage)
                ->header('X-Chat-List-Total', (string) max(0, (int) ($total ?? 0)));
        }

        return $response;
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can create sessions'], 403);
        }

        $validated = $request->validate([
            'counselor_id' => 'required|exists:users,id',
            'session_type' => 'required|in:chat,video,voice',
            'is_anonymous' => 'sometimes|boolean',
        ]);

        if (!$this->isApprovedCounselor((int) $validated['counselor_id'])) {
            return response()->json(['message' => 'Selected counselor is not available'], 422);
        }

        if ((int) $validated['counselor_id'] === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot start a session with your own account'], 422);
        }

        $isAnonymous = array_key_exists('is_anonymous', $validated)
            ? (bool) $validated['is_anonymous']
            : (bool) ($request->user()->profile?->anonymous_mode ?? false);

        $existing = CounselingSession::where('student_id', $request->user()->id)
            ->where('counselor_id', $validated['counselor_id'])
            ->where('session_type', $validated['session_type'])
            ->where('is_anonymous', $isAnonymous)
            ->where(function ($query) {
                $query->whereNull('assigned_role')
                    ->orWhere('assigned_role', 'counselor');
            })
            ->whereIn('status', ['pending', 'active'])
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->load(['student.profile', 'counselor.profile', 'peerCounselor.profile']);
            $this->appendRiskSignals($existing, $request->user(), $request);
            return response()->json($existing);
        }

        // Student requests remain pending until reviewed by a professional counselor.
        $session = CounselingSession::create([
            'student_id' => $request->user()->id,
            'counselor_id' => $validated['counselor_id'],
            'session_type' => $validated['session_type'],
            'status' => 'pending',
            'assigned_role' => 'counselor',
            'assigned_by' => null,
            'is_anonymous' => $isAnonymous,
            'anonymous_id' => $isAnonymous ? $this->generateAnonymousId() : null,
            'identity_revealed_at' => null,
            'identity_revealed_by' => null,
        ]);

        $session->load(['student.profile', 'counselor.profile', 'peerCounselor.profile']);
        $this->appendRiskSignals($session, $request->user(), $request);

        return response()->json($session, 201);
    }

    /**
     * Student: update anonymity for one open chat session (chat-level flag).
     * Also aligns profile anonymous_mode so dashboard and new chats stay consistent.
     */
    public function updateChatAnonymity(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('student')) {
            return response()->json(['message' => 'Only students can update chat anonymity'], 403);
        }

        $session = CounselingSession::query()->findOrFail($id);

        if ((int) $session->student_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($session->session_type !== 'chat') {
            return response()->json(['message' => 'Only chat sessions support anonymity settings'], 422);
        }

        if (! in_array((string) $session->status, ['pending', 'active'], true)) {
            return response()->json(['message' => 'This conversation is closed and cannot be edited'], 422);
        }

        $validated = $request->validate([
            'is_anonymous' => 'required|boolean',
        ]);

        $isAnonymous = (bool) $validated['is_anonymous'];

        $session->is_anonymous = $isAnonymous;
        if ($isAnonymous) {
            if ($session->anonymous_id === null || trim((string) $session->anonymous_id) === '') {
                $session->anonymous_id = CounselingSession::generateUniqueAnonymousId();
            }
        } else {
            $session->anonymous_id = null;
        }
        $session->save();

        $profile = $user->profile;
        if ($profile) {
            $profile->forceFill(['anonymous_mode' => $isAnonymous])->save();
        }

        $session->load(['student.profile', 'counselor.profile', 'peerCounselor.profile']);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session);
    }

    public function storeAsCounselor(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can create student sessions'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'session_type' => 'required|in:chat,video,voice',
        ]);

        if (!$this->isApprovedStudent((int) $validated['student_id'])) {
            return response()->json(['message' => 'Selected student is not available'], 422);
        }

        $existing = CounselingSession::where('student_id', $validated['student_id'])
            ->where('counselor_id', $user->id)
            ->where('session_type', $validated['session_type'])
            ->where('is_anonymous', false)
            ->where(function ($query) {
                $query->whereNull('assigned_role')
                    ->orWhere('assigned_role', 'counselor');
            })
            ->whereIn('status', ['pending', 'active'])
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->load(['student.profile', 'counselor.profile', 'peerCounselor.profile']);
            $this->appendRiskSignals($existing, $user, $request);
            return response()->json($existing);
        }

        $session = CounselingSession::create([
            'student_id' => $validated['student_id'],
            'counselor_id' => $user->id,
            'session_type' => $validated['session_type'],
            'status' => 'pending',
            'assigned_role' => 'counselor',
            'assigned_by' => $user->id,
            'is_anonymous' => false,
            'anonymous_id' => null,
            'identity_revealed_at' => null,
            'identity_revealed_by' => null,
        ]);

        $session->load(['student.profile', 'counselor.profile', 'peerCounselor.profile']);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session, 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        // ?minimal=1 is a fast-path used by the E2E chat bootstrap to resolve peer IDs only.
        // It skips the expensive anonymous-session expiry scan and all relation eager loads.
        if ($request->boolean('minimal')) {
            $session = CounselingSession::query()
                ->select([
                    'id',
                    'student_id',
                    'counselor_id',
                    'peer_counselor_id',
                    'assigned_role',
                    'is_anonymous',
                    'status',
                ])
                ->findOrFail($id);

            if (!$this->canViewSession($request->user(), $session)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // chat_peer_student_id mirrors the chatList projection so the E2E hook can
            // resolve the real student DB id even when student_id is masked (anonymous mode).
            return response()->json([
                'id'                    => (int) $session->id,
                'student_id'            => (int) $session->student_id,
                'chat_peer_student_id'  => (int) $session->student_id > 0 ? (int) $session->student_id : null,
                'counselor_id'          => $session->counselor_id ? (int) $session->counselor_id : null,
                'peer_counselor_id'     => $session->peer_counselor_id ? (int) $session->peer_counselor_id : null,
                'assigned_role'         => $session->assigned_role,
                'is_anonymous'          => (bool) $session->is_anonymous,
                'status'                => $session->status,
            ]);
        }

        $this->expireStaleAnonymousSessions();
        $session = CounselingSession::with([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ])->findOrFail($id);

        if (!$this->canViewSession($request->user(), $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->appendRiskSignals($session, $request->user(), $request);
        return response()->json($session);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $session = CounselingSession::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('admin') && (int) $session->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,active,completed,cancelled',
            'counselor_id' => 'sometimes|exists:users,id',
            'notes' => 'sometimes|string|max:5000',
            'ai_summary' => 'sometimes|string|max:20000',
        ]);

        if (!$user->hasRole('admin') && array_key_exists('counselor_id', $validated)) {
            return response()->json(['message' => 'Only admins can reassign counselors'], 403);
        }

        if (
            array_key_exists('counselor_id', $validated)
            && !$this->isApprovedCounselor((int) $validated['counselor_id'])
        ) {
            return response()->json(['message' => 'Selected counselor is not available'], 422);
        }

        if ($user->hasRole('admin') && array_key_exists('notes', $validated)) {
            return response()->json([
                'message' => 'Admins are not allowed to edit confidential counseling notes.',
            ], 403);
        }

        if (array_key_exists('counselor_id', $validated)) {
            // Professional reassignment clears peer assignment for safety.
            $validated['peer_counselor_id'] = null;
            $validated['assigned_role'] = 'counselor';
            $validated['assigned_by'] = $user->id;
        }

        $session->update($validated);

        if (($validated['status'] ?? null) === 'active' && !$session->started_at) {
            $session->update(['started_at' => now()]);
        }

        if (($validated['status'] ?? null) === 'completed' && !$session->ended_at) {
            $session->update(['ended_at' => now()]);

            // Automatically trigger AI analysis when session is completed.
            $messages = \App\Models\Message::where('session_id', $session->id)
                ->orderBy('created_at')
                ->get()
                ->map(function ($msg) use ($session) {
                    return [
                        'sender' => $msg->sender_id === $session->student_id ? 'student' : 'counselor',
                        'content' => $msg->content,
                    ];
                })
                ->toArray();

            if (!empty($messages) && SystemSettings::getBool('ai_auto_analysis', true)) {
                \App\Jobs\ProcessAIDiagnostic::dispatch($session, $messages);
            }
        }

        $session->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = CounselingSession::query()->findOrFail($id);
        $user = $request->user();

        $canDelete = $user->hasRole('admin')
            || ($user->hasRole('counselor') && (int) $session->counselor_id === (int) $user->id)
            || ($user->hasRole('student') && (int) $session->student_id === (int) $user->id);

        if (!$canDelete) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->delete();

        return response()->json(['message' => 'Session deleted successfully']);
    }

    public function upsertNote(Request $request, string $id): JsonResponse
    {
        $session = CounselingSession::with([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ])->findOrFail($id);
        $user = $request->user();

        if (!$this->canManageSessionNotes($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:5000',
        ]);

        $notes = trim((string) $validated['notes']);
        if ($notes === '') {
            return response()->json(['message' => 'Note content cannot be empty'], 422);
        }

        $session->update(['notes' => $notes]);
        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json([
            'message' => 'Session note saved',
            'session' => $session,
        ]);
    }

    public function deleteNote(Request $request, string $id): JsonResponse
    {
        $session = CounselingSession::with([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ])->findOrFail($id);
        $user = $request->user();

        if (!$this->canManageSessionNotes($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $session->update(['notes' => null]);
        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json([
            'message' => 'Session note deleted',
            'session' => $session,
        ]);
    }

    public function assignPeerCounselor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can assign peer counselors'], 403);
        }

        $session = CounselingSession::with(['student.profile', 'counselor.profile', 'peerCounselor.profile'])
            ->findOrFail($id);

        if ((int) $session->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only assign your own student cases'], 403);
        }

        if (in_array((string) $session->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Closed cases cannot be assigned to peer counselors'], 422);
        }

        if ($session->session_type !== 'chat') {
            return response()->json(['message' => 'Peer counselors are limited to chat cases'], 422);
        }

        $validated = $request->validate([
            'peer_counselor_id' => 'required|integer|exists:users,id',
        ]);

        $peerCounselorId = (int) $validated['peer_counselor_id'];
        if (!$this->isApprovedPeerCounselor($peerCounselorId)) {
            return response()->json(['message' => 'Selected peer counselor is not available'], 422);
        }
        $peerCounselor = User::query()->with('profile')->find($peerCounselorId);

        $riskLevel = $this->latestRiskLevel($session);
        if ($riskLevel !== null && $riskLevel !== 'low') {
            return response()->json([
                'message' => 'Only low-risk cases can be assigned to peer counselors.',
                'risk_level' => $riskLevel,
            ], 422);
        }

        // Keep counselor and peer counselor threads separate:
        // create/reuse a dedicated peer support session instead of converting
        // the original counselor-owned thread.
        $shouldSplitToDedicatedPeerSession = $session->assigned_role !== 'peer_counselor';
        $targetSession = $session;

        if ($shouldSplitToDedicatedPeerSession) {
            $existingPeerSession = CounselingSession::query()
                ->where('student_id', $session->student_id)
                ->where('counselor_id', $session->counselor_id)
                ->where('session_type', 'chat')
                ->where('is_anonymous', (bool) $session->is_anonymous)
                ->where('assigned_role', 'peer_counselor')
                ->whereIn('status', ['pending', 'active'])
                ->latest('id')
                ->first();

            if ($existingPeerSession) {
                $existingPeerSession->update([
                    'peer_counselor_id' => $peerCounselorId,
                    'assigned_by' => $user->id,
                    'assigned_role' => 'peer_counselor',
                ]);
                $targetSession = $existingPeerSession;
            } else {
                $targetSession = CounselingSession::query()->create([
                    'student_id' => $session->student_id,
                    'counselor_id' => $session->counselor_id,
                    'peer_counselor_id' => $peerCounselorId,
                    'assigned_by' => $user->id,
                    'assigned_role' => 'peer_counselor',
                    'is_anonymous' => (bool) $session->is_anonymous,
                    'anonymous_id' => $session->is_anonymous ? $this->generateAnonymousId() : null,
                    'identity_revealed_at' => null,
                    'identity_revealed_by' => null,
                    'status' => in_array((string) $session->status, ['pending', 'active'], true)
                        ? $session->status
                        : 'pending',
                    'session_type' => 'chat',
                ]);
            }
        } else {
            $session->update([
                'peer_counselor_id' => $peerCounselorId,
                'assigned_by' => $user->id,
                'assigned_role' => 'peer_counselor',
            ]);
            $targetSession = $session;
        }

        PeerAssignment::query()
            ->where('session_id', $targetSession->id)
            ->where('status', 'active')
            ->update([
                'status' => 'reassigned',
                'unassigned_at' => now(),
            ]);

        PeerAssignment::query()->create([
            'session_id' => $targetSession->id,
            'peer_counselor_id' => $peerCounselorId,
            'assigned_by' => $user->id,
            'status' => 'active',
            'assigned_at' => now(),
            'notes' => 'Assigned by counselor',
        ]);

        $this->logCaseTransition(
            $request,
            'case_assigned_to_peer_counselor',
            "Counselor {$user->id} assigned session {$targetSession->id} to peer counselor {$peerCounselorId}.",
            $targetSession,
            $peerCounselorId,
            [
                'risk_level' => $riskLevel,
                'source_session_id' => $session->id,
                'dedicated_peer_session' => $shouldSplitToDedicatedPeerSession,
            ]
        );

        $studentLabel = $targetSession->is_anonymous
            ? $this->resolveAnonymousDisplayId($targetSession)
            : (
                $targetSession->student?->profile?->full_name
                ?: ($targetSession->student?->email ? Str::before((string) $targetSession->student->email, '@') : "Student #{$targetSession->student_id}")
            );
        $peerCounselorLabel = $peerCounselor?->profile?->full_name
            ?: ($peerCounselor?->email ? Str::before((string) $peerCounselor->email, '@') : 'Peer counselor');

        Notification::query()->create([
            'user_id' => $peerCounselorId,
            'title' => 'New peer support assignment',
            'message' => "{$studentLabel} has been assigned to you for peer support. Open Messages to start chat.",
            'type' => 'info',
        ]);

        Notification::query()->create([
            'user_id' => $targetSession->student_id,
            'title' => 'Case assigned for peer support',
            'message' => "Your counselor assigned {$peerCounselorLabel} as your supervised peer counselor for this chat case.",
            'type' => 'info',
        ]);

        $targetSession->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($targetSession, $user, $request);

        return response()->json($targetSession);
    }

    public function unassignPeerCounselor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can remove peer assignments'], 403);
        }

        $session = CounselingSession::with(['student.profile', 'counselor.profile', 'peerCounselor.profile'])
            ->findOrFail($id);

        if ((int) $session->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'You can only manage your own student cases'], 403);
        }

        if ($session->session_type !== 'chat') {
            return response()->json(['message' => 'Peer counselor delegation is supported for chat cases only'], 422);
        }

        $peerCounselorId = (int) ($session->peer_counselor_id ?? 0);
        $isPeerAssigned = $session->assigned_role === 'peer_counselor' && $peerCounselorId > 0;
        if (!$isPeerAssigned) {
            return response()->json(['message' => 'This case is not currently assigned to a peer counselor'], 422);
        }

        $peerCounselor = User::query()->with('profile')->find($peerCounselorId);

        $session->update([
            'peer_counselor_id' => null,
            'assigned_by' => $user->id,
            'assigned_role' => 'counselor',
            'status' => in_array((string) $session->status, ['completed', 'cancelled'], true)
                ? $session->status
                : 'pending',
        ]);

        PeerAssignment::query()
            ->where('session_id', $session->id)
            ->where('peer_counselor_id', $peerCounselorId)
            ->where('status', 'active')
            ->update([
                'status' => 'closed',
                'unassigned_at' => now(),
                'notes' => 'Unassigned by counselor',
            ]);

        $peerCounselorLabel = $peerCounselor?->profile?->full_name
            ?: ($peerCounselor?->email ? Str::before((string) $peerCounselor->email, '@') : "Peer #{$peerCounselorId}");

        $this->logCaseTransition(
            $request,
            'peer_counselor_unassigned_by_counselor',
            "Counselor {$user->id} removed peer counselor {$peerCounselorId} from session {$session->id}.",
            $session,
            $peerCounselorId,
            [
                'assigned_role_before' => 'peer_counselor',
                'assigned_role_after' => 'counselor',
            ]
        );

        if ($peerCounselorId > 0) {
            Notification::query()->create([
                'user_id' => $peerCounselorId,
                'title' => 'Peer support assignment removed',
                'message' => 'A counselor has removed this case from your peer support queue.',
                'type' => 'info',
            ]);
        }

        Notification::query()->create([
            'user_id' => $session->student_id,
            'title' => 'Counselor resumed direct support',
            'message' => "{$peerCounselorLabel} has been removed from your peer support case. Your counselor will continue with you directly.",
            'type' => 'info',
        ]);

        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session);
    }

    public function escalateToCounselor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Only peer counselors can escalate assigned cases'], 403);
        }

        $session = CounselingSession::with(['student.profile', 'counselor.profile', 'peerCounselor.profile'])
            ->findOrFail($id);

        $isAssignedPeer = (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor';
        if (!$isAssignedPeer) {
            return response()->json(['message' => 'You can only escalate cases currently assigned to you'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $escalationReason = trim((string) ($validated['reason'] ?? ''));

        $session->update([
            'peer_counselor_id' => null,
            'assigned_by' => $user->id,
            'assigned_role' => 'counselor',
            'status' => in_array((string) $session->status, ['completed', 'cancelled'], true)
                ? $session->status
                : 'pending',
        ]);

        PeerAssignment::query()
            ->where('session_id', $session->id)
            ->where('peer_counselor_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'escalated',
                'unassigned_at' => now(),
            ]);

        Escalation::query()->create([
            'session_id' => $session->id,
            'escalated_by' => $user->id,
            'escalated_to' => $session->counselor_id,
            'escalation_type' => 'peer_to_counselor',
            'severity' => 'high',
            'reason' => $escalationReason !== '' ? $escalationReason : null,
            'metadata' => [
                'source' => 'peer_dashboard',
                'assigned_role_before' => 'peer_counselor',
            ],
        ]);

        $this->logCaseTransition(
            $request,
            'peer_case_escalated_to_counselor',
            "Peer counselor {$user->id} escalated session {$session->id} back to counselor {$session->counselor_id}.",
            $session,
            $session->counselor_id,
            ['reason' => $escalationReason !== '' ? $escalationReason : null]
        );

        if ($session->counselor_id) {
            Notification::query()->create([
                'user_id' => $session->counselor_id,
                'title' => 'Case escalated by peer counselor',
                'message' => $escalationReason !== ''
                    ? "A peer counselor escalated a case back to you: {$escalationReason}"
                    : 'A peer counselor escalated a case back to you for immediate review.',
                'type' => 'warning',
            ]);
        }

        Notification::query()->create([
            'user_id' => $session->student_id,
            'title' => 'Case escalated to counselor',
            'message' => 'Your case was escalated to a professional counselor for follow-up.',
            'type' => 'info',
        ]);

        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session);
    }

    public function flagUrgentConcern(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('peer_counselor')) {
            return response()->json(['message' => 'Only peer counselors can flag urgent concerns'], 403);
        }

        $session = CounselingSession::with(['student.profile', 'counselor.profile', 'peerCounselor.profile'])
            ->findOrFail($id);

        $isAssignedPeer = (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor';
        if (!$isAssignedPeer) {
            return response()->json(['message' => 'You can only flag urgent concerns on your assigned cases'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $reason = trim((string) $validated['reason']);
        if ($reason === '') {
            return response()->json(['message' => 'Urgent reason is required'], 422);
        }

        $session->update([
            'peer_counselor_id' => null,
            'assigned_by' => $user->id,
            'assigned_role' => 'counselor',
            'status' => in_array((string) $session->status, ['completed', 'cancelled'], true)
                ? $session->status
                : 'pending',
        ]);

        PeerAssignment::query()
            ->where('session_id', $session->id)
            ->where('peer_counselor_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'escalated',
                'unassigned_at' => now(),
                'notes' => 'Urgent concern flagged by peer counselor',
            ]);

        Escalation::query()->create([
            'session_id' => $session->id,
            'escalated_by' => $user->id,
            'escalated_to' => $session->counselor_id,
            'escalation_type' => 'urgent_flag',
            'severity' => 'critical',
            'reason' => $reason,
            'metadata' => [
                'source' => 'peer_dashboard',
                'assigned_role_before' => 'peer_counselor',
            ],
        ]);

        $this->logCaseTransition(
            $request,
            'peer_flagged_urgent_concern',
            "Peer counselor {$user->id} flagged urgent concern on session {$session->id}.",
            $session,
            $session->counselor_id,
            ['reason' => $reason, 'severity' => 'critical']
        );

        if ($session->counselor_id) {
            Notification::query()->create([
                'user_id' => $session->counselor_id,
                'title' => 'Urgent concern flagged by peer counselor',
                'message' => "Immediate review required: {$reason}",
                'type' => 'panic',
            ]);
        }

        Notification::query()->create([
            'user_id' => $session->student_id,
            'title' => 'Your case was escalated',
            'message' => 'Your peer support case was escalated to a professional counselor for urgent follow-up.',
            'type' => 'warning',
        ]);

        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json($session);
    }

    public function panicEscalation(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = CounselingSession::with([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ])->findOrFail($id);

        if (!$this->canViewSession($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
        ]);

        $reason = trim((string) ($validated['reason'] ?? ''));
        $location = trim((string) ($validated['location'] ?? ''));
        $panicLocation = $location !== ''
            ? "session:{$session->id} | {$location}"
            : "session:{$session->id}";

        $panicLog = PanicLog::query()->create([
            'student_id' => $session->student_id,
            'location' => $panicLocation,
            'resolved' => false,
        ]);

        // Immediate counselor handover for emergency scenarios.
        if (
            $user->hasRole('peer_counselor')
            && (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor'
        ) {
            $session->update([
                'peer_counselor_id' => null,
                'assigned_by' => $user->id,
                'assigned_role' => 'counselor',
                'status' => in_array((string) $session->status, ['completed', 'cancelled'], true)
                    ? $session->status
                    : 'pending',
            ]);

            PeerAssignment::query()
                ->where('session_id', $session->id)
                ->where('peer_counselor_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'escalated',
                    'unassigned_at' => now(),
                    'notes' => 'Escalated by panic event',
                ]);
        }

        Escalation::query()->create([
            'session_id' => $session->id,
            'escalated_by' => $user->id,
            'escalated_to' => $session->counselor_id,
            'escalation_type' => 'panic',
            'severity' => 'critical',
            'reason' => $reason !== '' ? $reason : 'Panic escalation triggered',
            'metadata' => [
                'panic_log_id' => $panicLog->id,
            ],
        ]);

        if ($session->is_anonymous && !$session->identity_revealed_at && $session->counselor_id) {
            $counselor = User::query()->find($session->counselor_id);
            $this->revealAnonymousIdentity(
                $request,
                $session,
                $counselor,
                'panic_escalation',
                'high'
            );
        }

        $this->logCaseTransition(
            $request,
            'anonymous_panic_escalation',
            "Emergency escalation triggered for session {$session->id}.",
            $session,
            $session->counselor_id,
            [
                'panic_log_id' => $panicLog->id,
                'reason' => $reason !== '' ? $reason : null,
            ]
        );

        if (SystemSettings::getBool('panic_alerts', true)) {
            // Professional counselors + admins — not peer counselors (student "need help"
            // escalations route to clinic staff only).
            $recipientIds = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where(function ($inner) {
                        $inner->where(function ($scoped) {
                            $scoped->where('role', 'counselor')
                                ->where('approved', true);
                        })->orWhere('role', 'admin');
                    });
                })
                ->pluck('id')
                ->unique()
                ->values();

            $peerCounselorId = (int) ($session->peer_counselor_id ?? 0);
            $emergencyMessage = $this->buildEmergencyMessage($session, $reason);

            foreach ($recipientIds as $recipientId) {
                if ($peerCounselorId > 0 && (int) $recipientId === $peerCounselorId) {
                    continue;
                }
                try {
                    $notification = Notification::query()->create([
                        'user_id' => (int) $recipientId,
                        'title' => 'Emergency escalation',
                        'message' => $emergencyMessage,
                        'type' => 'panic',
                        'read' => false,
                    ]);

                    try {
                        event(new \App\Events\NotificationCreated($notification));
                    } catch (\Throwable $broadcastException) {
                        Log::warning(
                            'Session panic notification broadcast failed',
                            [
                                'session_id' => $session->id,
                                'panic_log_id' => $panicLog->id,
                                'recipient_id' => (int) $recipientId,
                                'error' => $broadcastException->getMessage(),
                            ]
                        );
                    }
                } catch (\Throwable $createException) {
                    Log::error(
                        'Failed to create session panic notification',
                        [
                            'session_id' => $session->id,
                            'panic_log_id' => $panicLog->id,
                            'recipient_id' => (int) $recipientId,
                            'error' => $createException->getMessage(),
                        ]
                    );
                }
            }
        }

        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);
        $session->setAttribute('panic_log_id', $panicLog->id);

        return response()->json($session);
    }

    public function revealIdentity(Request $request, string $id): JsonResponse
    {
        $session = CounselingSession::with([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ])->findOrFail($id);
        $user = $request->user();

        if (!$this->canViewSession($user, $session)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$session->is_anonymous) {
            return response()->json(['message' => 'Identity reveal is only available for anonymous sessions.'], 422);
        }

        if (
            !$user->hasRole('admin')
            && !($user->hasRole('counselor') && (int) $session->counselor_id === (int) $user->id)
        ) {
            return response()->json(['message' => 'Only authorized counselors or admins can reveal identity.'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);
        $reason = trim((string) $validated['reason']);

        $this->revealAnonymousIdentity(
            $request,
            $session,
            $user,
            'manual_authorized_reveal',
            null
        );

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'anonymous_identity_manual_reveal',
            'description' => "Authorized identity reveal for session {$session->id}.",
            'type' => 'alert',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'session_id' => $session->id,
                'reason' => $reason,
                'student_id' => $session->student_id,
                'revealed_by' => $user->id,
                'anonymous_id' => $session->anonymous_id,
            ],
        ]);

        $session->refresh()->load([
            'student.profile',
            'counselor.profile',
            'peerCounselor.profile',
            'assignedByUser.profile',
            'identityRevealedByUser.profile',
        ]);
        $this->appendRiskSignals($session, $user, $request);

        return response()->json([
            'message' => 'Identity revealed and access logged.',
            'session' => $session,
        ]);
    }

    private function canViewSession(User $user, CounselingSession $session): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ((int) $session->student_id === (int) $user->id) {
            return true;
        }

        if ((int) $session->counselor_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('peer_counselor')
            && (int) $session->peer_counselor_id === (int) $user->id
            && $session->assigned_role === 'peer_counselor';
    }

    private function canManageSessionNotes(User $user, CounselingSession $session): bool
    {
        return $user->hasRole('counselor')
            && (int) $session->counselor_id === (int) $user->id;
    }

    private function appendRiskSignals(
        CounselingSession $session,
        ?User $viewer = null,
        ?Request $request = null
    ): void {
        $riskLevel = $this->latestRiskLevel($session);

        if ($viewer && $request) {
            $this->maybeAutoRevealAnonymousIdentity($session, $viewer, $request, $riskLevel);
        }

        $session->setAttribute('current_risk_level', $riskLevel);
        $session->setAttribute('is_low_risk', $riskLevel === 'low');
        $session->setAttribute('is_peer_assigned', $session->assigned_role === 'peer_counselor');
        $session->setAttribute('anonymous_display_id', $this->resolveAnonymousDisplayId($session));

        if ($viewer) {
            $identityVisible = $this->canViewerSeeAnonymousIdentity($viewer, $session);
            $session->setAttribute('identity_visible_to_viewer', $identityVisible);
            // Preserve the real student user id for E2E/chat routing when anonymous projection masks student_id to 0.
            if ($session->is_anonymous) {
                $session->setAttribute('chat_peer_student_id', (int) $session->student_id);
            }
            $this->applyAnonymousProjection($session, $viewer, $identityVisible);
            if ($session->is_anonymous && !$identityVisible) {
                $session->setAttribute('anonymous_id', null);
            }
            $this->redactConfidentialNotesForViewer($session, $viewer);
        } else {
            $session->setAttribute('identity_visible_to_viewer', true);
        }
    }

    private function appendViewerSignals(
        CounselingSession $session,
        ?User $viewer = null
    ): void {
        $session->setAttribute('is_peer_assigned', $session->assigned_role === 'peer_counselor');
        $session->setAttribute('anonymous_display_id', $this->resolveAnonymousDisplayId($session));

        if ($viewer) {
            $identityVisible = $this->canViewerSeeAnonymousIdentity($viewer, $session);
            $session->setAttribute('identity_visible_to_viewer', $identityVisible);
            if ($session->is_anonymous) {
                $session->setAttribute('chat_peer_student_id', (int) $session->student_id);
            }
            $this->applyAnonymousProjection($session, $viewer, $identityVisible);
            if ($session->is_anonymous && !$identityVisible) {
                $session->setAttribute('anonymous_id', null);
            }
            $this->redactConfidentialNotesForViewer($session, $viewer);
        } else {
            $session->setAttribute('identity_visible_to_viewer', true);
        }
    }

    private function redactConfidentialNotesForViewer(CounselingSession $session, User $viewer): void
    {
        if (!$viewer->hasRole('admin')) {
            return;
        }

        // Policy: admins can audit operational metadata but must not view confidential session notes.
        $session->setAttribute('notes', null);
        $session->setAttribute('ai_summary', null);
        $session->setAttribute('notes_redacted', true);
    }

    private function maybeAutoRevealAnonymousIdentity(
        CounselingSession $session,
        User $viewer,
        Request $request,
        ?string $riskLevel
    ): void {
        if (!$session->is_anonymous || $session->identity_revealed_at) {
            return;
        }

        if (!$viewer->hasRole('counselor') || (int) $session->counselor_id !== (int) $viewer->id) {
            return;
        }

        if (!in_array((string) $riskLevel, ['high', 'critical'], true)) {
            return;
        }

        $this->revealAnonymousIdentity(
            $request,
            $session,
            $viewer,
            'high_risk_auto_reveal',
            $riskLevel
        );
    }

    private function revealAnonymousIdentity(
        Request $request,
        CounselingSession $session,
        ?User $revealedToUser,
        string $reason,
        ?string $riskLevel = null
    ): void {
        if (!$session->is_anonymous || $session->identity_revealed_at) {
            return;
        }

        $revealedBy = $revealedToUser?->id ?? $session->counselor_id;
        if (!$revealedBy) {
            return;
        }

        $session->update([
            'identity_revealed_at' => now(),
            'identity_revealed_by' => $revealedBy,
        ]);
        $session->refresh();

        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'anonymous_identity_revealed',
            'description' => "Anonymous identity revealed for session {$session->id}.",
            'type' => 'alert',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'session_id' => $session->id,
                'student_id' => $session->student_id,
                'counselor_id' => $session->counselor_id,
                'revealed_to_user_id' => $revealedBy,
                'reason' => $reason,
                'risk_level' => $riskLevel,
                'anonymous_id' => $session->anonymous_id,
            ],
        ]);
    }

    private function canViewerSeeAnonymousIdentity(User $viewer, CounselingSession $session): bool
    {
        if (!$session->is_anonymous) {
            return true;
        }

        if ((int) $session->student_id === (int) $viewer->id) {
            return true;
        }

        if ($session->identity_revealed_at === null) {
            return false;
        }

        if ($viewer->hasRole('admin')) {
            return true;
        }

        if ($viewer->hasRole('counselor') && (int) $session->counselor_id === (int) $viewer->id) {
            return true;
        }

        return false;
    }

    private function applyAnonymousProjection(
        CounselingSession $session,
        User $viewer,
        bool $identityVisible
    ): void {
        if (!$session->is_anonymous) {
            return;
        }

        if ($identityVisible) {
            return;
        }

        $anonymousDisplayId = $this->resolveAnonymousDisplayId($session);
        $session->setAttribute('student_id', 0);

        if ($session->relationLoaded('student') && $session->student) {
            $session->student->setAttribute('id', 0);
            $session->student->email = null;
            $session->student->setAttribute('masked_for_viewer', true);

            if ($session->student->relationLoaded('profile') && $session->student->profile) {
                $session->student->profile->full_name = $anonymousDisplayId;
                $session->student->profile->id_number = null;
                $session->student->profile->avatar_url = null;
            }
        }

        $session->setAttribute('identity_masked', true);
        $session->setAttribute('identity_masked_for_role', $viewer->hasRole('peer_counselor') ? 'peer_counselor' : 'counselor');
    }

    private function resolveAnonymousDisplayId(CounselingSession $session): string
    {
        if (!$session->is_anonymous) {
            return '';
        }

        return 'Anonymous User';
    }

    private function latestRiskLevel(CounselingSession $session): ?string
    {
        $sessionId = (int) $session->id;
        if (array_key_exists($sessionId, $this->sessionRiskLevelCache)) {
            $value = $this->sessionRiskLevelCache[$sessionId];
            if ($value !== null) {
                return $value;
            }
        }

        $studentId = (int) $session->student_id;
        if (array_key_exists($studentId, $this->studentRiskLevelCache)) {
            return $this->studentRiskLevelCache[$studentId];
        }

        if ($this->riskCachePrimed) {
            return null;
        }

        $sessionDiagnostic = AiDiagnostic::query()
            ->where('session_id', $session->id)
            ->whereNotNull('risk_level')
            ->latest('id')
            ->value('risk_level');

        if ($sessionDiagnostic) {
            $normalized = strtolower((string) $sessionDiagnostic);
            $this->sessionRiskLevelCache[$sessionId] = $normalized;
            return $normalized;
        }

        $studentDiagnostic = AiDiagnostic::query()
            ->where('student_id', $session->student_id)
            ->whereNotNull('risk_level')
            ->latest('id')
            ->value('risk_level');

        if ($studentDiagnostic) {
            $normalized = strtolower((string) $studentDiagnostic);
            $this->studentRiskLevelCache[$studentId] = $normalized;
            return $normalized;
        }

        $this->sessionRiskLevelCache[$sessionId] = null;
        $this->studentRiskLevelCache[$studentId] = null;
        return null;
    }

    /**
     * Prime latest session and student risk levels in two batched queries.
     *
     * @param iterable<CounselingSession> $sessions
     */
    private function primeRiskLevelCache(iterable $sessions): void
    {
        $sessionIds = [];
        $studentIds = [];

        foreach ($sessions as $session) {
            $sid = (int) $session->id;
            $stid = (int) $session->student_id;
            if ($sid > 0) {
                $sessionIds[$sid] = true;
            }
            if ($stid > 0) {
                $studentIds[$stid] = true;
            }
        }

        if ($sessionIds !== []) {
            $latestSessionDiagnosticIds = AiDiagnostic::query()
                ->whereIn('session_id', array_keys($sessionIds))
                ->whereNotNull('risk_level')
                ->selectRaw('MAX(id) as id')
                ->groupBy('session_id')
                ->pluck('id')
                ->filter()
                ->all();

            if ($latestSessionDiagnosticIds !== []) {
                $sessionDiagnostics = AiDiagnostic::query()
                    ->select(['id', 'session_id', 'risk_level'])
                    ->whereIn('id', $latestSessionDiagnosticIds)
                    ->get();

                foreach ($sessionDiagnostics as $row) {
                    $sid = (int) $row->session_id;
                    if ($sid <= 0) {
                        continue;
                    }

                    $this->sessionRiskLevelCache[$sid] = strtolower((string) $row->risk_level);
                }
            }
        }

        if ($studentIds !== []) {
            $latestStudentDiagnosticIds = AiDiagnostic::query()
                ->whereIn('student_id', array_keys($studentIds))
                ->whereNotNull('risk_level')
                ->selectRaw('MAX(id) as id')
                ->groupBy('student_id')
                ->pluck('id')
                ->filter()
                ->all();

            if ($latestStudentDiagnosticIds !== []) {
                $studentDiagnostics = AiDiagnostic::query()
                    ->select(['id', 'student_id', 'risk_level'])
                    ->whereIn('id', $latestStudentDiagnosticIds)
                    ->get();

                foreach ($studentDiagnostics as $row) {
                    $stid = (int) $row->student_id;
                    if ($stid <= 0) {
                        continue;
                    }

                    $this->studentRiskLevelCache[$stid] = strtolower((string) $row->risk_level);
                }
            }
        }

        $this->riskCachePrimed = true;
    }

    private function isApprovedCounselor(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', function ($query) {
                $query->where('role', 'counselor')->where('approved', true);
            })
            ->exists();
    }

    private function isApprovedPeerCounselor(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', function ($query) {
                $query->where('role', 'peer_counselor')->where('approved', true);
            })
            ->exists();
    }

    private function isApprovedStudent(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', function ($query) {
                $query->where('role', 'student')->where('approved', true);
            })
            ->exists();
    }

    private function generateAnonymousId(): string
    {
        return CounselingSession::generateUniqueAnonymousId();
    }

    private function buildEmergencyMessage(CounselingSession $session, string $reason): string
    {
        if ($session->is_anonymous) {
            $label = $this->resolveAnonymousDisplayId($session);
        } else {
            $student = $session->student;
            $student?->loadMissing('profile');
            $name = trim((string) ($student?->profile?->full_name ?? ''));
            $idNumber = trim((string) ($student?->profile?->id_number ?? ''));
            if ($name !== '') {
                $label = $name;
            } elseif ($student?->email) {
                $label = (string) $student->email;
            } else {
                $label = 'Student #' . (int) $session->student_id;
            }
            $bits = ['user ID ' . (int) $session->student_id];
            if ($student?->email) {
                $bits[] = (string) $student->email;
            }
            if ($idNumber !== '') {
                $bits[] = 'Institution ID ' . $idNumber;
            }
            $label .= ' (' . implode(' · ', $bits) . ')';
        }

        if ($reason !== '') {
            return "{$label} triggered emergency escalation. Reason: {$reason}";
        }

        return "{$label} triggered emergency escalation from chat.";
    }

    private function expireStaleAnonymousSessions(): void
    {
        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', self::ANONYMOUS_SESSION_TTL_HOURS));
        $cutoff = now()->subHours($ttlHours);

        CounselingSession::query()
            ->where('is_anonymous', true)
            ->whereIn('status', ['pending', 'active'])
            ->where('updated_at', '<', $cutoff)
            ->update([
                'status' => 'cancelled',
                'ended_at' => now(),
            ]);
    }

    private function logCaseTransition(
        Request $request,
        string $action,
        string $description,
        CounselingSession $session,
        ?int $targetUserId = null,
        array $extra = []
    ): void {
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'description' => $description,
            'type' => in_array($action, ['peer_case_escalated_to_counselor', 'anonymous_panic_escalation'], true)
                ? 'alert'
                : 'session',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge([
                'session_id' => $session->id,
                'student_id' => $session->student_id,
                'counselor_id' => $session->counselor_id,
                'peer_counselor_id' => $session->peer_counselor_id,
                'assigned_role' => $session->assigned_role,
                'status' => $session->status,
                'target_user_id' => $targetUserId,
                'is_anonymous' => $session->is_anonymous,
                'anonymous_id' => $session->anonymous_id,
            ], $extra),
        ]);
    }

    private function extractSessionListFilters(array $validated): array
    {
        $filters = [];
        foreach (['session_type', 'status', 'as_role'] as $key) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null) {
                $filters[$key] = $validated[$key];
            }
        }

        if (array_key_exists('open_only', $validated)) {
            $filters['open_only'] = (bool) $validated['open_only'];
        }

        return $filters;
    }

    private function extractChatListFilters(array $validated, string $effectiveRole, bool $openOnly): array
    {
        $filters = [
            'scope_role' => $effectiveRole,
            'open_only' => $openOnly,
        ];

        if (array_key_exists('as_role', $validated) && $validated['as_role'] !== null) {
            $filters['as_role'] = $validated['as_role'];
        }

        return $filters;
    }

    private function normalizeCachePayload(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalizeCachePayload($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->normalizeCachePayload($item);
        }
        ksort($normalized);

        return $normalized;
    }

    private function paginationLinks(?LengthAwarePaginator $paginator): array
    {
        $currentPage = max(1, (int) ($paginator?->currentPage() ?? 1));
        $lastPage = max(1, (int) ($paginator?->lastPage() ?? 1));

        return [
            'first' => $paginator?->url(1),
            'last' => $paginator?->url($lastPage),
            'next' => $paginator?->nextPageUrl(),
            'prev' => $paginator?->previousPageUrl(),
            'current' => $paginator?->url($currentPage),
        ];
    }
}
