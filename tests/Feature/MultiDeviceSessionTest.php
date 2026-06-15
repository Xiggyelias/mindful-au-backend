<?php

namespace Tests\Feature;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MultiDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_stay_signed_in_on_multiple_devices_and_log_out_other_sessions(): void
    {
        $user = $this->createPortalUser('counselor', 'multi-device@test.com', 'Multi Device Counselor');

        $deviceA = ['X-Device-ID' => 'device-a', 'X-Device-Name' => 'Chrome on Mac'];
        $deviceB = ['X-Device-ID' => 'device-b', 'X-Device-Name' => 'Safari on iPhone'];

        $loginA = $this->withHeaders($deviceA)->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ]);
        $loginA->assertStatus(200);
        $tokenA = (string) $loginA->json('access_token');

        $loginB = $this->withHeaders($deviceB)->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ]);
        $loginB->assertStatus(200);
        $tokenB = (string) $loginB->json('access_token');

        $this->assertNotSame($tokenA, $tokenB);

        $this->tokenRequest('GET', '/api/me', $tokenA, $deviceA)->assertStatus(200);
        $this->tokenRequest('GET', '/api/me', $tokenB, $deviceB)->assertStatus(200);

        $sessionsResponse = $this->tokenRequest('GET', '/api/auth/sessions', $tokenA, $deviceA);

        $sessionsResponse->assertStatus(200);
        $this->assertCount(2, $sessionsResponse->json('sessions'));

        $logoutOthers = $this->tokenRequest('POST', '/api/auth/sessions/logout-others', $tokenA, $deviceA);

        $logoutOthers
            ->assertStatus(200)
            ->assertJsonPath('deleted_count', 1);

        $this->tokenRequest('GET', '/api/me', $tokenA, $deviceA)->assertStatus(200);
        $this->tokenRequest('GET', '/api/me', $tokenB, $deviceB)->assertStatus(401);
    }

    #[Test]
    public function refresh_rotates_only_the_current_device_session(): void
    {
        $user = $this->createPortalUser('counselor', 'refresh-device@test.com', 'Refresh Device Counselor');

        $deviceA = ['X-Device-ID' => 'device-a-refresh', 'X-Device-Name' => 'Chrome on Windows'];
        $deviceB = ['X-Device-ID' => 'device-b-refresh', 'X-Device-Name' => 'Edge on Android'];

        $tokenA = (string) $this->withHeaders($deviceA)->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ])->json('access_token');

        $tokenB = (string) $this->withHeaders($deviceB)->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ])->json('access_token');

        $refresh = $this->tokenRequest('POST', '/api/refresh', $tokenA, $deviceA);

        $refresh->assertStatus(200);
        $replacementToken = (string) $refresh->json('access_token');

        $this->assertNotSame($tokenA, $replacementToken);

        $this->tokenRequest('GET', '/api/me', $tokenA, $deviceA)->assertStatus(401);
        $this->tokenRequest('GET', '/api/me', $replacementToken, $deviceA)->assertStatus(200);
        $this->tokenRequest('GET', '/api/me', $tokenB, $deviceB)->assertStatus(200);
    }

    #[Test]
    public function mobile_login_tolerates_long_device_metadata_headers(): void
    {
        $user = $this->createPortalUser('counselor', 'mobile-login@test.com', 'Mobile Login Counselor');

        $deviceId = str_repeat('mobile-device-', 20);
        $deviceName = 'Mobile Safari '.str_repeat('iPhone ', 40);
        $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) '
            .str_repeat('MobileSafari/very-long-agent ', 100);

        $response = $this->withHeaders([
            'X-Device-ID' => $deviceId,
            'X-Device-Name' => $deviceName,
            'User-Agent' => $userAgent,
        ])->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'SecretPass123!',
        ]);

        $response->assertStatus(200);

        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($token);
        $this->assertLessThanOrEqual(191, strlen((string) $token->device_id));
        $this->assertLessThanOrEqual(120, strlen((string) $token->device_name));
        $this->assertLessThanOrEqual(2000, strlen((string) $token->user_agent));
        $this->assertSame(substr($deviceId, 0, 191), (string) $token->device_id);
        $this->assertSame(substr($deviceName, 0, 120), (string) $token->device_name);
    }

    /**
     * @param  array<string, string>  $deviceHeaders
     * @return array<string, string>
     */
    private function authHeaders(string $token, array $deviceHeaders): array
    {
        return array_merge($deviceHeaders, [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @param  array<string, string>  $deviceHeaders
     */
    private function tokenRequest(string $method, string $uri, string $token, array $deviceHeaders)
    {
        $this->app['auth']->forgetGuards();

        return match (strtoupper($method)) {
            'POST' => $this->withHeaders($this->authHeaders($token, $deviceHeaders))->postJson($uri),
            default => $this->withHeaders($this->authHeaders($token, $deviceHeaders))->getJson($uri),
        };
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
