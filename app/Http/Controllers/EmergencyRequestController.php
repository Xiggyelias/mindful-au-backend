<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\EmergencyRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\CounselorSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmergencyRequestController extends Controller
{
    public function __construct(
        private readonly CounselorSlotService $slotService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = EmergencyRequest::query()
            ->with(['student.profile', 'counselor.profile', 'assignee.profile', 'resolver.profile'])
            ->orderByRaw("CASE status WHEN 'queued' THEN 0 WHEN 'assigned' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderBy('priority')
            ->orderByDesc('requested_at');

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($query->limit(200)->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can create emergency requests'], 403);
        }

        $validated = $request->validate([
            'counselor_id' => 'nullable|integer|exists:users,id',
            'requested_at' => 'nullable|date',
            'reason' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:500',
        ]);

        $requestedAt = !empty($validated['requested_at']) ? Carbon::parse($validated['requested_at']) : now();
        $counselorId = !empty($validated['counselor_id']) ? (int) $validated['counselor_id'] : null;
        $isAfterHours = $counselorId
            ? $this->slotService->isOutsideNormalBookingWindow($counselorId, $requestedAt)
            : $this->isDefaultAfterHours($requestedAt);

        $emergencyRequest = EmergencyRequest::query()->create([
            'student_id' => $request->user()->id,
            'counselor_id' => $counselorId,
            'requested_at' => $requestedAt,
            'is_after_hours' => $isAfterHours,
            'priority' => 1,
            'status' => 'queued',
            'location' => $validated['location'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ]);

        $recipientsNotified = $this->notifyEmergencyQueue($emergencyRequest);

        return response()->json([
            'message' => 'Emergency request queued for priority counselor review.',
            'emergency_request' => $emergencyRequest->load(['student.profile', 'counselor.profile']),
            'recipients_notified' => $recipientsNotified,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $emergencyRequest = EmergencyRequest::query()->findOrFail($id);
        $validated = $request->validate([
            'status' => 'sometimes|in:queued,assigned,resolved,cancelled',
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $oldStatus = $emergencyRequest->status;

        if (($validated['status'] ?? null) === 'resolved' && $emergencyRequest->status !== 'resolved') {
            $validated['resolved_by'] = $user->id;
            $validated['resolved_at'] = now();
        }

        if (($validated['status'] ?? null) === 'assigned' && empty($validated['assigned_to'])) {
            $validated['assigned_to'] = $user->id;
        }

        $emergencyRequest->update($validated);

        if (($validated['status'] ?? null) === 'assigned' && $oldStatus !== 'assigned') {
            $counselorId = $emergencyRequest->assigned_to;
            $studentId = $emergencyRequest->student_id;
            $requestedAt = $emergencyRequest->requested_at ?? now();

            $dayOfWeek = (int) $requestedAt->isoWeekday();
            $schedule = \App\Models\CounselorSchedule::query()
                ->where('counselor_id', $counselorId)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            $duration = 60; // Default to 60 minutes
            if ($schedule) {
                $duration = max(30, (int) $schedule->slot_duration_minutes);
            }

            $endTime = $requestedAt->copy()->addMinutes($duration);

            $slot = \App\Models\CounselorSlot::query()->create([
                'counselor_id' => $counselorId,
                'counselor_schedule_id' => $schedule?->id,
                'slot_date' => $requestedAt->toDateString(),
                'day_of_week' => $dayOfWeek,
                'start_time' => $requestedAt,
                'end_time' => $endTime,
                'status' => 'available',
            ]);

            $session = \App\Models\CounselingSession::query()
                ->where('student_id', $studentId)
                ->where('counselor_id', $counselorId)
                ->where('session_type', 'chat')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->latest('id')
                ->first();

            if (!$session) {
                $session = \App\Models\CounselingSession::query()->create([
                    'student_id' => $studentId,
                    'counselor_id' => $counselorId,
                    'session_type' => 'chat',
                    'status' => 'active',
                    'assigned_role' => 'counselor',
                    'is_anonymous' => false,
                ]);
            } elseif ($session->status === 'pending') {
                $session->update(['status' => 'active', 'started_at' => now()]);
            }

            $formattedTime = $requestedAt->format('M j, Y g:i A');

            try {
                $notification = \App\Models\Notification::query()->create([
                    'user_id' => $studentId,
                    'title' => 'Emergency Request Accepted',
                    'message' => "Counselor accepted your emergency request. Please book your slot at {$formattedTime}.",
                    'meta' => [
                        'counselor_id' => (int) $counselorId,
                        'slot_id' => (int) $slot->id,
                        'path' => "/student/appointments?book=1&counselor_id={$counselorId}&slot_id={$slot->id}",
                    ],
                    'type' => 'info',
                ]);
                event(new \App\Events\NotificationCreated($notification));
            } catch (\Throwable $_) {
                // no-op
            }
        }

        return response()->json($emergencyRequest->refresh()->load(['student.profile', 'counselor.profile', 'assignee.profile', 'resolver.profile']));
    }

    private function notifyEmergencyQueue(EmergencyRequest $emergencyRequest): int
    {
        $emergencyRequest->loadMissing(['student.profile', 'counselor.profile']);
        $studentName = trim((string) ($emergencyRequest->student?->profile?->full_name ?? ''));
        if ($studentName === '') {
            $studentName = $emergencyRequest->student?->email
                ? Str::before((string) $emergencyRequest->student->email, '@')
                : 'A student';
        }

        $message = sprintf(
            'Emergency support request from %s at %s. %s',
            $studentName,
            $emergencyRequest->requested_at?->format('M j, Y g:i A') ?? 'now',
            $emergencyRequest->reason ? 'Reason: ' . Str::limit($emergencyRequest->reason, 180) : 'Please review the priority dashboard.'
        );

        $recipientQuery = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where(function ($scoped): void {
                        $scoped->where('role', 'counselor')->where('approved', true);
                    })->orWhere('role', 'admin');
                });
            });

        if ($emergencyRequest->counselor_id) {
            $recipientQuery->orWhere('id', $emergencyRequest->counselor_id);
        }

        $recipientIds = $recipientQuery->pluck('id')->unique()->values();
        $notified = 0;
        foreach ($recipientIds as $recipientId) {
            try {
                $recipient = User::query()->find((int) $recipientId);
                $notification = Notification::query()->create([
                    'user_id' => (int) $recipientId,
                    'title' => 'Emergency Support Request',
                    'message' => $message,
                    'type' => 'panic',
                    'read' => false,
                    'meta' => [
                        'emergency_request_id' => (int) $emergencyRequest->id,
                        'path' => $recipient?->hasRole('admin') ? '/admin/alerts' : '/counselor/alerts',
                    ],
                ]);
                event(new NotificationCreated($notification));
                $notified++;
            } catch (\Throwable $exception) {
                Log::warning('Emergency request notification failed', [
                    'emergency_request_id' => $emergencyRequest->id,
                    'recipient_id' => (int) $recipientId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $notified;
    }

    private function isDefaultAfterHours(Carbon $date): bool
    {
        $day = (int) $date->isoWeekday();
        if ($day < 1 || $day > 5) {
            return true;
        }

        $minutes = ((int) $date->format('H') * 60) + (int) $date->format('i');
        return $minutes < (8 * 60) || $minutes >= ((16 * 60) + 30);
    }
}
