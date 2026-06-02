<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CounselorSchedule;
use App\Models\CounselorSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CounselorSlotService
{
    private const DEFAULT_WORKING_DAYS = [1, 2, 3, 4, 5];
    private const DEFAULT_START_TIME = '08:00:00';
    private const DEFAULT_END_TIME = '16:00:00';
    private const DEFAULT_BREAK_START = '13:00:00';
    private const DEFAULT_BREAK_END = '14:00:00';
    private const DEFAULT_SLOT_DURATION_MINUTES = 30;
    private const DEFAULT_MAX_SLOTS_PER_DAY = 6;

    public function schedulesFor(int $counselorId): Collection
    {
        $this->ensureDefaultSchedules($counselorId);

        return CounselorSchedule::query()
            ->where('counselor_id', $counselorId)
            ->orderBy('day_of_week')
            ->get();
    }

    public function ensureDefaultSchedules(int $counselorId): void
    {
        for ($day = 1; $day <= 7; $day++) {
            $schedule = CounselorSchedule::query()->firstOrCreate(
                [
                    'counselor_id' => $counselorId,
                    'day_of_week' => $day,
                ],
                [
                    'is_working_day' => in_array($day, self::DEFAULT_WORKING_DAYS, true),
                    'start_time' => self::DEFAULT_START_TIME,
                    'end_time' => self::DEFAULT_END_TIME,
                    'break_start' => self::DEFAULT_BREAK_START,
                    'break_end' => self::DEFAULT_BREAK_END,
                    'slot_duration_minutes' => self::DEFAULT_SLOT_DURATION_MINUTES,
                ]
            );

            if ($this->minutesSinceMidnight((string) $schedule->end_time) > $this->minutesSinceMidnight(self::DEFAULT_END_TIME)) {
                $schedule->update(['end_time' => self::DEFAULT_END_TIME]);
            }
        }
    }

    public function updateSchedules(int $counselorId, array $scheduleRows): Collection
    {
        $this->ensureDefaultSchedules($counselorId);

        foreach ($scheduleRows as $row) {
            $day = max(1, min(7, (int) ($row['day_of_week'] ?? 0)));
            if ($day < 1 || $day > 7) {
                continue;
            }

            CounselorSchedule::query()->updateOrCreate(
                [
                    'counselor_id' => $counselorId,
                    'day_of_week' => $day,
                ],
                [
                    'is_working_day' => (bool) ($row['is_working_day'] ?? true),
                    'start_time' => $this->normalizeTime($row['start_time'] ?? null, self::DEFAULT_START_TIME),
                    'end_time' => $this->capEndTime($this->normalizeTime($row['end_time'] ?? null, self::DEFAULT_END_TIME)),
                    'break_start' => $this->normalizeNullableTime($row['break_start'] ?? null),
                    'break_end' => $this->normalizeNullableTime($row['break_end'] ?? null),
                    'slot_duration_minutes' => max(15, min(120, (int) ($row['slot_duration_minutes'] ?? self::DEFAULT_SLOT_DURATION_MINUTES))),
                ]
            );
        }

        return $this->schedulesFor($counselorId);
    }

    public function generateSlotsForRange(int $counselorId, Carbon $from, Carbon $to): Collection
    {
        $schedules = $this->schedulesFor($counselorId)->keyBy('day_of_week');
        $createdOrUpdated = collect();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $dayOfWeek = (int) $cursor->isoWeekday();
            /** @var CounselorSchedule|null $schedule */
            $schedule = $schedules->get($dayOfWeek);
            if (!$schedule || !$schedule->is_working_day) {
                $this->deleteStaleGeneratedSlotsForDate($counselorId, $cursor, []);
                $cursor->addDay();
                continue;
            }

            $windows = $this->slotWindowsForDate($schedule, $cursor);
            foreach ($windows as $window) {
                [$slotStart, $slotEnd] = $window;
                $slot = CounselorSlot::query()->firstOrNew([
                    'counselor_id' => $counselorId,
                    'start_time' => $slotStart->toDateTimeString(),
                    'end_time' => $slotEnd->toDateTimeString(),
                ]);

                $slot->fill([
                    'counselor_schedule_id' => $schedule->id,
                    'slot_date' => $cursor->toDateString(),
                    'day_of_week' => $dayOfWeek,
                    'status' => $slot->exists && $slot->status === 'booked' ? $slot->status : 'available',
                ]);
                $slot->save();
                $createdOrUpdated->push($slot);
            }
            $this->deleteStaleGeneratedSlotsForDate($counselorId, $cursor, $windows);

            $cursor->addDay();
        }

        $this->refreshSlotStatuses($createdOrUpdated);

        return $createdOrUpdated->sortBy('start_time')->values();
    }

    public function slotsForRange(int $counselorId, Carbon $from, Carbon $to, bool $generateMissing = true): Collection
    {
        if ($generateMissing) {
            $this->generateSlotsForRange($counselorId, $from, $to);
        }

        $slots = CounselorSlot::query()
            ->with(['appointment:id,status'])
            ->where('counselor_id', $counselorId)
            ->where('start_time', '>=', $from->copy()->startOfDay()->toDateTimeString())
            ->where('start_time', '<=', $to->copy()->endOfDay()->toDateTimeString())
            ->orderBy('start_time')
            ->get();

        $this->refreshSlotStatuses($slots);

        return $this->filterSlotsToCurrentSchedule($slots, $counselorId);
    }

    /**
     * @return array{slot: CounselorSlot|null, reason: string}
     */
    public function resolveSlotForBooking(int $counselorId, Carbon $start, int $durationMinutes): array
    {
        $durationMinutes = max(15, min(120, $durationMinutes));
        if ($this->isOutsideNormalBookingWindow($counselorId, $start, $durationMinutes)) {
            return ['slot' => null, 'reason' => 'outside_hours'];
        }

        if ($this->overlapsBreak($counselorId, $start, $durationMinutes)) {
            return ['slot' => null, 'reason' => 'lunch_break'];
        }

        $this->generateSlotsForRange($counselorId, $start->copy()->startOfDay(), $start->copy()->endOfDay());
        $end = $start->copy()->addMinutes($durationMinutes);
        $slot = CounselorSlot::query()
            ->where('counselor_id', $counselorId)
            ->where('start_time', $start->toDateTimeString())
            ->where('end_time', $end->toDateTimeString())
            ->first();

        if (!$slot) {
            return ['slot' => null, 'reason' => 'unavailable'];
        }

        if ($slot->status !== 'available' || $slot->appointment_id) {
            return ['slot' => $slot, 'reason' => 'booked'];
        }

        return ['slot' => $slot, 'reason' => 'available'];
    }

    public function isOutsideNormalBookingWindow(int $counselorId, Carbon $start, int $durationMinutes = 0): bool
    {
        $schedule = $this->scheduleForDate($counselorId, $start);
        if (!$schedule || !$schedule->is_working_day) {
            return true;
        }

        $minute = $this->minutesSinceMidnight($start->format('H:i:s'));
        $startMinute = $this->minutesSinceMidnight((string) $schedule->start_time);
        $endMinute = $this->minutesSinceMidnight((string) $schedule->end_time);
        if ($durationMinutes > 0) {
            $end = $start->copy()->addMinutes(max(15, min(120, $durationMinutes)));
            if ($end->toDateString() !== $start->toDateString()) {
                return true;
            }

            return $minute < $startMinute
                || $this->minutesSinceMidnight($end->format('H:i:s')) > $endMinute;
        }

        return $minute < $this->minutesSinceMidnight((string) $schedule->start_time)
            || $minute >= $this->minutesSinceMidnight((string) $schedule->end_time);
    }

    public function overlapsBreak(int $counselorId, Carbon $start, int $durationMinutes): bool
    {
        $schedule = $this->scheduleForDate($counselorId, $start);
        if (!$schedule || !$schedule->break_start || !$schedule->break_end) {
            return false;
        }

        $end = $start->copy()->addMinutes($durationMinutes);
        $breakStart = $this->dateTimeFor($start, (string) $schedule->break_start);
        $breakEnd = $this->dateTimeFor($start, (string) $schedule->break_end);

        return $start->lt($breakEnd) && $end->gt($breakStart);
    }

    public function releaseSlotForAppointment(Appointment $appointment): void
    {
        if (!$appointment->counselor_slot_id) {
            return;
        }

        CounselorSlot::query()
            ->where('id', $appointment->counselor_slot_id)
            ->where('appointment_id', $appointment->id)
            ->update([
                'status' => 'available',
                'appointment_id' => null,
            ]);
    }

    public function markSlotBooked(CounselorSlot $slot, Appointment $appointment): void
    {
        $slot->update([
            'status' => 'booked',
            'appointment_id' => $appointment->id,
        ]);
    }

    private function scheduleForDate(int $counselorId, Carbon $date): ?CounselorSchedule
    {
        return $this->schedulesFor($counselorId)->firstWhere('day_of_week', (int) $date->isoWeekday());
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function slotWindowsForDate(CounselorSchedule $schedule, Carbon $date): array
    {
        $duration = max(15, min(120, (int) $schedule->slot_duration_minutes));
        $dayStart = $this->dateTimeFor($date, (string) $schedule->start_time);
        $dayEnd = $this->dateTimeFor($date, (string) $schedule->end_time);
        $breakStart = $schedule->break_start ? $this->dateTimeFor($date, (string) $schedule->break_start) : null;
        $breakEnd = $schedule->break_end ? $this->dateTimeFor($date, (string) $schedule->break_end) : null;
        $windows = [];
        $cursor = $dayStart->copy();

        while ($cursor->copy()->addMinutes($duration)->lte($dayEnd)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes($duration);
            $overlapsBreak = $breakStart && $breakEnd && $slotStart->lt($breakEnd) && $slotEnd->gt($breakStart);
            if (!$overlapsBreak) {
                $windows[] = [$slotStart, $slotEnd];
                if (count($windows) >= self::DEFAULT_MAX_SLOTS_PER_DAY) {
                    break;
                }
            }
            $cursor->addMinutes($duration);
        }

        return $windows;
    }

    /**
     * @param  array<int, array{0: Carbon, 1: Carbon}>  $windows
     */
    private function deleteStaleGeneratedSlotsForDate(int $counselorId, Carbon $date, array $windows): void
    {
        $allowedKeys = [];
        foreach ($windows as [$slotStart, $slotEnd]) {
            $allowedKeys[$this->slotKey($slotStart, $slotEnd)] = true;
        }

        CounselorSlot::query()
            ->where('counselor_id', $counselorId)
            ->where('slot_date', $date->toDateString())
            ->whereNull('appointment_id')
            ->where('status', '!=', 'booked')
            ->get()
            ->each(function (CounselorSlot $slot) use ($allowedKeys): void {
                $key = $this->slotKey(Carbon::parse($slot->start_time), Carbon::parse($slot->end_time));
                if (! isset($allowedKeys[$key])) {
                    $slot->delete();
                }
            });
    }

    private function filterSlotsToCurrentSchedule(Collection $slots, int $counselorId): Collection
    {
        if ($slots->isEmpty()) {
            return $slots;
        }

        $schedules = $this->schedulesFor($counselorId)->keyBy('day_of_week');
        $allowedByDate = [];

        return $slots
            ->filter(function (CounselorSlot $slot) use ($schedules, &$allowedByDate): bool {
                $slotDate = $slot->slot_date?->toDateString() ?: Carbon::parse($slot->start_time)->toDateString();
                if (! isset($allowedByDate[$slotDate])) {
                    $dayOfWeek = (int) Carbon::parse($slotDate)->isoWeekday();
                    $schedule = $schedules->get($dayOfWeek);
                    $allowedByDate[$slotDate] = [];
                    if ($schedule && $schedule->is_working_day) {
                        foreach ($this->slotWindowsForDate($schedule, Carbon::parse($slotDate)) as [$slotStart, $slotEnd]) {
                            $allowedByDate[$slotDate][$this->slotKey($slotStart, $slotEnd)] = true;
                        }
                    }
                }

                $key = $this->slotKey(Carbon::parse($slot->start_time), Carbon::parse($slot->end_time));
                return isset($allowedByDate[$slotDate][$key]);
            })
            ->values();
    }

    private function refreshSlotStatuses(Collection $slots): void
    {
        if ($slots->isEmpty()) {
            return;
        }

        $counselorIds = $slots->pluck('counselor_id')->map(fn ($id) => (int) $id)->unique()->values();
        $minStart = Carbon::parse($slots->min('start_time'))->subMinutes(120);
        $maxEnd = Carbon::parse($slots->max('end_time'))->addMinutes(120);
        $appointments = Appointment::query()
            ->whereIn('counselor_id', $counselorIds)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_at', '<', $maxEnd)
            ->where('scheduled_at', '>', $minStart)
            ->get(['id', 'counselor_id', 'scheduled_at', 'duration_minutes']);

        foreach ($slots as $slot) {
            $slotStart = Carbon::parse($slot->start_time);
            $slotEnd = Carbon::parse($slot->end_time);
            $match = $appointments->first(function (Appointment $appointment) use ($slot, $slotStart, $slotEnd) {
                if ((int) $appointment->counselor_id !== (int) $slot->counselor_id) {
                    return false;
                }
                $appointmentStart = Carbon::parse($appointment->scheduled_at);
                $appointmentEnd = $appointmentStart->copy()->addMinutes((int) $appointment->duration_minutes);
                return $slotStart->lt($appointmentEnd) && $slotEnd->gt($appointmentStart);
            });

            $nextStatus = $match ? 'booked' : 'available';
            $nextAppointmentId = $match ? (int) $match->id : null;
            if ($slot->status !== $nextStatus || (int) ($slot->appointment_id ?? 0) !== (int) ($nextAppointmentId ?? 0)) {
                $slot->status = $nextStatus;
                $slot->appointment_id = $nextAppointmentId;
                $slot->save();
            }
        }
    }

    private function dateTimeFor(Carbon $date, string $time): Carbon
    {
        return Carbon::parse($date->toDateString() . ' ' . $this->normalizeTime($time, '00:00:00'));
    }

    private function normalizeTime(?string $value, string $fallback): string
    {
        $time = trim((string) ($value ?? ''));
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $time, $matches)) {
            $hour = max(0, min(23, (int) $matches[1]));
            $minute = max(0, min(59, (int) $matches[2]));
            $second = isset($matches[3]) ? max(0, min(59, (int) $matches[3])) : 0;
            return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }

        return $fallback;
    }

    private function normalizeNullableTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->normalizeTime((string) $value, self::DEFAULT_BREAK_START);
    }

    private function capEndTime(string $time): string
    {
        return $this->minutesSinceMidnight($time) > $this->minutesSinceMidnight(self::DEFAULT_END_TIME)
            ? self::DEFAULT_END_TIME
            : $time;
    }

    private function slotKey(Carbon $start, Carbon $end): string
    {
        return $start->toDateTimeString().'|'.$end->toDateTimeString();
    }

    private function minutesSinceMidnight(string $time): int
    {
        $normalized = $this->normalizeTime($time, '00:00:00');
        [$hour, $minute] = array_map('intval', explode(':', substr($normalized, 0, 5)));
        return ($hour * 60) + $minute;
    }
}
