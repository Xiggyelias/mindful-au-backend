<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MultiDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
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

    /** @test */
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
