<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSessionControlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_open_a_student_chat_case_for_a_selected_counselor(): void
    {
        $admin = $this->createPortalUser('admin', 'admin-case-control@test.com', 'Admin Case Control');
        $student = $this->createPortalUser('student', 'student-case-control@test.com', 'Student Case Control');
        $counselor = $this->createPortalUser('counselor', 'counselor-case-control@test.com', 'Counselor Case Control');

        $response = $this->actingAs($admin)->postJson('/api/sessions/counselor', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'session_type' => 'chat',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('student_id', $student->id)
            ->assertJsonPath('counselor_id', $counselor->id)
            ->assertJsonPath('assigned_by', $admin->id)
            ->assertJsonPath('assigned_role', 'counselor');

        $this->assertDatabaseHas('counseling_sessions', [
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'assigned_by' => $admin->id,
            'session_type' => 'chat',
        ]);
    }

    #[Test]
    public function admin_must_choose_a_supervising_counselor_when_opening_a_case(): void
    {
        $admin = $this->createPortalUser('admin', 'admin-missing-counselor@test.com', 'Admin Missing Counselor');
        $student = $this->createPortalUser('student', 'student-missing-counselor@test.com', 'Student Missing Counselor');

        $response = $this->actingAs($admin)->postJson('/api/sessions/counselor', [
            'student_id' => $student->id,
            'session_type' => 'chat',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['counselor_id']);

        $this->assertSame(0, CounselingSession::query()->count());
    }

    #[Test]
    public function counselor_cannot_open_a_case_on_behalf_of_another_counselor(): void
    {
        $student = $this->createPortalUser('student', 'student-cross-counselor@test.com', 'Student Cross Counselor');
        $counselor = $this->createPortalUser('counselor', 'counselor-cross-a@test.com', 'Counselor Cross A');
        $otherCounselor = $this->createPortalUser('counselor', 'counselor-cross-b@test.com', 'Counselor Cross B');

        $response = $this->actingAs($counselor)->postJson('/api/sessions/counselor', [
            'student_id' => $student->id,
            'counselor_id' => $otherCounselor->id,
            'session_type' => 'chat',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Only administrators can create sessions for another counselor');

        $this->assertSame(0, CounselingSession::query()->count());
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
