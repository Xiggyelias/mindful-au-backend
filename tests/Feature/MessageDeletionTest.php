<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $counselor;

    private User $peerCounselor;

    private User $outsider;

    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['email' => 'student-delete@test.com']);
        $this->counselor = User::factory()->create(['email' => 'counselor-delete@test.com']);
        $this->peerCounselor = User::factory()->create(['email' => 'peer-delete@test.com']);
        $this->outsider = User::factory()->create(['email' => 'outsider-delete@test.com']);

        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');
        $this->assignRole($this->peerCounselor, 'peer_counselor');
        $this->assignRole($this->outsider, 'student');

        config(['chat.delete_for_everyone_minutes' => 15]);

        $this->session = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    #[Test]
    public function sender_can_delete_own_message(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);

        $response = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'id' => $message->id,
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'This message was deleted.',
            'message_type' => 'text',
            'has_file' => false,
            'is_encrypted' => false,
        ]);
    }

    #[Test]
    public function counselor_cannot_delete_student_message_for_everyone(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);

        $response = $this->actingAs($this->counselor)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You can only delete messages you sent.',
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'test message',
        ]);
    }

    #[Test]
    public function assigned_peer_counselor_cannot_delete_student_message_for_everyone(): void
    {
        $this->session->update([
            'peer_counselor_id' => $this->peerCounselor->id,
            'assigned_role' => 'peer_counselor',
        ]);
        $message = $this->createMessage($this->student->id, $this->peerCounselor->id);

        $response = $this->actingAs($this->peerCounselor)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You can only delete messages you sent.',
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'test message',
        ]);
    }

    #[Test]
    public function sender_cannot_delete_for_everyone_after_time_limit_expires(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);
        $message->forceFill([
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ])->saveQuietly();

        $response = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Delete for everyone is only available for 15 minutes after sending.',
            ]);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'test message',
        ]);
    }

    #[Test]
    public function student_cannot_delete_counselor_message(): void
    {
        $message = $this->createMessage($this->counselor->id, $this->student->id);

        $response = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You can only delete messages you sent.',
            ]);

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    #[Test]
    public function non_participant_cannot_delete_message(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);

        $response = $this->actingAs($this->outsider)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    #[Test]
    public function message_must_belong_to_the_selected_session(): void
    {
        $this->session->update(['status' => 'completed']);
        $otherSession = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);
        $message = Message::create([
            'session_id' => $otherSession->id,
            'sender_id' => $this->student->id,
            'recipient_id' => $this->counselor->id,
            'content' => 'message in other session',
            'message_type' => 'text',
            'is_encrypted' => false,
        ]);

        $response = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response->assertStatus(404);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    #[Test]
    public function deleting_for_everyone_removes_related_notifications(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);
        $notification = Notification::create([
            'user_id' => $this->counselor->id,
            'title' => 'New message',
            'message' => 'Student sent a message',
            'type' => 'info',
            'meta' => [
                'chat_session_id' => $this->session->id,
                'chat_message_id' => $message->id,
            ],
        ]);

        $response = $this->actingAs($this->student)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }

    private function createMessage(int $senderId, int $recipientId): Message
    {
        return Message::create([
            'session_id' => $this->session->id,
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'content' => 'test message',
            'message_type' => 'text',
            'is_encrypted' => false,
        ]);
    }
}
