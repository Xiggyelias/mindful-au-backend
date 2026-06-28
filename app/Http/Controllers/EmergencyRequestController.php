<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Models\CounselingSession;
use App\Models\CounselorSlot;
use App\Models\EmergencyRequest;
use App\Models\Notification;
use App\Models\User;
use App\Services\CounselorSlotService;
use App\Support\AnalyticsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            ->with($this->emergencyRequestRelations())
            ->orderByRaw("CASE status WHEN 'queued' THEN 0 WHEN 'assigned' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderBy('priority')
            ->orderByDesc('requested_at');

        if ($user->hasRole('student')) {
            $query->where('student_id', $user->id);
        } elseif (! $user->hasRole('admin') && ! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($query->limit(200)->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('student')) {
            return response()->json(['message' => 'Only students can create emergency requests'], 403);
        }

        $validated = $request->validate([
            'counselor_id' => 'nullable|integer|exists:users,id',
            'requested_at' => 'nullable|date',
            'reason' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:500',
        ]);

        $studentId = $request->user()->id;

        // Return the existing active request instead of creating a duplicate.
        $existing = EmergencyRequest::query()
            ->where('student_id', $studentId)
            ->whereIn('status', ['queued', 'assigned'])
            ->with(['student.profile', 'counselor.profile'])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have an active emergency request.',
                'emergency_request' => $existing,
                'recipients_notified' => 0,
            ]);
        }

        $requestedAt = ! empty($validated['requested_at']) ? Carbon::parse($validated['requested_at']) : now();
        $counselorId = ! empty($validated['counselor_id']) ? (int) $validated['counselor_id'] : null;
        $isAfterHours = $counselorId
            ? $this->slotService->isOutsideNormalBookingWindow($counselorId, $requestedAt)
            : $this->isDefaultAfterHours($requestedAt);

        $emergencyRequest = EmergencyRequest::query()->create([
            'student_id' => $studentId,
            'counselor_id' => $counselorId,
            'requested_at' => $requestedAt,
            'is_after_hours' => $isAfterHours,
            'priority' => 1,
            'status' => 'queued',
            'location' => $validated['location'] ?? null,
            'reason' => $validated['reason'] ?? null,
        ]);
        AnalyticsCache::clear();

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
        if (! $user->hasRole('admin') && ! $user->hasRole('counselor')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $emergencyRequest = EmergencyRequest::query()->findOrFail($id);
        $validated = $request->validate([
            'status' => 'sometimes|in:queued,assigned,resolved,cancelled',
            'assigned_to' => 'sometimes|nullable|integer|exists:users,id',
            'prepare_slot' => 'sometimes|boolean',
        ]);

        $oldStatus = $emergencyRequest->status;
        $hasResolvedBy = $this->hasEmergencyRequestColumn('resolved_by');
        $hasResolvedAt = $this->hasEmergencyRequestColumn('resolved_at');
        $prepareSlotRequested = (bool) ($validated['prepare_slot'] ?? false);
        unset($validated['prepare_slot']);

        if (($validated['status'] ?? null) === 'resolved' && $emergencyRequest->status !== 'resolved') {
            if ($hasResolvedBy) {
                $validated['resolved_by'] = $user->id;
            }
            if ($hasResolvedAt) {
                $validated['resolved_at'] = now();
            }
        }

        if (($validated['status'] ?? null) === 'assigned' && empty($validated['assigned_to'])) {
            $validated['assigned_to'] = $user->id;
        }

        if ($prepareSlotRequested && ($validated['status'] ?? $emergencyRequest->status) !== 'assigned') {
            $validated['status'] = 'assigned';
        }

        if ($prepareSlotRequested && empty($validated['assigned_to']) && empty($emergencyRequest->assigned_to)) {
            $validated['assigned_to'] = $user->id;
        }

        $slot = null;
        $slotStart = null;
        $canPreparePrioritySlot = $this->hasEmergencyRequestColumn('counselor_slot_id')
            && Schema::hasTable('counselor_slots');
        $shouldPreparePrioritySlot =
            (($validated['status'] ?? null) === 'assigned' || $prepareSlotRequested)
            && $canPreparePrioritySlot
            && ($oldStatus !== 'assigned' || empty($emergencyRequest->counselor_slot_id));

        if ($shouldPreparePrioritySlot) {
            $counselorId = (int) ($validated['assigned_to'] ?? $emergencyRequest->assigned_to ?? 0);
            if ($counselorId <= 0) {
                return response()->json(['message' => 'Assign the emergency request before preparing a slot.'], 422);
            }

            $requestedAt = $this->toScheduleTimezone($emergencyRequest->requested_at ?? now());
            $slotStart = $requestedAt->copy();
            $minimumStart = $this->toScheduleTimezone(now())->addMinutes(10);
            if ($slotStart->lessThan($minimumStart)) {
                $slotStart = $minimumStart;
            }
            $slotStart = $this->roundUpToQuarterHour($slotStart);

            $duration = 60;

            $slot = null;
            $endTime = null;
            for ($attempt = 0; $attempt < 24; $attempt++) {
                $endTime = $slotStart->copy()->addMinutes($duration);
                $storageStart = $slotStart->copy()->utc();
                $storageEnd = $endTime->copy()->utc();
                $existingSlot = CounselorSlot::query()
                    ->where('counselor_id', $counselorId)
                    ->where('start_time', $storageStart->toDateTimeString())
                    ->where('end_time', $storageEnd->toDateTimeString())
                    ->first();

                if ($existingSlot) {
                    if ($existingSlot->status === 'available' && empty($existingSlot->appointment_id)) {
                        $slot = $existingSlot;
                        break;
                    }
                    $slotStart->addMinutes($duration);

                    continue;
                }

                $slot = CounselorSlot::query()->create([
                    'counselor_id' => $counselorId,
                    'counselor_schedule_id' => null,
                    'slot_date' => $slotStart->toDateString(),
                    'day_of_week' => (int) $slotStart->isoWeekday(),
                    'start_time' => $storageStart,
                    'end_time' => $storageEnd,
                    'status' => 'available',
                ]);
                break;
            }

            if (! $slot || ! $endTime) {
                // If the counselor explicitly requested slot preparation, surface the
                // failure so they know to free up their schedule first.
                if ($prepareSlotRequested) {
                    return response()->json(['message' => 'No emergency slot is currently available for this counselor.'], 422);
                }
                // Auto-triggered by "Take Case" — proceed without a priority slot.
                // The student will be told to pick any available time themselves.
                $slot = null;
            } else {
                $validated['counselor_slot_id'] = $slot->id;
            }
        }

        $emergencyRequest->update($validated);
        AnalyticsCache::clear();

        if ($shouldPreparePrioritySlot) {
            $counselorId = (int) ($validated['assigned_to'] ?? $emergencyRequest->assigned_to);
            $studentId = $emergencyRequest->student_id;

            if ($slot && $slotStart) {
                // Priority slot was prepared — create/resume the chat session and
                // send the student a direct booking link for the prepared slot.
                $session = CounselingSession::query()
                    ->where('student_id', $studentId)
                    ->where('counselor_id', $counselorId)
                    ->where('session_type', 'chat')
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->latest('id')
                    ->first();

                if (! $session) {
                    $session = CounselingSession::query()->create([
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

                $formattedTime = $slotStart->format('M j, Y g:i A');

                try {
                    $notification = Notification::query()->create([
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
                    event(new NotificationCreated($notification));
                } catch (\Throwable $_) {
                    // no-op
                }
            } else {
                // No priority slot could be prepared — notify the student to pick
                // any time themselves via the emergency custom time picker.
                try {
                    $notification = Notification::query()->create([
                        'user_id' => $studentId,
                        'title' => 'Emergency Request Accepted',
                        'message' => 'A counselor has accepted your emergency request. Please go to Appointments to choose your preferred session time.',
                        'meta' => [
                            'counselor_id' => (int) $counselorId,
                            'path' => '/student/appointments',
                        ],
                        'type' => 'info',
                    ]);
                    event(new NotificationCreated($notification));
                } catch (\Throwable $_) {
                    // no-op
                }
            }
        }

        return response()->json($emergencyRequest->refresh()->load($this->emergencyRequestRelations()));
    }

    /**
     * @return array<int, string>
     */
    private function emergencyRequestRelations(): array
    {
        $relations = ['student.profile', 'counselor.profile', 'assignee.profile', 'resolver.profile'];
        if ($this->hasEmergencyRequestColumn('counselor_slot_id') && Schema::hasTable('counselor_slots')) {
            $relations[] = 'slot';
        }

        return $relations;
    }

    private function hasEmergencyRequestColumn(string $column): bool
    {
        if (! Schema::hasTable('emergency_requests')) {
            return false;
        }

        return in_array($column, Schema::getColumnListing('emergency_requests'), true);
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
            $emergencyRequest->reason ? 'Reason: '.Str::limit($emergencyRequest->reason, 180) : 'Please review the priority dashboard.'
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

        $recipients = $recipientQuery->with('roles')->get()->unique('id')->values();
        $notified = 0;
        foreach ($recipients as $recipient) {
            try {
                $notification = Notification::query()->create([
                    'user_id' => (int) $recipient->id,
                    'title' => 'Emergency Support Request',
                    'message' => $message,
                    'type' => 'panic',
                    'read' => false,
                    'meta' => [
                        'emergency_request_id' => (int) $emergencyRequest->id,
                        'path' => $recipient->hasRole('admin') ? '/admin/alerts' : '/counselor/alerts',
                    ],
                ]);
                event(new NotificationCreated($notification));
                $notified++;
            } catch (\Throwable $exception) {
                Log::warning('Emergency request notification failed', [
                    'emergency_request_id' => $emergencyRequest->id,
                    'recipient_id' => (int) $recipient->id,
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

    private function toScheduleTimezone(Carbon $date): Carbon
    {
        return $date->copy()->timezone($this->scheduleTimezone());
    }

    private function roundUpToQuarterHour(Carbon $date): Carbon
    {
        $rounded = $date->copy()->second(0);
        if ($rounded->minute % 15 === 0) {
            return $rounded;
        }

        return $rounded->addMinutes(15 - ($rounded->minute % 15));
    }

    private function scheduleTimezone(): string
    {
        return (string) config('app.schedule_timezone', 'Africa/Harare');
    }
}
