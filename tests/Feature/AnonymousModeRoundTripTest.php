<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselingCall;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\CallSignalBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Anonymity has to be reversible.
 *
 * Turning it on forces an online appointment to audio-only; every route out of anonymous mode
 * has to put that back, and the `notes` / `call_type` pair that encodes the format must never
 * disagree with the `is_anonymous` flag. When it did, a student who had switched to Identified
 * Mode still produced audio-only calls and still showed up to the counselor as "Anonymous
 * User" over a queue entry advertising "Video / online".
 */
class AnonymousModeRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableTwoFactor();
        config([
            'services.supabase.url' => 'https://realtime.test',
            'services.supabase.key' => 'test-anon-key',
        ]);
        Http::fake([
            'realtime.test/*' => Http::response(['message' => 'ok'], 202),
        ]);
    }

    #[Test]
    public function turning_anonymous_mode_on_forces_an_online_appointment_to_audio(): void
    {
        [$student, , $appointment] = $this->makeOnlineVideoAppointment();

        $this->actingAs($student)
            ->putJson('/api/profile', ['anonymous_mode' => true])
            ->assertStatus(200);

        $appointment->refresh();
        $this->assertTrue($appointment->is_anonymous);
        $this->assertSame('Online audio', $appointment->notes);
        $this->assertSame('audio', $appointment->call_type);
    }

    #[Test]
    public function turning_anonymous_mode_back_off_restores_the_video_format(): void
    {
        [$student, , $appointment] = $this->makeOnlineVideoAppointment();

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => true])->assertStatus(200);
        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => false])->assertStatus(200);

        $appointment->refresh();
        $this->assertFalse($appointment->is_anonymous);
        $this->assertNull($appointment->anonymous_id);
        // The regression: only `is_anonymous` used to be cleared, so the appointment stayed
        // 'Online audio' forever and every call it produced was audio-only.
        $this->assertSame('Online', $appointment->notes);
        $this->assertSame('video', $appointment->call_type);
    }

    #[Test]
    public function turning_anonymous_mode_off_leaves_a_deliberate_audio_booking_alone(): void
    {
        [$student, $counselor] = $this->makePortalPair('deliberate-audio');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online audio',
            'call_type' => 'audio',
        ]);

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => false])->assertStatus(200);

        $appointment->refresh();
        $this->assertSame('Online audio', $appointment->notes);
        $this->assertSame('audio', $appointment->call_type);
    }

    #[Test]
    public function the_toggle_reaches_a_session_whose_call_window_is_still_open(): void
    {
        [$student, $counselor] = $this->makePortalPair('open-window');

        // Started 40 minutes ago, 60-minute session: the call is joinable right now, but it
        // fell outside the old 15-minute cutoff and so was skipped by the sync entirely.
        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(40),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
            'call_type' => 'video',
        ]);

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => true])->assertStatus(200);

        $appointment->refresh();
        $this->assertTrue($appointment->is_anonymous);
        $this->assertSame('Online audio', $appointment->notes);
    }

    #[Test]
    public function the_toggle_does_not_touch_a_session_whose_call_window_has_closed(): void
    {
        [$student, $counselor] = $this->makePortalPair('closed-window');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(110),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
            'call_type' => 'video',
        ]);

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => true])->assertStatus(200);

        $appointment->refresh();
        $this->assertFalse($appointment->is_anonymous);
        $this->assertSame('Online', $appointment->notes);
    }

    #[Test]
    public function the_toggle_keeps_in_person_notes_in_both_directions(): void
    {
        [$student, $counselor] = $this->makePortalPair('physical-notes');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Physical — Room 12',
            'call_type' => 'video',
        ]);

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => true])->assertStatus(200);
        $this->assertSame('Physical — Room 12', $appointment->fresh()->notes);

        $this->actingAs($student)->putJson('/api/profile', ['anonymous_mode' => false])->assertStatus(200);
        $this->assertSame('Physical — Room 12', $appointment->fresh()->notes);
    }

    #[Test]
    public function revealing_identity_restores_the_video_format(): void
    {
        [$student, , $appointment] = $this->makeOnlineVideoAppointment();
        $appointment->update(['is_anonymous' => true, 'anonymous_id' => 'User_0001']);

        $this->actingAs($student)
            ->postJson("/api/appointments/{$appointment->id}/reveal-identity")
            ->assertStatus(200);

        $appointment->refresh();
        $this->assertFalse($appointment->is_anonymous);
        $this->assertSame('Online', $appointment->notes);
        $this->assertSame('video', $appointment->call_type);
    }

    #[Test]
    public function marking_an_appointment_anonymous_normalises_its_notes_too(): void
    {
        [, , $appointment] = $this->makeOnlineVideoAppointment();

        // Only `call_type` used to be coerced, leaving `notes = 'Online'`. Every surface that
        // renders the format from `notes` then advertised a video session for an audio call.
        $appointment->update(['is_anonymous' => true]);

        $appointment->refresh();
        $this->assertSame('Online audio', $appointment->notes);
        $this->assertSame('audio', $appointment->call_type);
    }

    #[Test]
    public function authorize_reports_the_call_type_the_server_resolved(): void
    {
        [$student, , $appointment] = $this->makeOnlineVideoAppointment();
        $appointment->update([
            'scheduled_at' => now()->subMinutes(5),
            'is_anonymous' => true,
        ]);

        // The client asked for video; anonymity forces audio and the response has to say so,
        // otherwise WebRTC negotiates video against a call row stored as audio.
        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', [
                'appointment_id' => $appointment->id,
                'call_type' => 'audio',
            ])
            ->assertStatus(200)
            ->assertJson(['call_type' => 'audio', 'is_anonymous' => true]);
    }

    #[Test]
    public function a_ring_that_runs_out_is_reported_as_missed_not_declined(): void
    {
        [$student, $counselor, $appointment] = $this->makeOnlineVideoAppointment();
        $appointment->update(['scheduled_at' => now()->subMinutes(5)]);

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'missed'])
            ->assertStatus(200)
            ->assertJson(['state' => CallSignalBroadcaster::STATE_ENDED]);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => CounselingCall::STATUS_MISSED,
        ]);
        $this->assertSignalledTo($counselor->id, CallSignalBroadcaster::STATE_ENDED);
    }

    #[Test]
    public function a_student_always_sees_the_real_counselor_name_on_an_incoming_ring(): void
    {
        [$student, $counselor, $appointment] = $this->makeOnlineVideoAppointment();
        $appointment->update(['scheduled_at' => now()->subMinutes(5), 'is_anonymous' => true]);

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        // Anonymity hides the student from the counselor, never the counselor from the student.
        $this->actingAs($student)
            ->getJson('/api/student/incoming-calls')
            ->assertStatus(200)
            ->assertJsonPath('data.0.counselor_name', 'Round Trip Counselor');
    }

    #[Test]
    public function a_counselor_never_sees_an_anonymous_students_name_on_an_incoming_ring(): void
    {
        [$student, $counselor, $appointment] = $this->makeOnlineVideoAppointment();
        $appointment->update(['scheduled_at' => now()->subMinutes(5), 'is_anonymous' => true]);

        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->actingAs($counselor)
            ->getJson('/api/counselor/incoming-calls')
            ->assertStatus(200)
            ->assertJsonPath('data.0.student_name', 'Anonymous User')
            ->assertJsonPath('data.0.student_id', 0)
            ->assertJsonPath('data.0.call_type', 'audio');
    }

    private function assertSignalledTo(int $userId, string $state): void
    {
        $channel = CallSignalBroadcaster::channelFor($userId);

        Http::assertSent(function (Request $request) use ($channel, $state) {
            $message = $request->data()['messages'][0] ?? null;

            return $message
                && ($message['topic'] ?? null) === $channel
                && ($message['event'] ?? null) === CallSignalBroadcaster::EVENT
                && (($message['payload']['state'] ?? null) === $state);
        });
    }

    /** @return array{0: User, 1: User, 2: Appointment} */
    private function makeOnlineVideoAppointment(): array
    {
        [$student, $counselor] = $this->makePortalPair('round-trip');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
            'call_type' => 'video',
        ]);

        return [$student, $counselor, $appointment];
    }

    /** @return array{0: User, 1: User} */
    private function makePortalPair(string $slug): array
    {
        return [
            $this->createPortalUser('student', "student-{$slug}@test.com", 'Round Trip Student'),
            $this->createPortalUser('counselor', "counselor-{$slug}@test.com", 'Round Trip Counselor'),
        ];
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
