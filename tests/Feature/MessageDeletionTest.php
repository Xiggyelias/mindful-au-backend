<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $counselor;
    private User $outsider;
    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['email' => 'student-delete@test.com']);
        $this->counselor = User::factory()->create(['email' => 'counselor-delete@test.com']);
        $this->outsider = User::factory()->create(['email' => 'outsider-delete@test.com']);

        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');
        $this->assignRole($this->outsider, 'student');

        $this->session = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    /** @test */
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

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    /** @test */
    public function participant_cannot_delete_someone_elses_message(): void
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

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    /** @test */
    public function non_participant_cannot_delete_message(): void
    {
        $message = $this->createMessage($this->student->id, $this->counselor->id);

        $response = $this->actingAs($this->outsider)->deleteJson(
            "/api/sessions/{$this->session->id}/messages/{$message->id}"
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    /** @test */
    public function message_must_belong_to_the_selected_session(): void
    {
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
