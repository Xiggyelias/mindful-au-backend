<?php

namespace Tests\Feature;

use App\Models\CounselorSlot;
use App\Models\EmergencyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CounselorSlotManagementTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertNotEmpty($slots);
        $this->assertFalse(
            $slots->contains(fn (array $slot) => Carbon::parse($slot['start_time'])->format('H:i') === '13:00'),
            'Lunch break slots must not be generated.'
        );

        $slot = $slots->first(fn (array $slot) => $slot['status'] === 'available');
        $this->assertNotNull($slot);

        $booking = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'counselor_slot_id' => $slot['id'],
            'scheduled_at' => $slot['start_time'],
            'duration_minutes' => 30,
            'notes' => 'Online',
        ]);

        $booking->assertStatus(201)
            ->assertJsonPath('counselor_slot_id', $slot['id']);

        $this->assertSame('booked', CounselorSlot::query()->find($slot['id'])->status);

        $secondBooking = $this->actingAs($otherStudent)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'counselor_slot_id' => $slot['id'],
            'scheduled_at' => $slot['start_time'],
            'duration_minutes' => 30,
            'notes' => 'Online',
        ]);

        $secondBooking->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
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
}
