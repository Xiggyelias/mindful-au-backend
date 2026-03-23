<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpenRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpenRouterStreamTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function openrouter_stream_endpoint_returns_server_sent_events(): void
    {
        $student = $this->createPortalUser('student', 'stream-student@test.com', 'Stream Student');
        Sanctum::actingAs($student);

        $this->mock(OpenRouterService::class, function ($mock): void {
            $mock->shouldReceive('streamMessage')
                ->once()
                ->andReturn((function () {
                    yield ['content' => 'Hel', 'done' => false];
                    yield ['content' => 'lo', 'done' => false];
                    yield ['content' => '', 'done' => true, 'conversation_id' => 321];
                })());
        });

        $response = $this->postJson('/api/openrouter/stream', [
            'messages' => [
                ['role' => 'user', 'content' => 'hello'],
            ],
            'model' => 'nvidia/nemotron-nano-9b-v2:free',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'text/event-stream',
            (string) $response->headers->get('Content-Type', '')
        );
        $this->assertSame('no', (string) $response->headers->get('X-Accel-Buffering', ''));

        $streamedContent = $response->streamedContent();
        $this->assertStringContainsString('"content":"Hel"', $streamedContent);
        $this->assertStringContainsString('"content":"lo"', $streamedContent);
        $this->assertStringContainsString('"conversation_id":321', $streamedContent);
        $this->assertStringContainsString('"done":true', $streamedContent);
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
