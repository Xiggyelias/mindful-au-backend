<?php

namespace Tests\Feature;

use App\Models\CounselingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SessionKeepAliveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_session_touch_bumps_updated_at(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $session = $this->createSession($student, $counselor, [
            'is_anonymous' => true,
            'status' => 'active',
        ]);
        $this->setSessionUpdatedAt($session, now()->subMinute());
        $originalUpdatedAt = $session->fresh()->updated_at;

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['ok', 'session_id', 'updated_at', 'throttled']);

        $session->refresh();
        $this->assertTrue(
            $session->updated_at->greaterThan($originalUpdatedAt),
            'Session updated_at should be bumped by touch endpoint'
        );
    }

    public function test_session_touch_throttles_rapid_calls(): void
    {
        $student = $this->createUserWithRole('student');
        $session = $this->createSession($student);

        $response1 = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $response1->assertOk();
        $this->assertFalse($response1->json('throttled'), 'First touch should not be throttled');

        $response2 = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $response2->assertOk();
        $this->assertTrue($response2->json('throttled'), 'Rapid second touch should be throttled');
    }

    public function test_cannot_touch_expired_session(): void
    {
        $student = $this->createUserWithRole('student');
        $session = $this->createSession($student, null, [
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertStatus(410)
            ->assertJson(['message' => 'Cannot touch inactive session']);
    }

    public function test_unauthorized_user_cannot_touch_session(): void
    {
        $student1 = $this->createUserWithRole('student');
        $student2 = $this->createUserWithRole('student');
        $session = $this->createSession($student1);

        $response = $this->actingAs($student2)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_counselor_can_touch_session(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $session = $this->createSession($student, $counselor);

        $response = $this->actingAs($counselor)->postJson("/api/sessions/{$session->id}/touch");

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_session_expiration_still_works_after_ttl(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $session = $this->createSession($student, $counselor, [
            'is_anonymous' => true,
            'status' => 'active',
        ]);
        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', 24));
        $this->setSessionUpdatedAt($session, now()->subHours($ttlHours + 1));
        $this->assertTrue($session->updated_at->lessThan(now()->subHours($ttlHours)));

        $response = $this->actingAs($student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'This should fail',
                'message_type' => 'text',
            ]
        );

        $response->assertStatus(410)
            ->assertJson(['message' => 'This anonymous session has expired.']);
    }

    public function test_student_create_session_does_not_reuse_expired_anonymous_chat(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $expired = $this->createSession($student, $counselor, [
            'is_anonymous' => true,
            'status' => 'active',
        ]);
        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', 24));
        $this->setSessionUpdatedAt($expired, now()->subHours($ttlHours + 1));

        $response = $this->actingAs($student)->postJson('/api/sessions', [
            'counselor_id' => $counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => true,
        ]);

        $response->assertCreated();
        $this->assertNotSame((int) $expired->id, (int) $response->json('id'));
        $this->assertSame('cancelled', $expired->fresh()->status);
    }

    public function test_student_can_force_new_anonymous_chat_even_when_one_is_open(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $existing = $this->createSession($student, $counselor, [
            'is_anonymous' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($student)->postJson('/api/sessions', [
            'counselor_id' => $counselor->id,
            'session_type' => 'chat',
            'is_anonymous' => true,
            'force_new' => true,
        ]);

        $response->assertCreated();
        $this->assertNotSame((int) $existing->id, (int) $response->json('id'));
        $this->assertSame('active', $existing->fresh()->status);
    }

    public function test_touch_resets_expiration_timer(): void
    {
        $student = $this->createUserWithRole('student');
        $counselor = $this->createUserWithRole('counselor');
        $session = $this->createSession($student, $counselor, [
            'is_anonymous' => true,
            'status' => 'active',
        ]);
        $ttlHours = max(1, (int) env('ANONYMOUS_SESSION_TTL_HOURS', 24));
        $this->setSessionUpdatedAt($session, now()->subHours(max(1, $ttlHours - 4)));

        $response = $this->actingAs($student)->postJson("/api/sessions/{$session->id}/touch");
        $response->assertOk();

        $session->refresh();
        $this->assertTrue(
            $session->updated_at->greaterThan(now()->subHours($ttlHours)),
            'After touch, session should be recent enough to not expire'
        );

        $response = $this->actingAs($student)->postJson(
            "/api/sessions/{$session->id}/messages",
            [
                'content' => 'This should work',
                'message_type' => 'text',
            ]
        );

        $response->assertStatus(201);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->create([
            'role' => $role,
            'approved' => true,
        ]);

        return $user->fresh();
    }

    private function createSession(User $student, ?User $counselor = null, array $overrides = []): CounselingSession
    {
        return CounselingSession::create(array_merge([
            'student_id' => $student->id,
            'counselor_id' => $counselor?->id,
            'status' => 'active',
            'session_type' => 'chat',
        ], $overrides));
    }

    private function setSessionUpdatedAt(CounselingSession $session, mixed $updatedAt): void
    {
        $session->timestamps = false;
        $session->forceFill(['updated_at' => $updatedAt])->saveQuietly();
        $session->timestamps = true;
        $session->refresh();
    }
}
