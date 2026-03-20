<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use App\Models\CounselingSession;
use App\Models\Appointment;
use App\Models\Message;
use App\Models\AiDiagnostic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $cacheKey = 'analytics:dashboard:v2';
        $stats = Cache::remember($cacheKey, now()->addSeconds(20), function () {
            return [
                'overview' => $this->getOverviewStats(),
                'users' => $this->getUserStats(),
                'sessions' => $this->getSessionStats(),
                'appointments' => $this->getAppointmentStats(),
                'ai_diagnostics' => $this->getAIDiagnosticStats(),
                'recent_activity' => $this->getRecentActivity(),
                'risk_levels' => $this->getRiskLevelDistribution(),
                'counselor_performance' => $this->getCounselorPerformance(),
            ];
        });

        return response()->json($stats);
    }

    private function getOverviewStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_students' => User::whereHas('roles', fn($q) => $q->where('role', 'student'))->count(),
            'total_counselors' => User::whereHas('roles', fn($q) => $q->where('role', 'counselor'))->count(),
            'total_sessions' => CounselingSession::count(),
            'active_sessions' => CounselingSession::where('status', 'active')->count(),
            'total_appointments' => Appointment::count(),
            'pending_appointments' => Appointment::where('status', 'scheduled')->count(),
        ];
    }

    private function getUserStats(): array
    {
        return [
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_users_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)->count(),
            'users_by_role' => UserRole::select('role', DB::raw('count(*) as count'))
                ->where('approved', true)
                ->groupBy('role')
                ->get()
                ->pluck('count', 'role')
                ->toArray(),
        ];
    }

    private function getSessionStats(): array
    {
        $avgDuration = 0;

        $sessionsWithTiming = CounselingSession::query()
            ->whereNotNull('ended_at')
            ->whereNotNull('started_at');

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $avgDuration = (float) ($sessionsWithTiming
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_duration')
                ->value('avg_duration') ?? 0);
        } else {
            $durations = $sessionsWithTiming
                ->get(['started_at', 'ended_at'])
                ->map(function ($session) {
                    if (!$session->started_at || !$session->ended_at) {
                        return null;
                    }
                    return max(0, $session->started_at->diffInMinutes($session->ended_at, false));
                })
                ->filter(fn ($minutes) => is_int($minutes));

            $avgDuration = $durations->count() > 0
                ? (float) $durations->avg()
                : 0;
        }

        return [
            'total_sessions' => CounselingSession::count(),
            'sessions_by_status' => CounselingSession::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
            'sessions_by_type' => CounselingSession::select('session_type', DB::raw('count(*) as count'))
                ->groupBy('session_type')
                ->get()
                ->pluck('count', 'session_type')
                ->toArray(),
            'sessions_this_week' => CounselingSession::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'sessions_this_month' => CounselingSession::whereMonth('created_at', now()->month)->count(),
            'avg_session_duration' => round($avgDuration, 2),
        ];
    }

    private function getAppointmentStats(): array
    {
        return [
            'total_appointments' => Appointment::count(),
            'appointments_by_status' => Appointment::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
            'upcoming_appointments' => Appointment::where('scheduled_at', '>', now())
                ->where('status', '!=', 'cancelled')
                ->count(),
            'appointments_today' => Appointment::whereDate('scheduled_at', today())->count(),
            'appointments_this_week' => Appointment::whereBetween('scheduled_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
        ];
    }

    private function getAIDiagnosticStats(): array
    {
        $diagnostics = AiDiagnostic::select(
            DB::raw('AVG(stress_level) as avg_stress'),
            DB::raw('AVG(anxiety_level) as avg_anxiety'),
            DB::raw('AVG(depression_level) as avg_depression'),
            DB::raw('COUNT(*) as total')
        )->first();

        $totalDiagnostics = (int) ($diagnostics->total ?? 0);
        $studentsAssessed = (int) AiDiagnostic::query()
            ->distinct('student_id')
            ->count('student_id');
        $totalStudents = (int) User::query()
            ->whereHas('roles', fn($q) => $q->where('role', 'student')->where('approved', true))
            ->count();

        $riskDistribution = AiDiagnostic::select('risk_level', DB::raw('count(*) as count'))
            ->whereNotNull('risk_level')
            ->groupBy('risk_level')
            ->get()
            ->pluck('count', 'risk_level')
            ->toArray();

        $highRiskAlerts = (int) (($riskDistribution['high'] ?? 0) + ($riskDistribution['critical'] ?? 0));
        $coveragePercentage = $totalStudents > 0
            ? round(($studentsAssessed / $totalStudents) * 100, 1)
            : 0;

        return [
            'total_diagnostics' => $totalDiagnostics,
            'students_assessed' => $studentsAssessed,
            'coverage_percentage' => $coveragePercentage,
            'high_risk_alerts' => $highRiskAlerts,
            'average_stress_level' => round($diagnostics->avg_stress ?? 0, 2),
            'average_anxiety_level' => round($diagnostics->avg_anxiety ?? 0, 2),
            'average_depression_level' => round($diagnostics->avg_depression ?? 0, 2),
            'risk_level_distribution' => $riskDistribution,
            'diagnostics_this_month' => AiDiagnostic::whereMonth('created_at', now()->month)->count(),
        ];
    }

    private function getRecentActivity(): array
    {
        return [
            'recent_sessions' => CounselingSession::with(['student.profile', 'counselor.profile'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'student' => $s->student?->profile?->full_name ?? 'Anonymous',
                    'counselor' => $s->counselor?->profile?->full_name ?? 'Unassigned',
                    'status' => $s->status,
                    'created_at' => $s->created_at,
                ]),
            'recent_messages' => Message::with(['sender.profile', 'session'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn($m) => [
                    'id' => $m->id,
                    'sender' => $m->sender?->profile?->full_name ?? 'Unknown',
                    'session_id' => $m->session_id,
                    'created_at' => $m->created_at,
                ]),
        ];
    }

    private function getRiskLevelDistribution(): array
    {
        return AiDiagnostic::select('risk_level', DB::raw('count(*) as count'))
            ->whereNotNull('risk_level')
            ->groupBy('risk_level')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'risk_level')
            ->toArray();
    }

    private function getCounselorPerformance(): array
    {
        $counselors = User::whereHas('roles', fn($q) => $q->where('role', 'counselor'))
            ->with('profile')
            ->withCount(['counselorSessions', 'appointmentsAsCounselor'])
            ->get();

        $completedSessionsByCounselor = CounselingSession::query()
            ->select('counselor_id', DB::raw('count(*) as completed_count'))
            ->where('status', 'completed')
            ->whereNotNull('counselor_id')
            ->groupBy('counselor_id')
            ->pluck('completed_count', 'counselor_id');

        return $counselors
            ->map(fn($counselor) => [
                'id' => $counselor->id,
                'name' => $counselor->profile?->full_name ?? 'Unknown',
                'total_sessions' => $counselor->counselor_sessions_count,
                'total_appointments' => $counselor->appointments_as_counselor_count,
                'completed_sessions' => (int) ($completedSessionsByCounselor[$counselor->id] ?? 0),
            ])
            ->toArray();
    }
}


