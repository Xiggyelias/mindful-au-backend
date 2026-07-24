<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\EmergencyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmergencyAppointmentIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEDULE_TIMEZONE = 'Africa/Harare';

    #[Test]
    public function emergency_booking_is_always_identified_even_when_anonymous_is_requested(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-emg-id@test.com', 'Counselor Emergency');
        $student = $this->createPortalUser('student', 'student-emg-id@test.com', 'Student Emergency', anonymousMode: true);

        $emergency = EmergencyRequest::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'assigned_to' => $counselor->id,
            'requested_at' => now(),
            'is_after_hours' => true,
            'priority' => 1,
            'status' => 'assigned',
            'reason' => 'Test emergency.',
        ]);

        // The student asks for an anonymous video call, but because this is an emergency the
        // counselor must be able to identify them — the appointment must come back identified.
        $response = $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => Carbon::now(self::SCHEDULE_TIMEZONE)
                ->addHour()
                ->utc()
                ->toIso8601String(),
            'duration_minutes' => 60,
            'notes' => 'Online',
            'is_anonymous' => true,
            'call_type' => 'video',
            'emergency_request_id' => $emergency->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('is_anonymous', false)
            ->assertJsonPath('identity_visible_to_viewer', true)
            ->assertJsonPath('is_emergency', true);

        $this->assertDatabaseHas('appointments', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'is_anonymous' => false,
            'anonymous_id' => null,
            'is_emergency' => true,
        ]);
    }

    #[Test]
    public function emergency_booking_is_allowed_even_when_it_overlaps_an_existing_appointment(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-emg-overlap@test.com', 'Counselor Overlap');
        $student = $this->createPortalUser('student', 'student-emg-overlap@test.com', 'Student Overlap');

        $conflictStart = Carbon::now(self::SCHEDULE_TIMEZONE)->addHour()->startOfHour();

        // A pre-existing scheduled appointment the emergency time will overlap.
        Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => $conflictStart->copy()->utc(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Online',
            'is_anonymous' => false,
        ]);

        $emergency = EmergencyRequest::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'assigned_to' => $counselor->id,
            'requested_at' => now(),
            'is_after_hours' => true,
            'priority' => 1,
            'status' => 'assigned',
            'reason' => 'Overlapping emergency.',
        ]);

        // Emergency time deliberately overlaps the appointment above (starts 15 min into it).
        $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => $conflictStart->copy()->addMinutes(15)->utc()->toIso8601String(),
            'duration_minutes' => 60,
            'notes' => 'Online',
            'call_type' => 'video',
            'emergency_request_id' => $emergency->id,
        ])->assertCreated();

        // Both appointments now exist for the same counselor at overlapping times.
        $this->assertSame(2, Appointment::query()->where('counselor_id', $counselor->id)->count());
    }

    private function createPortalUser(string $role, string $email, string $fullName, bool $anonymousMode = false): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('SecretPass123!'),
        ]);

        $user->profile()->create([
            'full_name' => $fullName,
            'id_number' => null,
            'anonymous_mode' => $anonymousMode,
            'peer_available' => true,
        ]);

        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }
}
