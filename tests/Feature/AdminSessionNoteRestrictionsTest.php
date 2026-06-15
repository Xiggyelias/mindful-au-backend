<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSessionNoteRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $counselor;

    private CounselingSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['email' => 'admin-notes@test.com']);
        $this->student = User::factory()->create(['email' => 'student-notes@test.com']);
        $this->counselor = User::factory()->create(['email' => 'counselor-notes@test.com']);

        $this->assignRole($this->admin, 'admin');
        $this->assignRole($this->student, 'student');
        $this->assignRole($this->counselor, 'counselor');

        $this->session = CounselingSession::query()->create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
            'notes' => 'Confidential counselor notes',
        ]);
    }

    #[Test]
    public function admin_sees_redacted_notes_when_fetching_session(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/sessions/{$this->session->id}");

        $response
            ->assertStatus(200)
            ->assertJson([
                'id' => $this->session->id,
                'notes' => null,
                'notes_redacted' => true,
            ]);
    }

    #[Test]
    public function admin_cannot_update_confidential_notes(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/sessions/{$this->session->id}", [
            'notes' => 'Admin should not update this',
        ]);

        $response
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Admins are not allowed to edit confidential counseling notes.',
            ]);
    }

    #[Test]
    public function admin_cannot_use_note_upsert_endpoint(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/sessions/{$this->session->id}/note", [
            'notes' => 'Admin note edit attempt',
        ]);

        $response->assertStatus(403);
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }
}
