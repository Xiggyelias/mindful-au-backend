<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnonymousOnlineAppointmentAudioEnforcementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_cannot_request_video_call_type_for_anonymous_online_booking(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-anon-audio@test.com', 'Counselor Anon Audio');
        $student = $this->createPortalUser('student', 'student-anon-audio@test.com', 'Student Anon Audio');

        $this->actingAs($student)->postJson('/api/appointments', [
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay()->setHour(15)->setMinute(0)->toIso8601String(),
            'duration_minutes' => 60,
            'notes' => 'Online',
            'is_anonymous' => true,
            'call_type' => 'video',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['call_type']);
    }

    #[Test]
    public function appointment_model_coerces_call_type_to_audio_for_anonymous_non_physical_rows(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-model-anon@test.com', 'Counselor Model');
        $student = $this->createPortalUser('student', 'student-model-anon@test.com', 'Student Model');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Online',
            'is_anonymous' => true,
            'anonymous_id' => 'User_4242',
            'call_type' => 'video',
        ]);

        $this->assertSame('audio', $appointment->fresh()->call_type);
    }

    #[Test]
    public function appointment_model_does_not_coerce_audio_for_anonymous_physical_bookings(): void
    {
        $counselor = $this->createPortalUser('counselor', 'counselor-phys-anon@test.com', 'Counselor Phys');
        $student = $this->createPortalUser('student', 'student-phys-anon@test.com', 'Student Phys');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => 'scheduled',
            'notes' => 'Physical — front desk',
            'is_anonymous' => true,
            'anonymous_id' => 'User_5353',
            'call_type' => 'video',
        ]);

        $this->assertSame('video', $appointment->fresh()->call_type);
    }

    private function createPortalUser(string $role, string $email, string $fullName): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('SecretPass123!'),
        ]);

        $user->profile()->create([
            'full_name' => $fullName,
            'id_number' => null,
            'anonymous_mode' => false,
            'peer_available' => true,
        ]);

        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user;
    }
}
