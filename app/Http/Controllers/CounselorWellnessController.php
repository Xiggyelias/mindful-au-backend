<?php

namespace App\Http\Controllers;

use App\Models\CounselorWellnessLog;
use App\Models\User;
use App\Services\CounselorLiveHealthCheckService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CounselorWellnessController extends Controller
{
    private const CHECK_IN_VERSION = 'v1';

    public function __construct(private CounselorLiveHealthCheckService $liveHealthCheck)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? $request->counselor_id
            : $user->id;

        $logs = CounselorWellnessLog::where('counselor_id', $counselorId)
            ->latest()
            ->get();

        return response()->json($logs);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? (int) $request->counselor_id
            : $user->id;

        $counselor = User::findOrFail($counselorId);

        $latestLog = CounselorWellnessLog::where('counselor_id', $counselor->id)
            ->latest()
            ->first();

        $summary = $this->liveHealthCheck->buildLiveSummary($counselor);

        return response()->json([
            ...$summary,
            'latest_log' => $latestLog,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor')) {
            return response()->json(['message' => 'Only counselors can create wellness logs'], 403);
        }

        $validated = $request->validate([
            'mood_score' => 'sometimes|integer|min:0|max:100',
            'stress_level' => 'sometimes|integer|min:0|max:100',
            'burnout_index' => 'sometimes|integer|min:0|max:100',
            'notes' => 'sometimes|string|max:2000',
            'check_in' => 'sometimes|array',
            'check_in.emotional_drain' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.disconnect_difficulty' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.calm_control' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.energy_level' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.break_quality' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.support_level' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.sleep_quality' => 'required_with:check_in|integer|min:0|max:4',
            'check_in.burnout_worry' => 'required_with:check_in|integer|min:0|max:4',
        ]);

        $hasManualScore =
            array_key_exists('mood_score', $validated) ||
            array_key_exists('stress_level', $validated) ||
            array_key_exists('burnout_index', $validated);

        $hasCheckIn = array_key_exists('check_in', $validated);
        $hasNotes = array_key_exists('notes', $validated) && trim((string) $validated['notes']) !== '';

        if (!$hasManualScore && !$hasCheckIn && !$hasNotes) {
            return response()->json([
                'message' => 'Provide either check-in answers, score values, or notes.',
            ], 422);
        }

        $payload = [
            'counselor_id' => $user->id,
            'notes' => $validated['notes'] ?? null,
        ];

        if ($hasCheckIn) {
            $scores = $this->calculateCheckInScores($validated['check_in']);
            $payload['mood_score'] = $scores['mood_score'];
            $payload['stress_level'] = $scores['stress_level'];
            $payload['burnout_index'] = $scores['burnout_index'];
            $payload['recommendations'] = $this->buildCheckInRecommendations($scores);
            $payload['check_in_answers'] = $validated['check_in'];
            $payload['check_in_version'] = self::CHECK_IN_VERSION;
        } else {
            if (array_key_exists('mood_score', $validated)) {
                $payload['mood_score'] = $validated['mood_score'];
            }
            if (array_key_exists('stress_level', $validated)) {
                $payload['stress_level'] = $validated['stress_level'];
            }
            if (array_key_exists('burnout_index', $validated)) {
                $payload['burnout_index'] = $validated['burnout_index'];
            }
        }

        $log = CounselorWellnessLog::create([
            ...$payload,
        ]);

        return response()->json($log, 201);
    }

    public function runHealthCheck(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole('counselor') && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $counselorId = $request->has('counselor_id') && $user->hasRole('admin')
            ? $request->counselor_id
            : $user->id;

        $counselor = User::findOrFail($counselorId);
        $summary = $this->liveHealthCheck->buildLiveSummary($counselor);
        $scores = $summary['scores'];

        if (!($summary['has_live_activity'] ?? false)) {
            return response()->json([
                'persisted' => false,
                'message' => $summary['source'] === 'self-check-in-only'
                    ? 'Your self check-in is saved. Live workload scores will appear after sessions, appointments, or risk reviews are recorded.'
                    : 'No live counselor activity was found yet. Complete sessions, appointments, or a self check-in before running a live check.',
                'summary' => $summary,
            ]);
        }

        $log = CounselorWellnessLog::create([
            'counselor_id' => $counselor->id,
            'mood_score' => $scores['mood_score'] ?? null,
            'stress_level' => $scores['stress_level'] ?? null,
            'burnout_index' => $scores['burnout_index'] ?? null,
            'recommendations' => $summary['recommendations'] ?? null,
            'notes' => 'Automated health check from live activity data',
            'check_in_version' => 'auto-v3',
        ]);

        // Send notification if high stress/burnout
        if (($scores['stress_level'] ?? 0) > 70 || ($scores['burnout_index'] ?? 0) > 70) {
            $counselor->notifications()->create([
                'title' => 'Wellness Alert',
                'message' => 'Your stress or burnout levels are elevated. Please consider taking a break or speaking with a supervisor.',
                'type' => 'warning',
            ]);
        }

        return response()->json([
            ...$log->toArray(),
            'persisted' => true,
            'summary' => $summary,
        ], 201);
    }

    private function calculateCheckInScores(array $answers): array
    {
        // Values are 0-4 on a frequency scale.
        $emotionalDrain = (int) $answers['emotional_drain'];
        $disconnectDifficulty = (int) $answers['disconnect_difficulty'];
        $calmControl = (int) $answers['calm_control'];
        $energyLevel = (int) $answers['energy_level'];
        $breakQuality = (int) $answers['break_quality'];
        $supportLevel = (int) $answers['support_level'];
        $sleepQuality = (int) $answers['sleep_quality'];
        $burnoutWorry = (int) $answers['burnout_worry'];

        $inverseCalm = 4 - $calmControl;
        $inverseBreaks = 4 - $breakQuality;
        $inverseEnergy = 4 - $energyLevel;
        $inverseSleep = 4 - $sleepQuality;

        $stressRaw = ($emotionalDrain + $disconnectDifficulty + $inverseCalm + $inverseBreaks + $burnoutWorry) / 5;
        $burnoutRaw = ($emotionalDrain + $disconnectDifficulty + $burnoutWorry + $inverseEnergy + $inverseSleep) / 5;
        $moodRaw = ($calmControl + $energyLevel + $breakQuality + $supportLevel + $sleepQuality) / 5;

        return [
            'stress_level' => (int) round($stressRaw * 25),
            'burnout_index' => (int) round($burnoutRaw * 25),
            'mood_score' => (int) round($moodRaw * 25),
        ];
    }

    private function buildCheckInRecommendations(array $scores): string
    {
        $stress = (int) ($scores['stress_level'] ?? 0);
        $burnout = (int) ($scores['burnout_index'] ?? 0);
        $mood = (int) ($scores['mood_score'] ?? 0);

        $tips = [];

        if ($stress >= 70) {
            $tips[] = 'High stress detected. Block two short recovery breaks between sessions and reduce non-urgent tasks today.';
        } elseif ($stress >= 45) {
            $tips[] = 'Moderate stress detected. Add a 10-minute reset after every 2 sessions.';
        }

        if ($burnout >= 70) {
            $tips[] = 'Burnout risk is high. Please escalate workload concerns to your supervisor and avoid overtime this week.';
        } elseif ($burnout >= 45) {
            $tips[] = 'Burnout risk is moderate. Prioritize sleep, boundaries, and peer support check-ins.';
        }

        if ($mood <= 35) {
            $tips[] = 'Wellbeing score is low. Consider a same-week peer debrief or counselor support session.';
        } elseif ($mood <= 55) {
            $tips[] = 'Wellbeing is fair. Maintain routines: hydration, meal timing, and short movement breaks.';
        }

        if (empty($tips)) {
            $tips[] = 'Wellness looks stable. Keep your current routines and continue daily check-ins.';
        }

        return implode(' ', $tips);
    }
}








