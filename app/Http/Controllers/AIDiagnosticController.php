<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Message;
use App\Services\AIDiagnosticService;
use App\Jobs\ProcessAIDiagnostic;
use App\Support\PaginationPayload;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AIDiagnosticController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'student_id' => 'nullable|integer|exists:users,id',
            'limit' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = AiDiagnostic::with(['student', 'session']);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('counselor')) {
            // Counselors can see diagnostics for their students
            $query->whereHas('session', function($q) use ($user) {
                $q->where('counselor_id', $user->id);
            });
        } elseif (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!empty($validated['student_id'])) {
            $query->where('student_id', (int) $validated['student_id']);
        }

        $query->orderByDesc('created_at')->orderByDesc('id');
        $limit = array_key_exists('limit', $validated)
            ? max(1, min(500, (int) $validated['limit']))
            : null;
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($validated['per_page'] ?? ($limit ?? 50))));

        if ($usePagination) {
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, ['student_id'])
            );
        }

        if ($limit !== null) {
            $query->limit($limit);
        }
        $diagnostics = $query->get();

        return response()->json($diagnostics);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $diagnostic = AiDiagnostic::with(['student', 'session'])->findOrFail($id);
        $user = $request->user();

        $uid = (int) $user->id;
        $isOwnerStudent = (int) $diagnostic->student_id === $uid;
        $isSessionCounselor = $diagnostic->session && (int) $diagnostic->session->counselor_id === $uid;

        if (!$user->hasRole('admin') && !$isOwnerStudent && !$isSessionCounselor) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($diagnostic);
    }

    public function analyzeSession(Request $request, string $sessionId): JsonResponse
    {
        $session = CounselingSession::findOrFail($sessionId);
        $user = $request->user();

        // Only counselors and admins can trigger analysis
        if (!$user->hasRole('admin') && (int) $session->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get messages from session
        $messages = Message::where('session_id', $sessionId)
            ->where(function ($query) {
                $query->where('is_encrypted', false)
                    ->orWhereNull('is_encrypted');
            })
            ->orderBy('created_at')
            ->get()
            ->map(function($msg) use ($session) {
                $content = trim((string) ($msg->content ?? ''));
                if ($content === '') {
                    return null;
                }

                return [
                    'sender' => (int) $msg->sender_id === (int) $session->student_id ? 'student' : 'counselor',
                    'content' => $content,
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        if (empty($messages)) {
            return response()->json([
                'message' => 'No plain-text messages are available for AI analysis. Encrypted conversations are not sent to diagnostics.',
            ], 400);
        }

        // Dispatch job to process AI diagnostic
        ProcessAIDiagnostic::dispatch($session, $messages);

        return response()->json([
            'message' => 'AI analysis started. Results will be available shortly.',
            'session_id' => $sessionId,
        ], 202);
    }

    public function analyzeAppointment(Request $request, string $appointmentId): JsonResponse
    {
        $appointment = Appointment::findOrFail($appointmentId);
        $user = $request->user();

        if (!$user->hasRole('admin') && (int) $appointment->counselor_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (empty($appointment->notes)) {
            return response()->json(['message' => 'No notes found for this appointment'], 400);
        }

        // For appointments (physical), we treat the notes as a single session content
        $messages = [
            [
                'sender' => 'counselor',
                'content' => $appointment->notes,
            ]
        ];

        // Create a lightweight CounselingSession wrapper for the job if necessary,
        // or update ProcessAIDiagnostic to handle Appointments directly.
        // For now, we simulate a session for the AI engine.
        $session = new CounselingSession();
        $session->fill([
            'student_id' => $appointment->student_id,
            'counselor_id' => $appointment->counselor_id,
            'session_type' => 'physical',
            'status' => 'completed',
        ]);
        $session->id = "apt_{$appointment->id}"; // Virtual ID prefix

        ProcessAIDiagnostic::dispatch($session, $messages);

        return response()->json([
            'message' => 'AI analysis started for appointment notes.',
            'appointment_id' => $appointmentId,
        ], 202);
    }

    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AiDiagnostic::with(['student', 'session']);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('counselor')) {
            $query->whereHas('session', function($q) use ($user) {
                $q->where('counselor_id', $user->id);
            });
        } elseif (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $diagnostic = $query->latest()->first();

        if (!$diagnostic) {
            return response()->json(['message' => 'No diagnostics found'], 404);
        }

        return response()->json($diagnostic);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = (int) $request->query('days', 30);
        $days = max(1, min(365, $days));
        $since = now()->subDays($days);

        $query = AiDiagnostic::query()->where('created_at', '>=', $since);

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole('counselor')) {
            $sessionStudentIds = CounselingSession::query()
                ->where('counselor_id', $user->id)
                ->pluck('student_id');

            $appointmentStudentIds = Appointment::query()
                ->where('counselor_id', $user->id)
                ->pluck('student_id');

            $studentIds = $sessionStudentIds
                ->merge($appointmentStudentIds)
                ->filter()
                ->unique()
                ->values();

            if ($studentIds->isEmpty()) {
                return response()->json([
                    'window_days' => $days,
                    'total' => 0,
                    'high_or_critical' => 0,
                    'students_with_high_or_critical' => 0,
                    'by_risk_level' => [],
                ]);
            }

            $query->whereIn('student_id', $studentIds->all());
        } elseif (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $total = (clone $query)->count();
        $highOrCriticalQuery = (clone $query)->whereIn('risk_level', ['high', 'critical']);
        $highOrCritical = $highOrCriticalQuery->count();
        $highOrCriticalStudents = (clone $highOrCriticalQuery)->distinct('student_id')->count('student_id');

        $byRiskLevel = (clone $query)
            ->whereNotNull('risk_level')
            ->select('risk_level', DB::raw('count(*) as count'))
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->toArray();

        return response()->json([
            'window_days' => $days,
            'total' => $total,
            'high_or_critical' => $highOrCritical,
            'students_with_high_or_critical' => $highOrCriticalStudents,
            'by_risk_level' => $byRiskLevel,
        ]);
    }
}
