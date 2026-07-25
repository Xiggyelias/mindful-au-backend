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
 * The incoming-call event must reach the *recipient's* device. It used to be emitted only
 * by the caller's browser, which cannot address an anonymous student (the appointments API
 * masks student_id to 0 for counselors) — so counselor→student calls never rang.
 */
class CallSignalingTest extends TestCase
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
    public function counselor_calling_a_student_rings_the_student_and_not_the_caller(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['call_state' => 'CALLING']);

        $this->assertSignalledTo($student->id, CallSignalBroadcaster::STATE_RINGING);
        $this->assertNotSignalledTo($counselor->id);
    }

    #[Test]
    public function an_anonymous_appointment_still_rings_the_student(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();
        $appointment->update(['is_anonymous' => true]);

        // The regression this guards: the counselor's client sees student_id = 0 for an
        // anonymous booking, so a browser-emitted ring is impossible here. Only the server
        // knows who to ring.
        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->assertSignalledTo($student->id, CallSignalBroadcaster::STATE_RINGING);
        $this->assertNotSignalledTo($counselor->id);
    }

    #[Test]
    public function student_calling_a_counselor_rings_the_counselor_and_not_the_caller(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->assertSignalledTo($counselor->id, CallSignalBroadcaster::STATE_RINGING);
        $this->assertNotSignalledTo($student->id);
    }

    #[Test]
    public function accepting_signals_the_caller_that_both_sides_are_connected(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'accepted'])
            ->assertStatus(200)
            ->assertJson(['state' => CallSignalBroadcaster::STATE_CONNECTED]);

        $this->assertSignalledTo($counselor->id, CallSignalBroadcaster::STATE_CONNECTED);
    }

    #[Test]
    public function declining_signals_the_caller_that_the_call_ended(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'declined'])
            ->assertStatus(200)
            ->assertJson(['state' => CallSignalBroadcaster::STATE_ENDED]);

        $this->assertSignalledTo($counselor->id, CallSignalBroadcaster::STATE_ENDED);
    }

    #[Test]
    public function cancelling_signals_the_recipient_so_their_ring_stops(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/cancel', ['appointment_id' => $appointment->id])
            ->assertStatus(200)
            ->assertJson(['cancelled' => true]);

        $this->assertSignalledTo($student->id, CallSignalBroadcaster::STATE_ENDED);
    }

    #[Test]
    public function the_callee_can_authorize_the_call_they_just_accepted(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $call = CounselingCall::query()->where('appointment_id', $appointment->id)->firstOrFail();

        $this->actingAs($student)
            ->patchJson("/api/student/incoming-calls/{$call->id}", ['status' => 'accepted'])
            ->assertStatus(200);

        // Accepting navigates the student to the call page, which authorizes to join. The
        // answered call is their own call — not a conflicting incoming one.
        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        $this->assertSame(1, CounselingCall::query()->where('appointment_id', $appointment->id)->count());
        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'status' => CounselingCall::STATUS_ACCEPTED,
        ]);
    }

    #[Test]
    public function an_unanswered_ring_still_blocks_the_callee_from_placing_their_own_call(): void
    {
        [$student, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        // Still PENDING — the student must answer or decline rather than dial back.
        $this->actingAs($student)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(409)
            ->assertJson(['reason' => 'incoming_call']);
    }

    #[Test]
    public function a_missing_realtime_config_does_not_break_the_call(): void
    {
        config(['services.supabase.url' => null, 'services.supabase.key' => null]);

        [, $counselor, $appointment] = $this->makeConfirmedAppointment();

        $this->actingAs($counselor)
            ->postJson('/api/video-calls/authorize', ['appointment_id' => $appointment->id])
            ->assertStatus(200);

        Http::assertNothingSent();
        $this->assertDatabaseHas('calls', [
            'appointment_id' => $appointment->id,
            'status' => CounselingCall::STATUS_PENDING,
        ]);
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

    private function assertNotSignalledTo(int $userId): void
    {
        $channel = CallSignalBroadcaster::channelFor($userId);

        Http::assertNotSent(function (Request $request) use ($channel) {
            $message = $request->data()['messages'][0] ?? null;

            return $message && ($message['topic'] ?? null) === $channel;
        });
    }

    /** @return array{0: User, 1: User, 2: Appointment} */
    private function makeConfirmedAppointment(): array
    {
        $student = $this->createPortalUser('student', 'student-signal@test.com', 'Signal Student');
        $counselor = $this->createPortalUser('counselor', 'counselor-signal@test.com', 'Signal Counselor');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Online',
        ]);

        return [$student, $counselor, $appointment];
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
