<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PeerChatRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $counselor;

    private User $peer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->student = User::factory()->create(['email' => 'peer-restrict-student@test.com']);
        $this->counselor = User::factory()->create(['email' => 'peer-restrict-counselor@test.com']);
        $this->peer = User::factory()->create(['email' => 'peer-restrict-peer@test.com']);

        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');
        $this->assignRole($this->peer, 'peer_counselor');
    }

    /** @test */
    public function peer_counselor_cannot_upload_attachment_in_delegated_session(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $file = UploadedFile::fake()->create('note.png', 128, 'image/png');

        $response = $this->actingAs($this->peer)->post('/api/chat/upload-file', [
            'session_id' => $session->id,
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('chat_files', 0);
    }

    /** @test */
    public function student_can_upload_attachment_in_peer_delegated_session(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $file = UploadedFile::fake()->create('handout.pdf', 256, 'application/pdf');

        $response = $this->actingAs($this->student)->post('/api/chat/upload-file', [
            'session_id' => $session->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('chat_files', 1);
    }

    /** @test */
    public function peer_counselor_cannot_post_non_text_message_type(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->peer)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'voice-placeholder',
                'message_type' => 'voice',
                'is_encrypted' => false,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Peer counselors can only send text messages in supervised chat.',
        ]);
        $this->assertDatabaseCount('messages', 0);
    }

    /** @test */
    public function supervising_counselor_can_join_peer_delegated_thread_without_new_room(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->counselor)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'I am joining this conversation.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('sender_id', $this->counselor->id)
            ->assertJsonPath('sender_role', 'counselor')
            ->assertJsonPath('sender_display_name', 'peer-restrict-counselor')
            ->assertJsonPath('recipient_id', $this->student->id);

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function assigned_peer_counselor_can_create_session_note(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->peer)->putJson("/api/sessions/{$session->id}/note", [
            'notes' => 'Student asked for follow-up tomorrow.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('session.notes', 'Student asked for follow-up tomorrow.');
    }

    /** @test */
    public function assigning_peer_counselor_keeps_the_same_case_room(): void
    {
        $session = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'assigned_role' => 'counselor',
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$session->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor');

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function counselor_starting_chat_with_peer_assigned_student_reuses_case_room(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->counselor)->postJson('/api/sessions/counselor', [
            'student_id' => $this->student->id,
            'session_type' => 'chat',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor');

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function student_starting_same_counselor_chat_reuses_peer_case_room(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->student)->postJson('/api/sessions', [
            'counselor_id' => $this->counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor');

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function message_sender_snapshots_survive_counselor_join_and_assignment_changes(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $peerResponse = $this->actingAs($this->peer)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'Peer support message.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $peerResponse
            ->assertStatus(201)
            ->assertJsonPath('sender_role', 'peer_counselor')
            ->assertJsonPath('sender_display_name', 'peer-restrict-peer');

        $counselorResponse = $this->actingAs($this->counselor)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'Counselor joining same case.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $counselorResponse
            ->assertStatus(201)
            ->assertJsonPath('sender_role', 'counselor')
            ->assertJsonPath('sender_display_name', 'peer-restrict-counselor');

        $session->update([
            'assigned_role' => 'counselor',
            'peer_counselor_id' => null,
        ]);

        $history = $this->actingAs($this->student)->getJson("/api/sessions/{$session->id}/messages?limit=10");

        $history
            ->assertStatus(200)
            ->assertJsonPath('0.sender_role', 'peer_counselor')
            ->assertJsonPath('0.sender_display_name', 'peer-restrict-peer')
            ->assertJsonPath('1.sender_role', 'counselor')
            ->assertJsonPath('1.sender_display_name', 'peer-restrict-counselor');
    }

    private function makeDelegatedPeerSession(): CounselingSession
    {
        return CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'peer_counselor_id' => $this->peer->id,
            'assigned_role' => 'peer_counselor',
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }
}
