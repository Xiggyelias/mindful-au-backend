<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselingCall;
use App\Models\Notification;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoCallRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_one_call_session_is_created_when_both_sides_try_to_call_at_once(): void
    {
        $this->disableTwoFactor();
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $first = $this->actingAs($student)->postJson('/api/video-calls/authorize', [
            'appointment_id' => $appointment->id,
        ]);
        $first->assertStatus(200);

        // Counselor presses Call at nearly the same moment, before answering the student's ring.
        $second = $this->actingAs($counselor)->postJson('/api/video-calls/authorize', [
            'appointment_id' => $appointment->id,
        ]);
        $second->assertStatus(409)->assertJson(['reason' => 'incoming_call']);

        $this->assertSame(1, CounselingCall::query()->where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('calls', [
            'appointment_id' => $appointment->id,
            'caller_role' => CounselingCall::CALLER_STUDENT,
            'status' => CounselingCall::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function repeated_button_presses_from_the_same_caller_do_not_create_duplicate_sessions(): void
    {
        $this->disableTwoFactor();
        [$student, , $appointment] = $this->makeConfirmedAppointment();

        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($student)->postJson('/api/video-calls/authorize', [
                'appointment_id' => $appointment->id,
            ]);
            $response->assertStatus(200);
        }

        $this->assertSame(1, CounselingCall::query()->where('appointment_id', $appointment->id)->count());
    }

    #[Test]
    public function calling_a_recipient_who_is_busy_on_another_call_returns_busy(): void
    {
        $this->disableTwoFactor();
        $counselor = $this->createPortalUser('counselor', 'counselor-busy@test.com', 'Busy Counselor');
        $studentA = $this->createPortalUser('student', 'student-a-busy@test.com', 'Student A');
        $studentB = $this->createPortalUser('student', 'student-b-busy@test.com', 'Student B');

        $appointmentA = $this->makeAppointment($studentA, $counselor);
        $appointmentB = $this->makeAppointment($studentB, $counselor);

        $this->actingAs($studentA)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointmentA->id])
            ->assertStatus(200);

        // Counselor is already ringing with Student A — Student B trying to reach the same
        // counselor (a different pair entirely) must be told the counselor is busy, not get
        // silently merged into Student A's call.
        $this->actingAs($studentB)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointmentB->id])
            ->assertStatus(409)
            ->assertJson(['reason' => 'busy']);

        $this->assertSame(0, CounselingCall::query()->where('appointment_id', $appointmentB->id)->count());
    }

    #[Test]
    public function cancelling_an_unanswered_call_is_idempotent_and_notifies_the_callee(): void
    {
        $this->disableTwoFactor();
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->actingAs($student)
            ->postJson('/api/video-calls/cancel', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['cancelled' => true]);

        $this->assertDatabaseHas('calls', [
            'appointment_id' => $appointment->id,
            'status' => CounselingCall::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $counselor->id,
            'title' => 'Call cancelled',
        ]);

        // Cancelling again (double-click, or a stale UI) must not error.
        $this->actingAs($student)
            ->postJson('/api/video-calls/cancel', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['cancelled' => false]);
    }

    #[Test]
    public function accepting_an_expired_call_is_rejected_and_marks_it_missed(): void
    {
        $this->disableTwoFactor();
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $call = CounselingCall::query()->create([
            'appointment_id' => $appointment->id,
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => CounselingCall::STATUS_PENDING,
            'call_type' => 'video',
            'caller_role' => CounselingCall::CALLER_COUNSELOR,
            'expires_at' => now()->subSeconds(5),
        ]);

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'accepted'])
            ->assertStatus(410);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => CounselingCall::STATUS_MISSED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'title' => 'Missed call',
        ]);
    }

    #[Test]
    public function declining_a_call_notifies_the_caller(): void
    {
        $this->disableTwoFactor();
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'declined'])
            ->assertStatus(200);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => CounselingCall::STATUS_DECLINED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $counselor->id,
            'title' => 'Call declined',
        ]);
    }

    #[Test]
    public function accepting_a_call_sets_connected_state(): void
    {
        $this->disableTwoFactor();
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'accepted'])
            ->assertStatus(200)
            ->assertJson(['status' => 'accepted']);

        $connected = CounselingCall::query()->findOrFail($call->id);
        $this->assertNotNull($connected->connected_at);

        // Now that the pair is CONNECTED, a third party trying to reach either of them is busy.
        $outsider = $this->createPortalUser('student', 'outsider@test.com', 'Outsider');
        $outsiderAppointment = $this->makeAppointment($outsider, $counselor);

        $this->actingAs($outsider)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $outsiderAppointment->id])
            ->assertStatus(409)
            ->assertJson(['reason' => 'busy']);
    }

    /** @return array{0: User, 1: User, 2: Appointment} */
    private function makeConfirmedAppointment(): array
    {
        $student = $this->createPortalUser('student', 'student-race@test.com', 'Race Student');
        $counselor = $this->createPortalUser('counselor', 'counselor-race@test.com', 'Race Counselor');
        $appointment = $this->makeAppointment($student, $counselor);

        return [$student, $counselor, $appointment];
    }

    private function makeAppointment(User $student, User $counselor): Appointment
    {
        return Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);
    }

    private function disableTwoFactor(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
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
