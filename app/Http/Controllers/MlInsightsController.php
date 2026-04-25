<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use App\Services\MentalHealthMlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MlInsightsController extends Controller
{
    public function __construct(
        private readonly MentalHealthMlService $mentalHealthMlService
    ) {
    }

    public function counselorMatches(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('student') && !$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'nullable|integer|exists:users,id',
            'mode' => 'nullable|in:online,physical',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $studentId = $user->hasRole('student')
            ? (int) $user->id
            : (int) ($validated['student_id'] ?? 0);

        if ($studentId <= 0) {
            return response()->json(['message' => 'student_id is required'], 422);
        }

        if ($user->hasRole('counselor')) {
            $assignedBySession = CounselingSession::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            $assignedByAppointment = Appointment::query()
                ->where('counselor_id', $user->id)
                ->where('student_id', $studentId)
                ->exists();

            if (!$assignedBySession && !$assignedByAppointment) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $student = User::findOrFail($studentId);
        if (!$student->hasRole('student')) {
            return response()->json(['message' => 'Target user is not a student'], 422);
        }

        $mode = (string) ($validated['mode'] ?? 'online');
        $limit = (int) ($validated['limit'] ?? 6);

        return response()->json([
            'student_id' => $studentId,
            'mode' => $mode,
            'model_version' => MentalHealthMlService::MODEL_VERSION,
            'matches' => $this->mentalHealthMlService->rankCounselorsForStudent($student, [
                'mode' => $mode,
                'limit' => $limit,
            ]),
        ]);
    }

    public function health(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $windowStart = now()->subDay();
        $modelVersion = MentalHealthMlService::MODEL_VERSION;

        $wellnessConversationIds = DB::table('chat_conversations')
            ->where('model', 'wellness-assistant-v1')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $assistantMessageIds = empty($wellnessConversationIds)
            ? []
            : DB::table('chat_messages')
                ->whereIn('conversation_id', $wellnessConversationIds)
                ->where('role', 'assistant')
                ->where('created_at', '>=', $windowStart)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $providerModes = [];
        $providerNames = [];
        $latencies = [];

        if (!empty($assistantMessageIds)) {
            $metadataRows = DB::table('message_metadata')
                ->whereIn('message_id', $assistantMessageIds)
                ->whereIn('key', ['provider_mode', 'provider_name', 'latency_ms'])
                ->get(['key', 'value']);

            foreach ($metadataRows as $row) {
                $key = (string) ($row->key ?? '');
                $value = (string) ($row->value ?? '');
                if ($key === 'provider_mode' && $value !== '') {
                    $providerModes[$value] = (int) (($providerModes[$value] ?? 0) + 1);
                }
                if ($key === 'provider_name' && $value !== '') {
                    $providerNames[$value] = (int) (($providerNames[$value] ?? 0) + 1);
                }
                if ($key === 'latency_ms') {
                    $latency = (int) $value;
                    if ($latency > 0) {
                        $latencies[] = $latency;
                    }
                }
            }
        }

        $inferences = array_sum($providerModes);
        $fallbackCount = (int) (($providerModes['local_fallback'] ?? 0) + ($providerModes['safety_guardrail'] ?? 0));
        $fallbackRate = $inferences > 0
            ? round(($fallbackCount / $inferences) * 100, 2)
            : 0.0;
        $avgLatencyMs = !empty($latencies)
            ? round(array_sum($latencies) / count($latencies), 2)
            : 0.0;
        $p95LatencyMs = 0.0;
        if (!empty($latencies)) {
            sort($latencies);
            $index = (int) floor((count($latencies) - 1) * 0.95);
            $p95LatencyMs = (float) $latencies[$index];
        }

        $adminOverview = $this->mentalHealthMlService->buildAdminMlOverview();
        $studentsNeedingFollowUp = (int) ($adminOverview['students_needing_follow_up'] ?? 0);
        $risingRiskStudents = (int) ($adminOverview['rising_risk_students'] ?? 0);

        return response()->json([
            'model_version' => $modelVersion,
            'window' => [
                'from' => $windowStart->toIso8601String(),
                'to' => now()->toIso8601String(),
            ],
            'inference' => [
                'assistant_inferences_24h' => $inferences,
                'provider_modes' => $providerModes,
                'provider_names' => $providerNames,
                'fallback_rate_percent' => $fallbackRate,
                'average_latency_ms' => $avgLatencyMs,
                'p95_latency_ms' => $p95LatencyMs,
            ],
            'risk_monitoring' => [
                'students_needing_follow_up' => $studentsNeedingFollowUp,
                'rising_risk_students' => $risingRiskStudents,
                'fairness_status' => (string) ($adminOverview['validation']['fairness_status'] ?? 'unknown'),
            ],
            'readiness' => [
                'low_bandwidth_mode' => true,
                'external_ai_optional' => true,
                'human_review_required' => true,
            ],
        ]);
    }
}
