<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\PreSessionCheckIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreSessionCheckInTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_can_submit_sublime_pre_session_check_in(): void
    {
        $student = $this->createPortalUser('student', 'student1@test.com', 'Alex Rivera');
        $counselor = $this->createPortalUser('counselor', 'counselor1@test.com', 'Dr. Sarah Connor');

        $student->update(['needs_assessment' => true]);

        $appointment = Appointment::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addMinutes(15),
            'status' => 'scheduled',
            'duration_minutes' => 45,
            'notes' => 'Online audio',
        ]);

        $payload = [
            'mood' => 'down',
            'mood_score' => 2,
            'energy_level' => 2,
            'stress_level' => 4,
            'primary_concerns' => ['Academics & Deadlines', 'Sleep & Fatigue'],
            'session_goal' => 'coping_tools',
            'student_notes' => 'Struggling with exam panic and insomnia.',
            'appointment_id' => $appointment->id,
        ];

        $response = $this->actingAs($student)->postJson('/api/pre-session-check-in', $payload);

        $response->assertCreated();
        $response->assertJsonPath('check_in.mood', 'down');
        $response->assertJsonPath('check_in.mood_score', 2);
        $response->assertJsonPath('check_in.stress_level', 4);

        $this->assertDatabaseHas('pre_session_check_ins', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'appointment_id' => $appointment->id,
            'mood' => 'down',
        ]);

        // Verify needs_assessment is seamlessly cleared
        $this->assertFalse($student->fresh()->needs_assessment);

        // Verify counselor received friendly check-in notification
        $this->assertTrue(
            Notification::where('user_id', $counselor->id)->where('title', 'like', '%Pre-Session%')->exists()
        );
    }

    #[Test]
    public function counselor_can_retrieve_latest_check_in_and_clinical_summary(): void
    {
        $student = $this->createPortalUser('student', 'student2@test.com', 'Jordan Lee');
        $counselor = $this->createPortalUser('counselor', 'counselor2@test.com', 'Dr. Marcus Vance');

        $checkIn = PreSessionCheckIn::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'mood' => 'anxious',
            'mood_score' => 2,
            'energy_level' => 3,
            'stress_level' => 5,
            'primary_concerns' => ['Anxiety / Panic'],
            'session_goal' => 'grounding',
            'student_notes' => 'Heart racing before presentation.',
            'is_crisis' => false,
        ]);

        $response = $this->actingAs($counselor)->getJson('/api/pre-session-check-in/latest?student_id='.$student->id);

        $response->assertOk();
        $response->assertJsonPath('check_in.id', $checkIn->id);
        $summary = $response->json('clinical_summary');
        $this->assertStringContainsString('Mood reported as \'anxious\'', $summary);
        $this->assertStringContainsString('Stress: 5/5', $summary);
        $this->assertStringContainsString('Grounding & emotional de-escalation', $summary);
    }

    #[Test]
    public function counselor_can_request_pre_session_check_in(): void
    {
        $student = $this->createPortalUser('student', 'student3@test.com', 'Taylor Swift');
        $counselor = $this->createPortalUser('counselor', 'counselor3@test.com', 'Dr. Lisa Cuddy');

        $response = $this->actingAs($counselor)->postJson('/api/pre-session-check-in/request', [
            'student_id' => $student->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Pre-session check-in request sent to student.');

        $this->assertTrue(
            Notification::where('user_id', $student->id)->where('title', 'like', '%Quick Check-In%')->exists()
        );
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
