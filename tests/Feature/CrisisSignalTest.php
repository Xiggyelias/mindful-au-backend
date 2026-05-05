<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrisisSignalTest extends TestCase
{
    use RefreshDatabase;

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }

    /** @test */
    public function student_posting_verified_keywords_triggers_staff_notification(): void
    {
        $student = User::factory()->create();
        $counselor = User::factory()->create();
        $this->assignRole($student, 'student');
        $this->assignRole($counselor, 'counselor');

        $session = CounselingSession::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/crisis-signal", [
            'keywords' => ['suicide'],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertTrue(
            Notification::query()->where('user_id', $counselor->id)->exists(),
            'Counselor should receive crisis notification'
        );
    }

    /** @test */
    public function counselor_cannot_post_crisis_signal_for_student_session(): void
    {
        $student = User::factory()->create();
        $counselor = User::factory()->create();
        $this->assignRole($student, 'student');
        $this->assignRole($counselor, 'counselor');

        $session = CounselingSession::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        $response = $this->actingAs($counselor)->postJson("/api/sessions/{$session->id}/crisis-signal", [
            'keywords' => ['suicide'],
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Notification::query()->count());
    }

    /** @test */
    public function unverified_keywords_return_422(): void
    {
        $student = User::factory()->create();
        $counselor = User::factory()->create();
        $this->assignRole($student, 'student');
        $this->assignRole($counselor, 'counselor');

        $session = CounselingSession::create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
            'session_type' => 'chat',
        ]);

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/crisis-signal", [
            'keywords' => ['hello there nothing'],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Notification::query()->count());
    }
}
