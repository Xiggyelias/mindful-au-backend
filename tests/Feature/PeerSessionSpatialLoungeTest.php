<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeerSessionSpatialLoungeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $peerCounselor;

    private User $otherUser;

    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->student = User::factory()->create(['email' => 'spatial-student@test.com']);
        $this->peerCounselor = User::factory()->create(['email' => 'spatial-peer@test.com']);
        $this->otherUser = User::factory()->create(['email' => 'spatial-other@test.com']);

        UserRole::query()->create(['user_id' => $this->student->id, 'role' => 'student', 'approved' => true]);
        UserRole::query()->create(['user_id' => $this->peerCounselor->id, 'role' => 'peer_counselor', 'approved' => true]);
        UserRole::query()->create(['user_id' => $this->otherUser->id, 'role' => 'student', 'approved' => true]);

        $this->session = CounselingSession::query()->create([
            'student_id' => $this->student->id,
            'peer_counselor_id' => $this->peerCounselor->id,
            'assigned_role' => 'peer_counselor',
            'session_type' => 'chat',
            'status' => 'in_progress',
            'is_anonymous' => false,
        ]);
    }

    #[Test]
    public function assigned_peer_counselor_can_request_spatial_lounge(): void
    {
        $response = $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'active' => true,
            'session_id' => $this->session->id,
            'room' => "spatial-session-{$this->session->id}",
        ]);

        $this->assertTrue(Cache::has("spatial_lounge_session_{$this->session->id}"));

        // Verifies chat invite message is posted to session
        $this->assertDatabaseHas('messages', [
            'session_id' => $this->session->id,
            'sender_id' => $this->peerCounselor->id,
            'sender_role' => 'peer_counselor',
        ]);

        $message = Message::query()->where('session_id', $this->session->id)->first();
        $this->assertStringContainsString('Heart-to-Heart Spatial Lounge', $message->content);
    }

    #[Test]
    public function unassigned_user_cannot_request_spatial_lounge(): void
    {
        $response = $this->actingAs($this->otherUser)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function student_cannot_request_spatial_lounge(): void
    {
        $response = $this->actingAs($this->student)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function assigned_pair_can_view_lounge_status(): void
    {
        // Initially inactive
        $response = $this->actingAs($this->student)->getJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/status"
        );

        $response->assertStatus(200);
        $response->assertJson([
            'session_id' => $this->session->id,
            'active' => false,
            'is_peer_assigned' => true,
            'can_request' => false, // student cannot request
        ]);

        // Peer counselor can check status and see can_request = true
        $peerCheck = $this->actingAs($this->peerCounselor)->getJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/status"
        );

        $peerCheck->assertStatus(200);
        $peerCheck->assertJson([
            'active' => false,
            'can_request' => true,
        ]);
    }

    #[Test]
    public function assigned_peer_and_student_can_mint_session_token_with_paired_partners(): void
    {
        // Peer counselor mints token
        $peerToken = $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/token"
        );

        $peerToken->assertStatus(200);
        $peerToken->assertJson([
            'success' => true,
            'room' => "spatial-session-{$this->session->id}",
            'user' => [
                'id' => $this->peerCounselor->id,
                'role' => 'peer_counselor',
                'avatar' => 'dino-blue',
                'initial_x' => 400,
                'initial_y' => 320,
            ],
            'assigned_partner' => [
                'id' => $this->student->id,
                'role' => 'student',
                'avatar' => 'dino-green',
                'initial_x' => 600,
                'initial_y' => 320,
            ],
        ]);

        // Student mints token
        $studentToken = $this->actingAs($this->student)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/token"
        );

        $studentToken->assertStatus(200);
        $studentToken->assertJson([
            'success' => true,
            'room' => "spatial-session-{$this->session->id}",
            'user' => [
                'id' => $this->student->id,
                'role' => 'student',
                'avatar' => 'dino-green',
                'initial_x' => 600,
                'initial_y' => 320,
            ],
            'assigned_partner' => [
                'id' => $this->peerCounselor->id,
                'role' => 'peer_counselor',
                'avatar' => 'dino-blue',
                'initial_x' => 400,
                'initial_y' => 320,
            ],
        ]);
    }

    #[Test]
    public function anonymous_student_identity_is_hidden_in_spatial_lounge(): void
    {
        $this->session->is_anonymous = true;
        $this->session->save();

        $peerToken = $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/token"
        );

        $peerToken->assertStatus(200);
        $peerToken->assertJson([
            'assigned_partner' => [
                'name' => 'Anonymous Student',
            ],
        ]);
    }

    #[Test]
    public function unassigned_user_cannot_access_session_spatial_lounge_token(): void
    {
        $response = $this->actingAs($this->otherUser)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/token"
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function peer_counselor_can_fetch_assigned_students_with_spatial_state(): void
    {
        // First check with inactive lounge
        $response = $this->actingAs($this->peerCounselor)->getJson('/api/peer/assigned-students');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'spatial_lounge_active' => false,
        ]);

        // Request lounge
        $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );

        // Now lounge is active
        $activeResponse = $this->actingAs($this->peerCounselor)->getJson('/api/peer/assigned-students');
        $activeResponse->assertStatus(200);
        $activeResponse->assertJsonFragment([
            'session_id' => $this->session->id,
            'spatial_lounge_active' => true,
        ]);
    }

    #[Test]
    public function student_can_check_active_spatial_lounge_invitation(): void
    {
        // Initially no active lounge
        $response = $this->actingAs($this->student)->getJson('/api/student/active-spatial-lounge');
        $response->assertStatus(200);
        $response->assertJson(['active' => false]);

        // Peer requests lounge
        $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );

        // Student now detects active invitation
        $activeResponse = $this->actingAs($this->student)->getJson('/api/student/active-spatial-lounge');
        $activeResponse->assertStatus(200);
        $activeResponse->assertJson([
            'active' => true,
            'session_id' => $this->session->id,
        ]);
    }

    #[Test]
    public function peer_counselor_can_cancel_spatial_lounge_invitation(): void
    {
        // 1. Peer requests lounge
        $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );
        $this->assertTrue(Cache::has("spatial_lounge_session_{$this->session->id}"));

        // 2. Student checks active lounge -> true
        $studentActive = $this->actingAs($this->student)->getJson('/api/student/active-spatial-lounge');
        $studentActive->assertStatus(200);
        $studentActive->assertJson(['active' => true, 'session_id' => $this->session->id]);

        // 3. Peer cancels the lounge invitation
        $cancelResponse = $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/cancel"
        );
        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJson([
            'success' => true,
            'active' => false,
            'session_id' => $this->session->id,
        ]);

        $this->assertFalse(Cache::has("spatial_lounge_session_{$this->session->id}"));

        // 4. Student checks active lounge again -> false (cleared from dashboard)
        $studentAfter = $this->actingAs($this->student)->getJson('/api/student/active-spatial-lounge');
        $studentAfter->assertStatus(200);
        $studentAfter->assertJson(['active' => false]);

        // 5. Peer assigned list also reflects inactive
        $peerStudents = $this->actingAs($this->peerCounselor)->getJson('/api/peer/assigned-students');
        $peerStudents->assertStatus(200);
        $peerStudents->assertJsonFragment([
            'session_id' => $this->session->id,
            'spatial_lounge_active' => false,
        ]);
    }

    #[Test]
    public function peer_counselor_can_cancel_all_spatial_lounges(): void
    {
        $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );
        $this->assertTrue(Cache::has("spatial_lounge_session_{$this->session->id}"));

        $response = $this->actingAs($this->peerCounselor)->postJson('/api/peer/spatial-lounge/cancel-all');
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'active' => false,
        ]);

        $this->assertFalse(Cache::has("spatial_lounge_session_{$this->session->id}"));
    }

    #[Test]
    public function student_can_dismiss_spatial_lounge_invitation(): void
    {
        $this->actingAs($this->peerCounselor)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/request"
        );
        $this->assertTrue(Cache::has("spatial_lounge_session_{$this->session->id}"));

        $response = $this->actingAs($this->student)->postJson(
            "/api/sessions/{$this->session->id}/spatial-lounge/cancel"
        );
        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'active' => false]);
        $this->assertFalse(Cache::has("spatial_lounge_session_{$this->session->id}"));
    }
}
