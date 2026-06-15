<?php

namespace App\Http\Controllers;

use App\Models\AiDiagnostic;
use App\Models\AiReport;
use App\Models\CounselingSession;
use App\Models\User;
use App\Support\PaginationPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1|max:100000',
            'per_page' => 'sometimes|integer|min:1|max:200',
        ]);

        $query = AiReport::query()->orderBy('generated_at', 'desc');
        $usePagination = array_key_exists('page', $validated) || array_key_exists('per_page', $validated);
        if ($usePagination) {
            $page = max(1, (int) ($validated['page'] ?? 1));
            $perPage = max(1, min(200, (int) ($validated['per_page'] ?? 50)));
            $paginator = $query
                ->paginate($perPage, ['*'], 'page', $page)
                ->appends($request->query());

            return response()->json(
                PaginationPayload::fromPaginator($paginator, $request, [])
            );
        }

        $reports = $query->get();

        return response()->json($reports);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $report = AiReport::findOrFail($id);

        return response()->json($report);
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $validated = $request->validate([
            'type' => 'required|in:weekly_heatmap,monthly_trend,risk_assessment,counselor_burnout',
        ]);

        $reportData = $this->generateReportData($validated['type']);

        $report = AiReport::create([
            'name' => $reportData['name'],
            'type' => $validated['type'],
            'status' => 'ready',
            'summary' => $reportData['summary'],
            'data' => $reportData['data'],
            'generated_at' => now(),
        ]);

        return response()->json($report, 201);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $report = AiReport::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }

    private function generateReportData(string $type): array
    {
        switch ($type) {
            case 'weekly_heatmap':
                return $this->generateWeeklyHeatmap();
            case 'monthly_trend':
                return $this->generateMonthlyTrend();
            case 'risk_assessment':
                return $this->generateRiskAssessment();
            case 'counselor_burnout':
                return $this->generateCounselorBurnout();
            default:
                return [
                    'name' => 'Unknown Report',
                    'summary' => 'No data available',
                    'data' => [],
                ];
        }
    }

    private function generateWeeklyHeatmap(): array
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $diagnostics = AiDiagnostic::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->select('risk_level', DB::raw('count(*) as count'))
            ->groupBy('risk_level')
            ->get()
            ->pluck('count', 'risk_level')
            ->toArray();

        $totalDiagnostics = array_sum($diagnostics);
        $highRiskCount = $diagnostics['high'] ?? 0;
        $highRiskPercentage = $totalDiagnostics > 0 ? round(($highRiskCount / $totalDiagnostics) * 100, 1) : 0;

        return [
            'name' => 'Weekly Emotional Heatmap',
            'summary' => "This week: {$totalDiagnostics} diagnostics completed. {$highRiskPercentage}% high-risk cases identified.",
            'data' => [
                'period' => 'week',
                'start_date' => $startOfWeek->toDateString(),
                'end_date' => $endOfWeek->toDateString(),
                'risk_distribution' => $diagnostics,
                'total_diagnostics' => $totalDiagnostics,
                'high_risk_percentage' => $highRiskPercentage,
            ],
        ];
    }

    private function generateMonthlyTrend(): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $diagnostics = AiDiagnostic::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->select(
                DB::raw('AVG(stress_level) as avg_stress'),
                DB::raw('AVG(anxiety_level) as avg_anxiety'),
                DB::raw('AVG(depression_level) as avg_depression'),
                DB::raw('COUNT(*) as total')
            )
            ->first();

        // Safely handle months with no diagnostics to avoid null property access
        $diagnosticTotal = $diagnostics->total ?? 0;
        $avgStress = round($diagnostics->avg_stress ?? 0, 2);
        $avgAnxiety = round($diagnostics->avg_anxiety ?? 0, 2);
        $avgDepression = round($diagnostics->avg_depression ?? 0, 2);

        $sessions = CounselingSession::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

        return [
            'name' => 'Monthly Trend Analysis',
            'summary' => "This month: {$diagnosticTotal} diagnostics, {$sessions} counseling sessions. Average stress: ".round($avgStress, 1),
            'data' => [
                'period' => 'month',
                'start_date' => $startOfMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
                'total_diagnostics' => $diagnosticTotal,
                'total_sessions' => $sessions,
                'average_stress_level' => $avgStress,
                'average_anxiety_level' => $avgAnxiety,
                'average_depression_level' => $avgDepression,
            ],
        ];
    }

    private function generateRiskAssessment(): array
    {
        $highRiskStudents = AiDiagnostic::where('risk_level', 'high')
            ->with('student.profile')
            ->latest()
            ->limit(20)
            ->get();

        $riskDistribution = AiDiagnostic::select('risk_level', DB::raw('count(*) as count'))
            ->whereNotNull('risk_level')
            ->groupBy('risk_level')
            ->get()
            ->pluck('count', 'risk_level')
            ->toArray();

        $totalStudents = User::whereHas('roles', fn ($q) => $q->where('role', 'student'))->count();
        $studentsWithDiagnostics = AiDiagnostic::distinct('student_id')->count('student_id');

        return [
            'name' => 'Risk Assessment Report',
            'summary' => "Campus-wide risk assessment: {$highRiskStudents->count()} high-risk students identified. {$studentsWithDiagnostics}/{$totalStudents} students assessed.",
            'data' => [
                'total_students' => $totalStudents,
                'students_assessed' => $studentsWithDiagnostics,
                'risk_distribution' => $riskDistribution,
                'high_risk_count' => $highRiskStudents->count(),
                'high_risk_students' => $highRiskStudents->map(fn ($d) => [
                    'student_id' => $d->student_id,
                    'student_name' => $d->student->profile->full_name ?? 'Anonymous',
                    'risk_level' => $d->risk_level,
                    'stress_level' => $d->stress_level,
                    'anxiety_level' => $d->anxiety_level,
                    'depression_level' => $d->depression_level,
                    'assessed_at' => $d->created_at,
                ]),
            ],
        ];
    }

    private function generateCounselorBurnout(): array
    {
        $counselors = User::whereHas('roles', fn ($q) => $q->where('role', 'counselor'))
            ->with('profile')
            ->withCount(['counselorSessions', 'appointmentsAsCounselor'])
            ->get();

        $avgSessionsPerCounselor = $counselors->avg('counselor_sessions_count') ?? 0;
        $maxSessions = $counselors->max('counselor_sessions_count') ?? 0;

        $overloadedCounselors = $counselors->filter(function ($c) use ($avgSessionsPerCounselor) {
            return $c->counselor_sessions_count > ($avgSessionsPerCounselor * 1.5);
        });

        return [
            'name' => 'Counselor Burnout Analysis',
            'summary' => "Workload analysis: {$overloadedCounselors->count()} counselors above 150% average workload. Average: ".round($avgSessionsPerCounselor, 1).' sessions.',
            'data' => [
                'total_counselors' => $counselors->count(),
                'average_sessions_per_counselor' => round($avgSessionsPerCounselor, 2),
                'max_sessions' => $maxSessions,
                'overloaded_counselors_count' => $overloadedCounselors->count(),
                'counselor_workload' => $counselors->map(fn ($c) => [
                    'counselor_id' => $c->id,
                    'counselor_name' => $c->profile->full_name ?? 'Unknown',
                    'total_sessions' => $c->counselor_sessions_count,
                    'total_appointments' => $c->appointments_as_counselor_count,
                    'workload_status' => $c->counselor_sessions_count > ($avgSessionsPerCounselor * 1.5) ? 'overloaded' : 'normal',
                ]),
            ],
        ];
    }
}
