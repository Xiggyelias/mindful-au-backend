<?php

namespace App\Http\Controllers;

use App\Models\CounselorSlot;
use App\Models\User;
use App\Services\CounselorSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CounselorSlotController extends Controller
{
    public function __construct(
        private readonly CounselorSlotService $slotService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'counselor_id' => 'required|integer|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'generate' => 'nullable|boolean',
        ]);

        $counselorId = (int) $validated['counselor_id'];
        if (!$this->canViewCounselorSlots($request->user(), $counselorId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$this->isApprovedCounselor($counselorId)) {
            return response()->json(['message' => 'Selected counselor is not available'], 422);
        }

        $from = !empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->startOfDay();
        $to = !empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->addDays(7)->endOfDay();

        if ($to->lt($from)) {
            return response()->json(['message' => 'The to date must be after from date.'], 422);
        }

        if ($from->diffInDays($to) > 45) {
            return response()->json(['message' => 'Slot range cannot exceed 45 days.'], 422);
        }

        $slots = $this->slotService->slotsForRange(
            $counselorId,
            $from,
            $to,
            $request->boolean('generate', true)
        );

        return response()->json([
            'data' => $slots->map(fn (CounselorSlot $slot) => $this->slotPayload($slot))->values(),
            'schedules' => $this->slotService->schedulesFor($counselorId)->values(),
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'slot_duration_minutes' => 30,
                'max_slots_per_day' => 6,
                'working_hours' => [
                    'start' => '08:00',
                    'end' => '16:00',
                    'lunch_start' => '13:00',
                    'lunch_end' => '14:00',
                ],
            ],
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'counselor_id' => 'nullable|integer|exists:users,id',
        ]);
        $counselorId = $this->resolveManagedCounselorId($request, $validated['counselor_id'] ?? null);
        if ($counselorId === null) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->slotService->schedulesFor($counselorId)->values(),
        ]);
    }

    public function updateSchedules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'counselor_id' => 'nullable|integer|exists:users,id',
            'schedules' => 'required|array|min:1|max:7',
            'schedules.*.day_of_week' => 'required|integer|min:1|max:7',
            'schedules.*.is_working_day' => 'required|boolean',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i',
            'schedules.*.break_start' => 'nullable|date_format:H:i',
            'schedules.*.break_end' => 'nullable|date_format:H:i',
            'schedules.*.slot_duration_minutes' => 'required|integer|min:30|max:360',
        ]);

        foreach ($validated['schedules'] as $schedule) {
            if (strcmp((string) $schedule['end_time'], (string) $schedule['start_time']) <= 0) {
                return response()->json(['message' => 'Schedule end time must be after start time.'], 422);
            }
            if (strcmp((string) $schedule['end_time'], '16:00') > 0) {
                return response()->json(['message' => 'Schedule end time cannot be after 16:00.'], 422);
            }
            if (!empty($schedule['break_start']) && !empty($schedule['break_end']) && strcmp($schedule['break_end'], $schedule['break_start']) <= 0) {
                return response()->json(['message' => 'Lunch break end time must be after break start time.'], 422);
            }
        }

        $counselorId = $this->resolveManagedCounselorId($request, $validated['counselor_id'] ?? null);
        if ($counselorId === null) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Counselor schedule updated.',
            'data' => $this->slotService->updateSchedules($counselorId, $validated['schedules'])->values(),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'counselor_id' => 'nullable|integer|exists:users,id',
            'week_start' => 'nullable|date',
            'weeks' => 'nullable|integer|min:1|max:8',
        ]);

        $counselorId = $this->resolveManagedCounselorId($request, $validated['counselor_id'] ?? null);
        if ($counselorId === null) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $weekStart = !empty($validated['week_start'])
            ? Carbon::parse($validated['week_start'])->startOfWeek()
            : now()->startOfWeek();
        $weeks = (int) ($validated['weeks'] ?? 1);
        $to = $weekStart->copy()->addWeeks($weeks)->subDay()->endOfDay();
        $slots = $this->slotService->generateSlotsForRange($counselorId, $weekStart, $to);

        return response()->json([
            'message' => 'Slots generated.',
            'generated_count' => $slots->count(),
            'data' => $slots->map(fn (CounselorSlot $slot) => $this->slotPayload($slot))->values(),
        ], 201);
    }

    private function resolveManagedCounselorId(Request $request, mixed $requestedId): ?int
    {
        $user = $request->user();
        if ($user->hasRole('admin')) {
            return $requestedId ? (int) $requestedId : null;
        }

        if ($user->hasRole('counselor')) {
            $id = $requestedId ? (int) $requestedId : (int) $user->id;
            return $id === (int) $user->id ? $id : null;
        }

        return null;
    }

    private function canViewCounselorSlots(User $user, int $counselorId): bool
    {
        if ($user->hasRole('student') || $user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('counselor') && (int) $user->id === $counselorId;
    }

    private function isApprovedCounselor(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->whereHas('roles', fn ($query) => $query->where('role', 'counselor')->where('approved', true))
            ->exists();
    }

    private function slotPayload(CounselorSlot $slot): array
    {
        return [
            'id' => (int) $slot->id,
            'counselor_id' => (int) $slot->counselor_id,
            'appointment_id' => $slot->appointment_id ? (int) $slot->appointment_id : null,
            'slot_date' => $slot->slot_date?->toDateString(),
            'day_of_week' => (int) $slot->day_of_week,
            'start_time' => $slot->start_time?->toIso8601String(),
            'end_time' => $slot->end_time?->toIso8601String(),
            'status' => $slot->status,
        ];
    }
}
