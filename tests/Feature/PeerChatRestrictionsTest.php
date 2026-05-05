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
