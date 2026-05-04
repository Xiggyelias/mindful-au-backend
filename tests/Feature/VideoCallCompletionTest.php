<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VideoCallCompletionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ending_an_active_video_call_completes_the_matching_appointment(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-video@test.com', 'Student Video');
        $counselor = $this->createPortalUser('counselor', 'counselor-video@test.com', 'Counselor Video');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(3),
            'notes' => "Video appointment #{$appointment->id}",
        ]);

        $response = $this->actingAs($counselor)->postJson('/api/video-calls/end', [
            'appointment_id' => $appointment->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'session_id' => $session->id,
                'status' => 'completed',
                'appointment_id' => $appointment->id,
                'appointment_status' => 'completed',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Appointment Completed',
        ]);

        $completedSession = CounselingSession::query()->findOrFail($session->id);
        $this->assertSame('completed', $completedSession->status);
        $this->assertNotNull($completedSession->ended_at);
    }

    /** @test */
    public function ending_an_active_video_call_completes_the_appointment_when_session_notes_hold_no_reference_id(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-video-notes@test.com', 'Student Video Notes');
        $counselor = $this->createPortalUser('counselor', 'counselor-video-notes@test.com', 'Counselor Video Notes');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(3),
            'notes' => 'Follow-up discussed coping strategies.',
        ]);

        $response = $this->actingAs($counselor)->postJson('/api/video-calls/end', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(200)->assertJson([
            'appointment_id' => $appointment->id,
            'appointment_status' => 'completed',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function ending_a_call_without_a_started_video_session_does_not_complete_the_appointment(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-no-session@test.com', 'Student No Session');
        $counselor = $this->createPortalUser('counselor', 'counselor-no-session@test.com', 'Counselor No Session');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addMinutes(10),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $response = $this->actingAs($counselor)->postJson('/api/video-calls/end', [
            'appointment_id' => $appointment->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'appointment_id' => $appointment->id,
                'appointment_status' => 'confirmed',
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);

        $this->assertSame(
            0,
            Notification::query()
                ->where('user_id', $student->id)
                ->where('title', 'Appointment Completed')
                ->count()
        );
    }

    /** @test */
    public function ending_an_active_call_does_not_complete_an_appointment_when_session_notes_reference_a_different_appointment_id(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-video-mismatch@test.com', 'Student Mismatch');
        $counselor = $this->createPortalUser('counselor', 'counselor-video-mismatch@test.com', 'Counselor Mismatch');

        $firstAppointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(20),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $secondAppointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(3),
            'notes' => "Video appointment #{$firstAppointment->id}",
        ]);

        $response = $this->actingAs($counselor)->postJson('/api/video-calls/end', [
            'appointment_id' => $secondAppointment->id,
        ]);

        $response->assertStatus(200)->assertJson([
            'appointment_id' => $secondAppointment->id,
            'appointment_status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $secondAppointment->id,
            'status' => 'confirmed',
        ]);
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
