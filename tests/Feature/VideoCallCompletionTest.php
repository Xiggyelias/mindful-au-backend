<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoCallCompletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Hanging up mid-session must not finish the appointment. It used to, which dropped the
     * slot out of both call queues and left a dropped connection with no way back in.
     */
    #[Test]
    public function ending_a_call_inside_an_open_session_leaves_the_slot_callable(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-video@test.com', 'Student Video');
        $counselor = $this->createPortalUser('counselor', 'counselor-video@test.com', 'Counselor Video');

        // 5 minutes into a 60-minute session: the window is wide open.
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

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/end', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson([
                'session_id' => $session->id,
                'status' => 'active',
                'appointment_id' => $appointment->id,
                'appointment_status' => 'confirmed',
                'session_still_open' => true,
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);

        // The session row stays active so a re-call reuses it (and its notes).
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);

        // Nothing was "completed", so the student must not be told it was.
        $this->assertSame(
            0,
            Notification::query()
                ->where('user_id', $student->id)
                ->where('title', 'Appointment Completed')
                ->count()
        );
    }

    #[Test]
    public function the_call_can_be_started_again_after_being_ended_inside_the_session(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-recall@test.com', 'Student Recall');
        $counselor = $this->createPortalUser('counselor', 'counselor-recall@test.com', 'Counselor Recall');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $first = $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/end', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['session_still_open' => true]);

        // The whole point: dial again on the same slot.
        $second = $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['call_state' => 'CALLING']);

        // Re-calling continues the same counseling session rather than fragmenting notes
        // across a new row for every reconnect.
        $this->assertSame(
            $first->json('session_id'),
            $second->json('session_id')
        );
        $this->assertSame(
            1,
            CounselingSession::query()
                ->where('student_id', $student->id)
                ->where('counselor_id', $counselor->id)
                ->where('session_type', 'video')
                ->count()
        );
    }

    #[Test]
    public function ending_a_call_after_the_session_window_closed_completes_the_appointment(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-video-notes@test.com', 'Student Video Notes');
        $counselor = $this->createPortalUser('counselor', 'counselor-video-notes@test.com', 'Counselor Video Notes');

        // 30-minute session that started 90 minutes ago: window closed 45 minutes back.
        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(90),
            'duration_minutes' => 30,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        $session = CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(88),
            'notes' => 'Follow-up discussed coping strategies.',
        ]);

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/end', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson([
                'appointment_id' => $appointment->id,
                'appointment_status' => 'completed',
                'session_still_open' => false,
            ]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);

        $completedSession = CounselingSession::query()->findOrFail($session->id);
        $this->assertSame('completed', $completedSession->status);
        $this->assertNotNull($completedSession->ended_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Appointment Completed',
        ]);
    }

    #[Test]
    public function the_sweep_completes_an_appointment_whose_window_has_closed(): void
    {
        $student = $this->createPortalUser('student', 'student-sweep@test.com', 'Student Sweep');
        $counselor = $this->createPortalUser('counselor', 'counselor-sweep@test.com', 'Counselor Sweep');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(90),
            'duration_minutes' => 30,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        // Left active by the last hang-up — nobody ever "ended" it after the window closed.
        CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(88),
            'notes' => "Video appointment #{$appointment->id}",
        ]);

        $this->artisan('appointments:complete-past')->assertSuccessful();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function the_sweep_leaves_an_open_session_alone(): void
    {
        $student = $this->createPortalUser('student', 'student-sweep-open@test.com', 'Student Sweep Open');
        $counselor = $this->createPortalUser('counselor', 'counselor-sweep-open@test.com', 'Counselor Sweep Open');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        CounselingSession::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'video',
            'status' => 'active',
            'started_at' => now()->subMinutes(3),
            'notes' => "Video appointment #{$appointment->id}",
        ]);

        $this->artisan('appointments:complete-past')->assertSuccessful();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
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

    #[Test]
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
