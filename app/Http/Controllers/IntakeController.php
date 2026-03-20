<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\IntakeSubmission;
use App\Models\Notification;
use App\Models\RiskAlert;
use App\Models\User;
use App\Support\PaginationPayload;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IntakeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'sometimes|string|max:20',
            'risk_level' => 'sometimes|string|max:20',
            'limit' => 'sometimes|integer|min:1|max:200',
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = IntakeSubmission::query()
            ->with(['user.profile', 'assignedTo.profile', 'riskAlerts'])
            ->orderByDesc('created_at');

        if ($user->hasRole('admin')) {
            // no scope filter
        } elseif ($user->hasRole('counselor') || $user->hasRole('peer_counselor')) {
            $query->where('assigned_to', $user->id);
        } else {
            $query->where('user_id', $user->id);
        }

        if (!empty($validated['status'])) {
            $query->where('status', (string) $validated['status']);
        }
        if (!empty($validated['risk_level'])) {
            $query->where('risk_level', (string) $validated['risk_level']);
        }

        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? 50)));
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, ['status', 'risk_level'])
            );
        }

        $limit = (int) ($validated['limit'] ?? 50);
        return response()->json($query->limit($limit)->get());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $intake = IntakeSubmission::query()
            ->with(['user.profile', 'assignedTo.profile', 'riskAlerts'])
            ->findOrFail($id);

        if (!$this->canViewIntake($request->user(), $intake)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($intake);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'submitter_type' => 'sometimes|in:student,staff',
            'is_anonymous' => 'sometimes|boolean',
            'presenting_concerns' => 'required|array|min:1|max:20',
            'presenting_concerns.*' => 'required|string|max:120',
            'risk_answers' => 'sometimes|array',
            'consent_acknowledged' => 'required|accepted',
            'summary' => 'sometimes|nullable|string|max:2000',
        ]);

        $submitterType = $validated['submitter_type']
            ?? ($user->hasRole('student') ? 'student' : 'staff');
        $riskAnswers = is_array($validated['risk_answers'] ?? null) ? $validated['risk_answers'] : [];
        [$riskLevel, $urgencyScore] = $this->evaluateRisk($riskAnswers);
        $assignedCounselorId = $this->resolveCounselorAssignment($riskLevel);
        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);

        $intake = IntakeSubmission::query()->create([
            'user_id' => $user->id,
            'submitter_type' => $submitterType,
            'is_anonymous' => $isAnonymous,
            'anonymous_id' => $isAnonymous ? $this->generateAnonymousId() : null,
            'presenting_concerns' => array_values($validated['presenting_concerns']),
            'risk_answers' => $riskAnswers,
            'consent_acknowledged' => true,
            'risk_level' => $riskLevel,
            'urgency_score' => $urgencyScore,
            'status' => $riskLevel === 'high' ? 'escalated' : ($riskLevel === 'moderate' ? 'routed' : 'new'),
            'assigned_to' => $assignedCounselorId,
            'summary' => $validated['summary'] ?? null,
        ]);

        if ($riskLevel === 'high') {
            RiskAlert::query()->create([
                'intake_submission_id' => $intake->id,
                'severity' => 'high',
                'status' => 'open',
                'triggered_at' => now(),
                'metadata' => [
                    'urgency_score' => $urgencyScore,
                    'submitter_type' => $submitterType,
                ],
            ]);

            $this->notifyHighRiskIntake($intake);
        }

        $this->logIntakeCreation($request, $intake);

        return response()->json($intake->load(['riskAlerts', 'assignedTo.profile']), 201);
    }

    public function acknowledgeAlert(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $alert = RiskAlert::query()
            ->with('intakeSubmission')
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:acknowledged,resolved',
        ]);

        $alert->update([
            'status' => $validated['status'],
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        if ($validated['status'] === 'resolved') {
            $alert->intakeSubmission?->update(['status' => 'closed']);
        }

        return response()->json($alert->fresh(['intakeSubmission']));
    }

    private function canViewIntake(User $user, IntakeSubmission $intake): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ((int) $intake->user_id === (int) $user->id) {
            return true;
        }

        return (int) $intake->assigned_to === (int) $user->id;
    }

    private function evaluateRisk(array $answers): array
    {
        $score = 0;
        $danger = false;

        $truthy = function (string $key) use ($answers): bool {
            $value = $answers[$key] ?? false;
            return in_array($value, [true, 1, '1', 'true', 'yes', 'high'], true);
        };

        if ($truthy('immediate_danger')) {
            $danger = true;
            $score += 10;
        }
        if ($truthy('self_harm_thoughts')) {
            $score += 5;
        }
        if ($truthy('panic_attacks')) {
            $score += 3;
        }
        if ($truthy('sleep_disruption')) {
            $score += 1;
        }
        if ($truthy('academic_decline')) {
            $score += 2;
        }
        if ($truthy('social_withdrawal')) {
            $score += 2;
        }

        if ($danger || $score >= 7) {
            return ['high', min(100, $score * 10)];
        }
        if ($score >= 3) {
            return ['moderate', min(100, $score * 10)];
        }

        return ['low', min(100, $score * 10)];
    }

    private function resolveCounselorAssignment(string $riskLevel): ?int
    {
        if (!in_array($riskLevel, ['moderate', 'high'], true)) {
            return null;
        }

        $counselorId = User::query()
            ->whereHas('roles', function ($query) {
                $query->where('role', 'counselor')->where('approved', true);
            })
            ->leftJoin('counseling_sessions as cs', function ($join) {
                $join->on('users.id', '=', 'cs.counselor_id')
                    ->whereIn('cs.status', ['pending', 'active']);
            })
            ->groupBy('users.id')
            ->orderByRaw('COUNT(cs.id) ASC')
            ->value('users.id');

        return $counselorId ? (int) $counselorId : null;
    }

    private function notifyHighRiskIntake(IntakeSubmission $intake): void
    {
        if (!SystemSettings::getBool('ai_risk_alerts', true)) {
            return;
        }

        $recipients = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('role', ['admin', 'counselor'])->where('approved', true);
            })
            ->pluck('id')
            ->unique()
            ->values();

        foreach ($recipients as $recipientId) {
            Notification::query()->create([
                'user_id' => (int) $recipientId,
                'title' => 'High-Risk Intake Alert',
                'message' => "A {$intake->submitter_type} intake has been flagged high-risk and needs urgent review.",
                'type' => 'warning',
            ]);
        }
    }

    private function generateAnonymousId(): string
    {
        do {
            $candidate = 'ANON-I-' . Str::upper(Str::random(6));
        } while (IntakeSubmission::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }

    private function logIntakeCreation(Request $request, IntakeSubmission $intake): void
    {
        if (!SystemSettings::getBool('audit_logging', true)) {
            return;
        }

        ActivityLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => 'intake.created',
            'description' => "Intake submission {$intake->id} created with risk level {$intake->risk_level}.",
            'type' => 'system',
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => [
                'intake_submission_id' => $intake->id,
                'risk_level' => $intake->risk_level,
                'urgency_score' => $intake->urgency_score,
            ],
        ]);
    }
}
