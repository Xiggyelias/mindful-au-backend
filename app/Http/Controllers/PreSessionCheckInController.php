<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\PreSessionCheckIn;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreSessionCheckInController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPush
    ) {}

    /**
     * Store a 30-second pre-session check-in from a student.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('student')) {
            return response()->json(['message' => 'Only students can submit a pre-session check-in.'], 403);
        }

        $validated = $request->validate([
            'mood' => 'required|string|in:great,calm,okay,down,anxious,crisis',
            'mood_score' => 'required|integer|min:1|max:5',
            'energy_level' => 'required|integer|min:1|max:5',
            'stress_level' => 'required|integer|min:1|max:5',
            'primary_concerns' => 'nullable|array|max:10',
            'primary_concerns.*' => 'string|max:64',
            'session_goal' => 'nullable|string|max:64',
            'student_notes' => 'nullable|string|max:1000',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'session_id' => 'nullable|integer|exists:counseling_sessions,id',
        ]);

        $appointmentId = $validated['appointment_id'] ?? null;
        $sessionId = $validated['session_id'] ?? null;
        $counselorId = null;

        if ($appointmentId) {
            $appointment = Appointment::find($appointmentId);
            if ($appointment && (int) $appointment->student_id === (int) $user->id) {
                $counselorId = $appointment->counselor_id;
            }
        }

        if (! $counselorId && $sessionId) {
            $session = CounselingSession::find($sessionId);
            if ($session && (int) $session->student_id === (int) $user->id) {
                $counselorId = $session->counselor_id;
            }
        }

        if (! $counselorId) {
            // Find upcoming or latest appointment
            $latestAppt = Appointment::where('student_id', $user->id)
                ->whereNotNull('counselor_id')
                ->where('scheduled_at', '>=', now()->subHours(2))
                ->orderBy('scheduled_at')
                ->first();
            $counselorId = $latestAppt?->counselor_id;
            if (! $appointmentId && $latestAppt) {
                $appointmentId = $latestAppt->id;
            }
        }

        $isCrisis = ($validated['mood'] === 'crisis') || ((int) $validated['stress_level'] === 5 && (int) $validated['mood_score'] === 1);

        $checkIn = PreSessionCheckIn::create([
            'student_id' => $user->id,
            'counselor_id' => $counselorId,
            'appointment_id' => $appointmentId,
            'session_id' => $sessionId,
            'mood' => $validated['mood'],
            'mood_score' => (int) $validated['mood_score'],
            'energy_level' => (int) $validated['energy_level'],
            'stress_level' => (int) $validated['stress_level'],
            'primary_concerns' => $validated['primary_concerns'] ?? [],
            'session_goal' => $validated['session_goal'] ?? null,
            'student_notes' => $validated['student_notes'] ?? null,
            'is_crisis' => $isCrisis,
        ]);

        // Seamlessly satisfy any pending assessment requirement
        if ($user->needs_assessment) {
            $user->update(['needs_assessment' => false]);
        }

        // Notify counselor if assigned
        if ($counselorId) {
            $studentName = $user->profile?->anonymous_mode ? "Anonymous Student #{$user->id}" : ($user->profile?->full_name ?: 'Student');
            $title = $isCrisis ? '🚨 Urgent: Student Pre-Session Crisis Alert' : '📋 Student Pre-Session Pulse Received';
            $message = $isCrisis
                ? "{$studentName} indicated an elevated crisis state in their pre-session check-in."
                : "{$studentName} completed their pre-session check-in (Mood: {$validated['mood']}, Stress: {$validated['stress_level']}/5).";

            Notification::create([
                'user_id' => $counselorId,
                'title' => $title,
                'message' => $message,
                'type' => $isCrisis ? 'error' : 'info',
                'meta' => [
                    'check_in_id' => $checkIn->id,
                    'student_id' => $user->id,
                    'path' => '/counselor/video',
                ],
            ]);

            try {
                $this->webPush->sendToUser(
                    (int) $counselorId,
                    $title,
                    $message,
                    '/counselor/video',
                    [
                        'tag' => "check-in-{$checkIn->id}",
                        'urgency' => $isCrisis ? 'high' : 'normal',
                    ]
                );
            } catch (\Throwable) {
                // ignore
            }
        }

        return response()->json([
            'message' => 'Pre-session check-in submitted successfully.',
            'check_in' => $checkIn,
            'clinical_summary' => $checkIn->toClinicalSummary(),
        ], 201);
    }

    /**
     * Get the latest check-in for a student or specific appointment/session.
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $request->query('student_id');
        $appointmentId = $request->query('appointment_id');
        $sessionId = $request->query('session_id');

        $query = PreSessionCheckIn::with(['student.profile', 'counselor.profile']);

        if ($appointmentId) {
            $query->where('appointment_id', (int) $appointmentId);
        } elseif ($sessionId) {
            $query->where('session_id', (int) $sessionId);
        } elseif ($studentId) {
            $query->where('student_id', (int) $studentId);
        } elseif ($user->hasRole('student')) {
            $query->where('student_id', (int) $user->id);
        }

        // Authorization check
        if ($user->hasRole('student')) {
            $query->where('student_id', (int) $user->id);
        } elseif ($user->hasRole('counselor')) {
            // Counselors can view check-ins assigned to them or where student has session/appointment with them
            $query->where(function ($q) use ($user) {
                $q->where('counselor_id', $user->id)
                    ->orWhereHas('appointment', fn ($aq) => $aq->where('counselor_id', $user->id))
                    ->orWhereHas('session', fn ($sq) => $sq->where('counselor_id', $user->id));
            });
        } elseif (! $user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $checkIn = $query->latest('id')->first();

        if (! $checkIn) {
            return response()->json([
                'check_in' => null,
                'clinical_summary' => null,
            ]);
        }

        return response()->json([
            'check_in' => $checkIn,
            'clinical_summary' => $checkIn->toClinicalSummary(),
        ]);
    }

    /**
     * View history of check-ins to track student emotional progress over sessions.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $request->query('student_id') ?? ($user->hasRole('student') ? $user->id : null);

        if (! $studentId) {
            return response()->json(['message' => 'student_id is required.'], 422);
        }

        if ($user->hasRole('student') && (int) $studentId !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = PreSessionCheckIn::where('student_id', (int) $studentId);

        if ($user->hasRole('counselor')) {
            // verify relationship
            $hasRelationship = Appointment::where('student_id', $studentId)->where('counselor_id', $user->id)->exists()
                || CounselingSession::where('student_id', $studentId)->where('counselor_id', $user->id)->exists();

            if (! $hasRelationship) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        $history = $query->latest('id')->limit(12)->get();

        return response()->json([
            'student_id' => (int) $studentId,
            'history' => $history,
        ]);
    }

    /**
     * Counselor requests a student to complete a pre-session check-in.
     */
    public function requestCheckIn(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('counselor') && ! $user->hasRole('admin')) {
            return response()->json(['message' => 'Only counselors and admins can request check-ins.'], 403);
        }

        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
        ]);

        $student = User::findOrFail($validated['student_id']);
        if (! $student->hasRole('student')) {
            return response()->json(['message' => 'Target user must be a student.'], 422);
        }

        $counselorName = $user->profile?->full_name ?: 'Your counselor';
        $appointmentId = $validated['appointment_id'] ?? null;

        Notification::create([
            'user_id' => $student->id,
            'title' => '🌟 Quick Check-In for Your Session',
            'message' => "{$counselorName} has requested a brief 30-second wellness check-in before your session.",
            'type' => 'info',
            'meta' => [
                'pre_session_request' => true,
                'appointment_id' => $appointmentId,
                'counselor_id' => $user->id,
                'path' => $appointmentId ? "/student/video-call?appointment_id={$appointmentId}&checkin=1" : '/student/appointments?checkin=1',
            ],
        ]);

        try {
            $this->webPush->sendToUser(
                (int) $student->id,
                '🌟 Quick Check-In for Your Session',
                "{$counselorName} sent a 30-second pre-session check-in. Tap to complete before meeting.",
                $appointmentId ? "/student/video-call?appointment_id={$appointmentId}&checkin=1" : '/student/appointments',
                [
                    'tag' => "pre-session-req-{$student->id}",
                    'urgency' => 'normal',
                ]
            );
        } catch (\Throwable) {
            // ignore
        }

        return response()->json([
            'message' => 'Pre-session check-in request sent to student.',
            'student_id' => $student->id,
        ]);
    }
}
