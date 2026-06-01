<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\Notification;
use App\Models\PeerAssignment;
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
    public function assigning_peer_counselor_creates_a_separate_peer_room(): void
    {
        $session = $this->makeDirectCounselorSession();

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$session->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);

        $peerSessionId = (int) $response->json('id');

        $response
            ->assertStatus(200)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor')
            ->assertJsonPath('is_anonymous', false)
            ->assertJsonPath('status', 'active');

        $this->assertNotSame((int) $session->id, $peerSessionId);
        $this->assertSame(2, CounselingSession::query()->count());

        $session->refresh();
        $this->assertNull($session->peer_counselor_id);
        $this->assertSame('counselor', $session->assigned_role);
        $this->assertSame('active', $session->status);

        $peerRoom = CounselingSession::query()->findOrFail($peerSessionId);
        $this->assertFalse((bool) $peerRoom->is_anonymous);
        $this->assertNull($peerRoom->anonymous_id);
        $this->assertNull($peerRoom->identity_revealed_at);

        $this->assertDatabaseHas('peer_assignments', [
            'session_id' => $peerSessionId,
            'peer_counselor_id' => $this->peer->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function student_chat_list_returns_direct_and_peer_rooms_as_separate_rows(): void
    {
        $directSession = $this->makeDirectCounselorSession();

        $assignResponse = $this->actingAs($this->counselor)->postJson("/api/sessions/{$directSession->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);
        $assignResponse->assertStatus(200);
        $peerSessionId = (int) $assignResponse->json('id');

        $response = $this->actingAs($this->student)
            ->getJson('/api/sessions/chat-list?as_role=student&open_only=1&page=1&per_page=20');

        $response->assertStatus(200);
        $rows = collect($response->json('data'));
        $directRow = $rows->firstWhere('id', $directSession->id);
        $peerRow = $rows->firstWhere('id', $peerSessionId);

        $this->assertNotNull($directRow, 'Direct counselor session is missing from the student chat list.');
        $this->assertNotNull($peerRow, 'Peer support session is missing from the student chat list.');
        $this->assertNotSame((int) $directRow['id'], (int) $peerRow['id']);
        $this->assertNull($directRow['peer_counselor_id']);
        $this->assertSame('counselor', $directRow['assigned_role']);
        $this->assertSame($this->peer->id, (int) $peerRow['peer_counselor_id']);
        $this->assertSame('peer_counselor', $peerRow['assigned_role']);
    }

    /** @test */
    public function student_chat_list_keeps_unread_counts_per_session(): void
    {
        $directSession = $this->makeDirectCounselorSession();

        $assignResponse = $this->actingAs($this->counselor)->postJson("/api/sessions/{$directSession->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);
        $assignResponse->assertStatus(200);
        $peerSessionId = (int) $assignResponse->json('id');

        Message::create([
            'session_id' => $directSession->id,
            'sender_id' => $this->counselor->id,
            'recipient_id' => $this->student->id,
            'content' => 'Direct counselor unread.',
            'message_type' => 'text',
            'is_encrypted' => false,
            'seen_at' => null,
        ]);

        Message::create([
            'session_id' => $peerSessionId,
            'sender_id' => $this->peer->id,
            'recipient_id' => $this->student->id,
            'content' => 'Peer support unread.',
            'message_type' => 'text',
            'is_encrypted' => false,
            'seen_at' => null,
        ]);

        Message::create([
            'session_id' => $peerSessionId,
            'sender_id' => $this->peer->id,
            'recipient_id' => $this->student->id,
            'content' => 'Peer support already seen.',
            'message_type' => 'text',
            'is_encrypted' => false,
            'seen_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->getJson('/api/sessions/chat-list?as_role=student&open_only=1&page=1&per_page=20');

        $response->assertStatus(200);
        $rows = collect($response->json('data'));
        $directRow = $rows->firstWhere('id', $directSession->id);
        $peerRow = $rows->firstWhere('id', $peerSessionId);

        $this->assertNotNull($directRow, 'Direct counselor session is missing from the student chat list.');
        $this->assertNotNull($peerRow, 'Peer support session is missing from the student chat list.');
        $this->assertSame(1, (int) $directRow['unread_count']);
        $this->assertSame(1, (int) $peerRow['unread_count']);
    }

    /** @test */
    public function assigning_peer_counselor_rejects_the_student_as_the_peer(): void
    {
        $this->assignRole($this->student, 'peer_counselor');
        $session = $this->makeDirectCounselorSession();

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$session->id}/assign-peer", [
            'peer_counselor_id' => $this->student->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'The student cannot be assigned as their own peer counselor');

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function assigning_peer_counselor_rejects_the_supervising_counselor_as_the_peer(): void
    {
        $this->assignRole($this->counselor, 'peer_counselor');
        $session = $this->makeDirectCounselorSession();

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$session->id}/assign-peer", [
            'peer_counselor_id' => $this->counselor->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'The supervising counselor cannot also be the peer counselor for this case');

        $this->assertSame(1, CounselingSession::query()->count());
    }

    /** @test */
    public function counselor_starting_chat_with_peer_assigned_student_reuses_the_direct_room(): void
    {
        $directSession = $this->makeDirectCounselorSession();
        $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->counselor)->postJson('/api/sessions/counselor', [
            'student_id' => $this->student->id,
            'session_type' => 'chat',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $directSession->id)
            ->assertJsonPath('peer_counselor_id', null)
            ->assertJsonPath('assigned_role', 'counselor');

        $this->assertSame(2, CounselingSession::query()->count());
    }

    /** @test */
    public function student_starting_same_counselor_chat_reuses_the_direct_room(): void
    {
        $directSession = $this->makeDirectCounselorSession();
        $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->student)->postJson('/api/sessions', [
            'counselor_id' => $this->counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => false,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $directSession->id)
            ->assertJsonPath('peer_counselor_id', null)
            ->assertJsonPath('assigned_role', 'counselor');

        $this->assertSame(2, CounselingSession::query()->count());
    }

    /** @test */
    public function unassigning_peer_counselor_closes_peer_room_without_mutating_direct_room(): void
    {
        $directSession = $this->makeDirectCounselorSession();

        $assignResponse = $this->actingAs($this->counselor)->postJson("/api/sessions/{$directSession->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);
        $assignResponse->assertStatus(200);
        $peerSessionId = (int) $assignResponse->json('id');

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$directSession->id}/unassign-peer");

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $peerSessionId)
            ->assertJsonPath('peer_counselor_id', null)
            ->assertJsonPath('assigned_role', 'counselor')
            ->assertJsonPath('status', 'completed');

        $directSession->refresh();
        $this->assertNull($directSession->peer_counselor_id);
        $this->assertSame('counselor', $directSession->assigned_role);
        $this->assertSame('active', $directSession->status);

        $this->assertDatabaseHas('peer_assignments', [
            'session_id' => $peerSessionId,
            'peer_counselor_id' => $this->peer->id,
            'status' => 'closed',
        ]);
    }

    /** @test */
    public function assigning_peer_counselor_preserves_anonymous_source_chat(): void
    {
        $session = $this->makeDirectCounselorSession();
        $anonymousId = CounselingSession::generateUniqueAnonymousId();
        $session->update([
            'is_anonymous' => true,
            'anonymous_id' => $anonymousId,
        ]);

        $response = $this->actingAs($this->counselor)->postJson("/api/sessions/{$session->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor')
            ->assertJsonPath('is_anonymous', true);

        $peerRoom = CounselingSession::query()->findOrFail((int) $response->json('id'));
        $this->assertTrue((bool) $peerRoom->is_anonymous);
        $this->assertNotEmpty($peerRoom->anonymous_id);
        $this->assertNotSame($anonymousId, $peerRoom->anonymous_id);
    }

    /** @test */
    public function peer_escalation_keeps_peer_and_counselor_in_shared_case_room(): void
    {
        $session = $this->makeDelegatedPeerSession();
        PeerAssignment::create([
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'assigned_by' => $this->counselor->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->peer)->postJson("/api/sessions/{$session->id}/escalate", [
            'reason' => 'Student asked for counselor support.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor')
            ->assertJsonPath('status', 'active');

        $this->assertSame(1, CounselingSession::query()->count());
        $this->assertDatabaseHas('peer_assignments', [
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'status' => 'escalated',
        ]);
    }

    /** @test */
    public function urgent_peer_flag_keeps_peer_visible_in_shared_case_room(): void
    {
        $session = $this->makeDelegatedPeerSession();
        PeerAssignment::create([
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'assigned_by' => $this->counselor->id,
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($this->peer)->postJson("/api/sessions/{$session->id}/flag-urgent", [
            'reason' => 'Needs urgent counselor review.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('peer_counselor_id', $this->peer->id)
            ->assertJsonPath('assigned_role', 'peer_counselor')
            ->assertJsonPath('status', 'active');

        $this->assertSame(1, CounselingSession::query()->count());
        $this->assertDatabaseHas('peer_assignments', [
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'status' => 'escalated',
        ]);
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

    /** @test */
    public function counselor_chat_list_exposes_case_peer_even_for_legacy_escalated_rooms(): void
    {
        $session = $this->makeDelegatedPeerSession();

        PeerAssignment::create([
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'assigned_by' => $this->counselor->id,
            'status' => 'escalated',
        ]);

        $session->update([
            'assigned_role' => 'counselor',
            'peer_counselor_id' => null,
        ]);

        $response = $this->actingAs($this->counselor)->getJson('/api/sessions/chat-list?as_role=counselor&open_only=1&limit=20');

        $response
            ->assertStatus(200)
            ->assertJsonPath('0.id', $session->id)
            ->assertJsonPath('0.peer_counselor_id', null)
            ->assertJsonPath('0.case_peer_counselor_id', $this->peer->id)
            ->assertJsonPath('0.case_peer_counselor.email', $this->peer->email);
    }

    /** @test */
    public function peer_chat_list_shows_student_identity_for_nonanonymous_peer_rooms(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->peer)->getJson('/api/sessions/chat-list?as_role=peer_counselor&open_only=1&limit=20');

        $response
            ->assertStatus(200)
            ->assertJsonPath('0.id', $session->id)
            ->assertJsonPath('0.is_anonymous', false)
            ->assertJsonPath('0.identity_visible_to_viewer', true)
            ->assertJsonPath('0.student_id', $this->student->id)
            ->assertJsonPath('0.student.email', $this->student->email)
            ->assertJsonPath('0.student.profile.full_name', 'peer-restrict-student');
    }

    /** @test */
    public function counselor_chat_list_shows_student_identity_for_nonanonymous_shared_peer_rooms(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $response = $this->actingAs($this->counselor)->getJson('/api/sessions/chat-list?as_role=counselor&open_only=1&limit=20');

        $response
            ->assertStatus(200)
            ->assertJsonPath('0.id', $session->id)
            ->assertJsonPath('0.is_anonymous', false)
            ->assertJsonPath('0.identity_visible_to_viewer', true)
            ->assertJsonPath('0.student_id', $this->student->id)
            ->assertJsonPath('0.student.email', $this->student->email)
            ->assertJsonPath('0.student.profile.full_name', 'peer-restrict-student');
    }

    /** @test */
    public function peer_counselor_sees_student_messages_masked_in_delegated_session(): void
    {
        $session = $this->makeDelegatedPeerSession();
        $session->update([
            'is_anonymous' => true,
            'anonymous_id' => CounselingSession::generateUniqueAnonymousId(),
        ]);

        $this->actingAs($this->student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'I need peer support.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        )->assertStatus(201);

        $history = $this->actingAs($this->peer)->getJson("/api/sessions/{$session->id}/messages?limit=10");

        $history
            ->assertStatus(200)
            ->assertJsonPath('0.sender_id', 0)
            ->assertJsonPath('0.sender_display_name', 'Anonymous User')
            ->assertJsonPath('0.sent_as_anonymous', true);
    }

    /** @test */
    public function student_can_turn_off_anonymous_mode_for_supervised_peer_room(): void
    {
        $directSession = $this->makeDirectCounselorSession();
        $directSession->update([
            'is_anonymous' => true,
            'anonymous_id' => CounselingSession::generateUniqueAnonymousId(),
        ]);

        $assignResponse = $this->actingAs($this->counselor)->postJson("/api/sessions/{$directSession->id}/assign-peer", [
            'peer_counselor_id' => $this->peer->id,
        ]);
        $assignResponse->assertStatus(200)->assertJsonPath('is_anonymous', true);

        $peerSessionId = (int) $assignResponse->json('id');

        $this->actingAs($this->student)->patchJson("/api/sessions/{$peerSessionId}/chat-anonymity", [
            'is_anonymous' => false,
        ])->assertStatus(200)
            ->assertJsonPath('is_anonymous', false)
            ->assertJsonPath('anonymous_id', null);

        $this->actingAs($this->peer)
            ->getJson('/api/sessions/chat-list?as_role=peer_counselor&open_only=1&limit=20')
            ->assertStatus(200)
            ->assertJsonPath('0.id', $peerSessionId)
            ->assertJsonPath('0.is_anonymous', false)
            ->assertJsonPath('0.identity_visible_to_viewer', true)
            ->assertJsonPath('0.student_id', $this->student->id)
            ->assertJsonPath('0.student.profile.full_name', 'peer-restrict-student');

        $this->actingAs($this->counselor)
            ->getJson('/api/sessions/chat-list?as_role=counselor&open_only=1&limit=20')
            ->assertStatus(200)
            ->assertJsonPath('0.id', $peerSessionId)
            ->assertJsonPath('0.is_anonymous', false)
            ->assertJsonPath('0.identity_visible_to_viewer', true)
            ->assertJsonPath('0.student_id', $this->student->id)
            ->assertJsonPath('0.student.profile.full_name', 'peer-restrict-student');
    }

    /** @test */
    public function legacy_escalated_case_peer_can_still_send_in_the_shared_room(): void
    {
        $session = $this->makeDelegatedPeerSession();

        PeerAssignment::create([
            'session_id' => $session->id,
            'peer_counselor_id' => $this->peer->id,
            'assigned_by' => $this->counselor->id,
            'status' => 'escalated',
        ]);

        $session->update([
            'assigned_role' => 'counselor',
            'peer_counselor_id' => null,
        ]);

        $response = $this->actingAs($this->peer)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'Still supporting the student.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('sender_id', $this->peer->id)
            ->assertJsonPath('sender_role', 'peer_counselor')
            ->assertJsonPath('recipient_id', $this->student->id);
    }

    /** @test */
    public function peer_room_message_notifications_only_go_to_the_direct_recipient(): void
    {
        $session = $this->makeDelegatedPeerSession();

        $studentResponse = $this->actingAs($this->student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'Student to peer only.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $studentResponse
            ->assertStatus(201)
            ->assertJsonPath('recipient_id', $this->peer->id);

        $studentMessageId = (int) $studentResponse->json('id');
        $this->assertTrue($this->messageNotificationExistsFor($this->peer->id, $studentMessageId));
        $this->assertFalse($this->messageNotificationExistsFor($this->counselor->id, $studentMessageId));

        $peerResponse = $this->actingAs($this->peer)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'Peer reply to student only.',
                'message_type' => 'text',
                'is_encrypted' => false,
            ]
        );

        $peerResponse
            ->assertStatus(201)
            ->assertJsonPath('recipient_id', $this->student->id);

        $peerMessageId = (int) $peerResponse->json('id');
        $this->assertTrue($this->messageNotificationExistsFor($this->student->id, $peerMessageId));
        $this->assertFalse($this->messageNotificationExistsFor($this->counselor->id, $peerMessageId));
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

    private function makeDirectCounselorSession(): CounselingSession
    {
        return CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'peer_counselor_id' => null,
            'assigned_role' => 'counselor',
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    private function messageNotificationExistsFor(int $userId, int $messageId): bool
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('meta->chat_message_id', $messageId)
            ->exists();
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }
}
