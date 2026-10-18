<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LiveKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveKitServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.livekit.url', 'http://localhost:7880');
        Config::set('services.livekit.key', 'devkey');
        Config::set('services.livekit.secret', 'secretsecretsecretsecretsecretsecret32');
    }

    #[Test]
    public function it_normalizes_http_and_https_urls_to_websocket_protocols(): void
    {
        $service = new LiveKitService();

        Config::set('services.livekit.url', 'http://livekit.example.com');
        $this->assertSame('ws://livekit.example.com', $service->url());

        Config::set('services.livekit.url', 'https://livekit.example.com');
        $this->assertSame('wss://livekit.example.com', $service->url());

        Config::set('services.livekit.url', 'ws://localhost:7880');
        $this->assertSame('ws://localhost:7880', $service->url());
    }

    #[Test]
    public function it_mints_a_valid_jwt_join_token(): void
    {
        $service = new LiveKitService();
        $token = $service->mintJoinToken(123, 45, 'student');

        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'A valid JWT must have 3 dot-separated parts.');
    }

    #[Test]
    public function participants_can_obtain_livekit_tokens_for_their_appointments(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        $student = $this->createPortalUser('student', 'student-lk@test.com', 'Student LK');
        $counselor = $this->createPortalUser('counselor', 'counselor-lk@test.com', 'Counselor LK');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/video-calls/livekit-token', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['url', 'token', 'room'])
            ->assertJson([
                'url' => 'ws://localhost:7880',
                'room' => 'appointment-'.$appointment->id,
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function non_participants_cannot_obtain_livekit_tokens(): void
    {
        $student = $this->createPortalUser('student', 'student-lk2@test.com', 'Student LK2');
        $counselor = $this->createPortalUser('counselor', 'counselor-lk2@test.com', 'Counselor LK2');
        $stranger = $this->createPortalUser('student', 'stranger@test.com', 'Stranger');

        $appointment = Appointment::query()->create([
            'student_id' => $student->id,
            'counselor_id' => $counselor->id,
            'scheduled_at' => now()->subMinutes(5),
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($stranger, 'sanctum')->postJson('/api/video-calls/livekit-token', [
            'appointment_id' => $appointment->id,
        ]);

        $response->assertStatus(403);
    }

    private function createPortalUser(string $role, string $email, string $name): User
    {
        $user = User::query()->create([
            'email' => $email,
            'password' => Hash::make('Secret123!'),
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'full_name' => $name,
            'avatar_url' => null,
            'is_anonymous' => false,
        ]);

        return $user;
    }
}
