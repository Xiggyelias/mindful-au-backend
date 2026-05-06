<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageUnreadReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $counselor;

    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['email' => 'student-unread@test.com']);
        $this->counselor = User::factory()->create(['email' => 'counselor-unread@test.com']);

        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');

        $this->session = CounselingSession::create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);
    }

    /** @test */
    public function fetching_messages_marks_all_inbound_unread_for_viewer_not_only_the_current_page(): void
    {
        $recipientId = $this->counselor->id;
        $senderId = $this->student->id;

        for ($i = 0; $i < 35; $i++) {
            Message::create([
                'session_id' => $this->session->id,
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'content' => "bulk unread {$i}",
                'message_type' => 'text',
                'is_encrypted' => false,
                'seen_at' => null,
            ]);
        }

        $this->assertSame(35, Message::query()
            ->where('session_id', $this->session->id)
            ->where('recipient_id', $recipientId)
            ->whereNull('seen_at')
            ->count());

        $this->actingAs($this->counselor)->getJson(
            '/api/sessions/' . $this->session->id . '/messages?limit=10'
        )->assertStatus(200);

        $this->assertSame(0, Message::query()
            ->where('session_id', $this->session->id)
            ->where('recipient_id', $recipientId)
            ->whereNull('seen_at')
            ->count());
    }

    /** @test */
    public function mark_read_endpoint_clears_unread_without_fetching_messages(): void
    {
        Message::create([
            'session_id' => $this->session->id,
            'sender_id' => $this->student->id,
            'recipient_id' => $this->counselor->id,
            'content' => 'hello counselor',
            'message_type' => 'text',
            'is_encrypted' => false,
            'seen_at' => null,
        ]);

        $this->actingAs($this->counselor)
            ->postJson('/api/sessions/' . $this->session->id . '/messages/read')
            ->assertStatus(204);

        $unread = Message::query()
            ->where('session_id', $this->session->id)
            ->where('recipient_id', $this->counselor->id)
            ->whereNull('seen_at')
            ->count();
        $this->assertSame(0, $unread);
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }
}
