<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounselorWalkInAndVideoNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $counselor;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->counselor = User::factory()->create(['email' => 'counselor-walkin@africau.edu']);
        $this->assignRole($this->counselor, 'counselor');
        $this->counselor->profile()->create([
            'full_name' => 'Dr. Counselor',
            'is_counselor_approved' => true,
        ]);

        $this->student = User::factory()->create(['email' => 'student-walkin@africau.edu']);
        $this->assignRole($this->student, 'student');
        $this->student->profile()->create([
            'full_name' => 'Walkin Student',
        ]);
    }

    #[Test]
    public function counselor_can_document_walk_in_consultation_for_registered_student(): void
    {
        $payload = [
            'student_id' => $this->student->id,
            'consultation_mode' => 'in_person_walkin',
            'chief_complaint' => 'Acute anxiety before exams',
            'risk_level' => 'medium',
            'notes' => "S: Student reported acute anxiety.\nO: Agitated, rapid speech.\nA: Situational exam distress.\nP: Introduced box breathing; safety plan established.",
            'action_taken' => 'Breathing exercises practiced; follow-up scheduled in 48h',
            'duration_minutes' => 45,
            'started_at' => now()->toIso8601String(),
        ];

        $response = $this->actingAs($this->counselor)
            ->postJson('/api/sessions/walk-in', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('session.session_type', 'in_person')
            ->assertJsonPath('session.status', 'completed')
            ->assertJsonPath('session.counselor_id', $this->counselor->id)
            ->assertJsonPath('session.student_id', $this->student->id);

        $sessionId = $response->json('session.id');
        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $sessionId,
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'session_type' => 'in_person',
            'status' => 'completed',
        ]);

        // Synchronized appointment created
        $this->assertDatabaseHas('appointments', [
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'status' => 'completed',
            'duration_minutes' => 45,
        ]);
    }

    #[Test]
    public function counselor_can_document_physical_emergency_for_unregistered_visitor(): void
    {
        $payload = [
            'student_id' => null,
            'visitor_name' => 'Campus Visitor Mark',
            'visitor_identifier' => 'EXT-2026-99',
            'consultation_mode' => 'physical_emergency',
            'chief_complaint' => 'Acute grief and panic',
            'risk_level' => 'high',
            'notes' => 'Emergency physical walk-in. Stabilized in office with campus chaplain present.',
            'action_taken' => 'Chaplaincy alerted; accompanied to student clinic.',
            'duration_minutes' => 60,
        ];

        $response = $this->actingAs($this->counselor)
            ->postJson('/api/sessions/walk-in', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('session.session_type', 'in_person')
            ->assertJsonPath('session.is_anonymous', true);

        $this->assertDatabaseHas('counseling_sessions', [
            'id' => $response->json('session.id'),
            'counselor_id' => $this->counselor->id,
            'is_anonymous' => true,
            'anonymous_id' => 'WalkIn (EXT-2026-99)',
        ]);
    }

    #[Test]
    public function student_cannot_create_walk_in_session(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/sessions/walk-in', [
                'consultation_mode' => 'in_person_walkin',
                'notes' => 'Unauthorized attempt',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function counselor_can_update_notes_on_documented_walk_in_session(): void
    {
        $createResponse = $this->actingAs($this->counselor)
            ->postJson('/api/sessions/walk-in', [
                'student_id' => $this->student->id,
                'consultation_mode' => 'in_person_walkin',
                'notes' => 'Initial in-person consultation note.',
            ]);

        $createResponse->assertStatus(201);
        $sessionId = $createResponse->json('session.id');

        $updateResponse = $this->actingAs($this->counselor)
            ->putJson("/api/sessions/{$sessionId}/note", [
                'notes' => 'Updated clinical documentation: student attended check-in with improved affect.',
            ]);

        $updateResponse->assertStatus(200);

        // Fetch session and verify updated notes
        $showResponse = $this->actingAs($this->counselor)->getJson("/api/sessions/{$sessionId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('notes', 'Updated clinical documentation: student attended check-in with improved affect.');
    }

    #[Test]
    public function counselor_can_fetch_or_establish_session_for_video_appointment(): void
    {
        $appointment = Appointment::query()->create([
            'student_id' => $this->student->id,
            'counselor_id' => $this->counselor->id,
            'scheduled_at' => now(),
            'duration_minutes' => 60,
            'status' => 'confirmed',
            'notes' => 'Video / online consultation',
        ]);

        $response = $this->actingAs($this->counselor)
            ->getJson("/api/appointments/{$appointment->id}/session");

        $response->assertStatus(200)
            ->assertJsonPath('student_id', $this->student->id)
            ->assertJsonPath('counselor_id', $this->counselor->id)
            ->assertJsonPath('session_type', 'video');
    }

    private function assignRole(User $user, string $role): void
    {
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);
    }
}
