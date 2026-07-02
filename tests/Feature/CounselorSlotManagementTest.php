<?php

namespace Tests\Feature;

use App\Models\CounselorSchedule;
use App\Models\CounselorSlot;
use App\Models\EmergencyRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CounselorSlotManagementTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEDULE_TIMEZONE = 'Africa/Harare';

    public function test_student_books_generated_slot_and_double_booking_is_blocked(): void
    {
        $student = $this->createUserWithRole('student');
        $otherStudent = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $date = now()->next(Carbon::MONDAY)->toDateString();

        $slotsResponse = $this->actingAs($student)->getJson(
            "/api/counselor-slots?counselor_id={$counselor->id}&from={$date}&to={$date}"
        );

        $slotsResponse->assertOk();
        $slots = collect($slotsResponse->json('data'));
        // Default schedule 08:00-16:00 with a 13:00-14:00 lunch break yields
        // seven non-overlapping 60-minute slots: 08,09,10,11,12,14,15.
        $this->assertCount(7, $slots);
        $this->assertFalse(
            $slots->contains(fn (array $slot) => $this->localSlotTime($slot['start_time']) >= '16:00'),
            'Slots must not start at or after the 16:00 school close.'
        );
        $this->assertFalse(
            $slots->contains(fn (array $slot) => $this->localSlotTime($slot['start_time']) === '13:00'),
            'Lunch break slots must not be generated.'
        );

        $slot = $slots->first(fn (array $slot) => $slot['status'] === 'available');
        $this->assertNotNull($slot);
        $slotDuration = (int) Carbon::parse($slot['start_time'])->diffInMinutes(Carbon::parse($slot['end_time']));
        $this->assertSame(60, $slotDuration);

        $booking = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'counselor_slot_id' => $slot['id'],
            'scheduled_at' => $slot['start_time'],
            'duration_minutes' => $slotDuration,
            'notes' => 'Online',
        ]);

        $booking->assertStatus(201)
            ->assertJsonPath('counselor_slot_id', $slot['id']);

        $this->assertSame('booked', CounselorSlot::query()->find($slot['id'])->status);

        $secondBooking = $this->actingAs($otherStudent)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'counselor_slot_id' => $slot['id'],
            'scheduled_at' => $slot['start_time'],
            'duration_minutes' => $slotDuration,
            'notes' => 'Online',
        ]);

        $secondBooking->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    public function test_existing_late_schedule_is_capped_to_school_close(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $date = now()->next(Carbon::MONDAY)->toDateString();

        CounselorSchedule::query()->create([
            'counselor_id' => $counselor->id,
            'day_of_week' => Carbon::MONDAY,
            'is_working_day' => true,
            'start_time' => '10:00:00',
            'end_time' => '18:00:00',
            'break_start' => '13:00:00',
            'break_end' => '14:00:00',
            'slot_duration_minutes' => 30,
        ]);

        $slotsResponse = $this->actingAs($student)->getJson(
            "/api/counselor-slots?counselor_id={$counselor->id}&from={$date}&to={$date}"
        );

        $slotsResponse->assertOk();
        $slots = collect($slotsResponse->json('data'));

        // Slots never overlap: the generator steps by at least the 60-minute
        // appointment duration even when the configured interval is 30
        // minutes. With the 18:00 end capped to the 16:00 school close and a
        // 13:00-14:00 lunch break, that yields 10,11,12,14,15 — five slots.
        $this->assertCount(5, $slots);
        $this->assertTrue(
            $slots->contains(
                fn (array $slot) => $this->localSlotTime($slot['start_time']) === '10:00'
                    && $this->localSlotTime($slot['end_time']) === '11:00'
            ),
            'Slots should start at the configured 10:00 schedule start.'
        );
        $this->assertTrue(
            $slots->every(
                fn (array $slot) => (int) Carbon::parse($slot['start_time'])->diffInMinutes(Carbon::parse($slot['end_time'])) === 60
            ),
            'Normal bookable slots should be 60 minutes long.'
        );
        $this->assertFalse(
            $slots->contains(function (array $slot) {
                $slotStart = Carbon::parse($slot['start_time'])->timezone(self::SCHEDULE_TIMEZONE);
                $slotEnd = Carbon::parse($slot['end_time'])->timezone(self::SCHEDULE_TIMEZONE);
                $breakStart = $slotStart->copy()->setTime(13, 0);
                $breakEnd = $slotStart->copy()->setTime(14, 0);

                return $slotStart->lt($breakEnd) && $slotEnd->gt($breakStart);
            }),
            'Generated slots must not overlap lunch.'
        );
        $this->assertFalse(
            $slots->contains(fn (array $slot) => $this->localSlotTime($slot['end_time']) > '16:00'),
            'Slots must end by the 16:00 school close.'
        );
        $this->assertSame('16:00:00', (string) CounselorSchedule::query()->where('counselor_id', $counselor->id)->where('day_of_week', Carbon::MONDAY)->value('end_time'));
    }

    public function test_after_hours_booking_is_queued_as_emergency_request(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $afterHours = now()->next(Carbon::MONDAY)->setTime(18, 0);

        $response = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $afterHours->toIso8601String(),
            'duration_minutes' => 30,
            'notes' => 'Need urgent after-hours support',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('emergency', true);

        $this->assertDatabaseHas('emergency_requests', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'queued',
        ]);
        $this->assertSame(1, EmergencyRequest::query()->count());

        $notification = Notification::query()
            ->where('user_id', $counselor->id)
            ->where('title', 'After-hours Emergency Request')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame('/counselor/alerts', $notification->meta['path'] ?? null);
    }

    public function test_assigned_emergency_request_creates_bookable_priority_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00'));

        try {
            $student = $this->createUserWithRole('student');
            $counselor = $this->createUserWithRole('counselor');
            $emergencyRequest = EmergencyRequest::query()->create([
                'student_id' => $student->id,
                'counselor_id' => null,
                'requested_at' => now()->subMinutes(20),
                'is_after_hours' => true,
                'priority' => 1,
                'status' => 'queued',
                'reason' => 'Urgent support needed',
            ]);

            $acceptResponse = $this->actingAs($counselor)->patchJson("/api/emergency-requests/{$emergencyRequest->id}", [
                'status' => 'assigned',
            ]);

            $acceptResponse->assertOk()
                ->assertJsonPath('status', 'assigned')
                ->assertJsonPath('assigned_to', $counselor->id);

            $slotId = (int) $acceptResponse->json('counselor_slot_id');
            $this->assertGreaterThan(0, $slotId);

            $slot = CounselorSlot::query()->findOrFail($slotId);
            $this->assertNull($slot->counselor_schedule_id);
            $this->assertTrue(Carbon::parse($slot->start_time)->greaterThan(now()));

            $slotsResponse = $this->actingAs($student)->getJson(
                "/api/counselor-slots?counselor_id={$counselor->id}&from=2026-06-08&to=2026-06-08&generate=1"
            );

            $slotsResponse->assertOk();
            $this->assertTrue(
                collect($slotsResponse->json('data'))->contains(fn (array $row) => (int) $row['id'] === $slotId),
                'Accepted emergency slots should remain visible after normal slot generation.'
            );

            $bookingResponse = $this->actingAs($student)->postJson('/api/appointments', [
                'counselor_id' => $counselor->id,
                'counselor_slot_id' => $slotId,
                'scheduled_at' => $slot->start_time->toIso8601String(),
                'duration_minutes' => max(30, $slot->start_time->diffInMinutes($slot->end_time)),
                'notes' => 'Online emergency support',
            ]);

            $bookingResponse->assertCreated()
                ->assertJsonPath('counselor_slot_id', $slotId);

            $this->assertDatabaseHas('appointments', [
                'student_id' => $student->id,
                'counselor_id' => $counselor->id,
                'counselor_slot_id' => $slotId,
                'status' => 'scheduled',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_prepare_slot_for_already_assigned_emergency_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-23 22:05:00', 'UTC'));

        try {
            $student = $this->createUserWithRole('student');
            $counselor = $this->createUserWithRole('counselor');
            $requestedAt = Carbon::parse('2026-06-24 00:07:48', self::SCHEDULE_TIMEZONE)->utc();
            $emergencyRequest = EmergencyRequest::query()->create([
                'student_id' => $student->id,
                'assigned_to' => $counselor->id,
                'requested_at' => $requestedAt,
                'is_after_hours' => true,
                'priority' => 1,
                'status' => 'assigned',
                'reason' => 'Need urgent support',
            ]);

            $prepareResponse = $this->actingAs($counselor)->patchJson("/api/emergency-requests/{$emergencyRequest->id}", [
                'prepare_slot' => true,
            ]);

            $prepareResponse->assertOk()
                ->assertJsonPath('status', 'assigned')
                ->assertJsonPath('assigned_to', $counselor->id);

            $slotId = (int) $prepareResponse->json('counselor_slot_id');
            $this->assertGreaterThan(0, $slotId);

            $slot = CounselorSlot::query()->findOrFail($slotId);
            $localStart = Carbon::parse($slot->start_time)->timezone(self::SCHEDULE_TIMEZONE);
            $this->assertSame('2026-06-24', $slot->slot_date->toDateString());
            $this->assertSame('2026-06-24', $localStart->toDateString());
            $this->assertSame(0, $localStart->minute % 15);
            $this->assertNull($slot->counselor_schedule_id);
            $this->assertSame('available', $slot->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_counselor_can_get_and_update_schedules_and_generate_slots(): void
    {
        $counselor = $this->createUserWithRole('counselor');

        $responseGet = $this->actingAs($counselor)->getJson('/api/counselor-schedules');
        $responseGet->assertOk()
            ->assertJsonStructure(['data']);

        $schedules = $responseGet->json('data');
        $this->assertCount(7, $schedules);

        $responsePut = $this->actingAs($counselor)->putJson('/api/counselor-schedules', [
            'schedules' => collect($schedules)->map(fn ($s) => [
                'day_of_week' => $s['day_of_week'],
                'is_working_day' => $s['is_working_day'],
                'start_time' => '09:00',
                'end_time' => '15:00',
                'slot_duration_minutes' => 60,
            ])->all(),
        ]);
        $responsePut->assertOk();

        $responseGenerate = $this->actingAs($counselor)->postJson('/api/counselor-slots/generate', [
            'weeks' => 1,
        ]);
        $responseGenerate->assertStatus(201);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }

    private function localSlotTime(string $value): string
    {
        return Carbon::parse($value)->timezone(self::SCHEDULE_TIMEZONE)->format('H:i');
    }
}
