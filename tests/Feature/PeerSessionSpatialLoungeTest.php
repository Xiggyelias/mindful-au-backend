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
}
