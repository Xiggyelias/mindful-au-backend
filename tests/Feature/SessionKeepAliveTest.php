<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Carbon;

class SessionKeepAliveTest extends TestCase
{
    public function test_session_touch_bumps_updated_at(): void
    {
        // Create a student
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first() ?? \App\Models\Role::create([
                'name' => 'student',
                'display_name' => 'Student',
                'level' => 1,
            ])
        )->create();

        // Create a counselor
        $counselor = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'counselor')->first() ?? \App\Models\Role::create([
                'name' => 'counselor',
                'display_name' => 'Counselor',
                'level' => 2,
            ])
        )->create();

        // Create an anonymous chat session
        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'is_anonymous' => true,
            'status' => 'active',
        ]);

        $originalUpdatedAt = $session->updated_at;
        sleep(1); // Ensure measurable time difference

        // Call the touch endpoint
        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'session_id', 'updated_at', 'throttled']);

        // Verify the session updated_at was bumped
        $session->refresh();
        $this->assertGreaterThan(
            $originalUpdatedAt->timestamp,
            $session->updated_at->timestamp,
            'Session updated_at should be bumped by touch endpoint'
        );
    }

    public function test_session_touch_throttles_rapid_calls(): void
    {
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        // First touch should not be throttled
        $response1 = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $this->assertFalse($response1->json('throttled'), 'First touch should not be throttled');

        // Rapid second touch should be throttled
        $response2 = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $this->assertTrue($response2->json('throttled'), 'Rapid second touch should be throttled');
    }

    public function test_cannot_touch_expired_session(): void
    {
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        // Create a cancelled session
        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertStatus(410)
            ->assertJson(['message' => 'Cannot touch inactive session']);
    }

    public function test_unauthorized_user_cannot_touch_session(): void
    {
        $student1 = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        $student2 = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        $session = CounselingSession::factory()->create([
            'student_id' => $student1->id,
            'status' => 'active',
        ]);

        // Student2 is not a participant, should be unauthorized
        $response = $this->actingAs($student2)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_counselor_can_touch_session(): void
    {
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        $counselor = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'counselor')->first()
        )->create();

        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($counselor)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_session_expiration_still_works_after_ttl(): void
    {
        // Verify that sessions still expire after the TTL if they're not touched
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        // Create a session that's been inactive for longer than TTL
        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'is_anonymous' => true,
            'status' => 'active',
            'updated_at' => now()->subHours(25), // Older than default 24h TTL
        ]);

        // Try to send a message (which checks expiration)
        $response = $this->actingAs($student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'This should fail',
                'message_type' => 'text',
            ]
        );

        // Should fail with 410 Gone
        $response->assertStatus(410)
            ->assertJson(['message' => 'This anonymous session has expired.']);
    }

    public function test_touch_resets_expiration_timer(): void
    {
        $student = User::factory()->hasAttached(
            \App\Models\Role::where('name', 'student')->first()
        )->create();

        $session = CounselingSession::factory()->create([
            'student_id' => $student->id,
            'is_anonymous' => true,
            'status' => 'active',
            'updated_at' => now()->subHours(20), // Getting close to expiry (24h default)
        ]);

        // Touch the session
        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $response->assertOk();

        $session->refresh();

        // Now it should be recent enough to not expire
        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', 24));
        $this->assertGreaterThan(
            now()->subHours($ttlHours),
            $session->updated_at,
            'After touch, session should be recent enough to not expire'
        );

        // Verify we can still access the session
        $response = $this->actingAs($student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'This should work',
                'message_type' => 'text',
            ]
        );

        $response->assertStatus(201);
    }
}
