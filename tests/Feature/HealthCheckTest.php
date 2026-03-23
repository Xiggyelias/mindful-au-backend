<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.kwaipilot.api_key' => null,
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
            'services.openai.api_key' => null,
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
            'services.academic_risk.webhook_secret' => null,
        ]);

        putenv('HEALTH_PROBE_EXTERNAL_AI');
        putenv('HEALTH_EXPOSE_DETAILS');
    }

    /** @test */
    public function health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertJsonStructure([
                'status',
                'service',
                'time',
            ]);
    }

    /** @test */
    public function ready_endpoint_checks_database_and_cache(): void
    {
        $response = $this->getJson('/api/ready');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('components.database', true)
            ->assertJsonPath('components.cache', true)
            ->assertJsonPath('components.queue', true)
            ->assertJsonPath('components.disk', true)
            ->assertJsonPath('components.ai', true)
            ->assertJsonMissingPath('details');
    }

    /** @test */
    public function web_health_alias_returns_readiness_payload(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('components.database', true)
            ->assertJsonPath('components.cache', true)
            ->assertJsonPath('components.queue', true)
            ->assertJsonPath('components.disk', true)
            ->assertJsonPath('components.ai', true)
            ->assertJsonMissingPath('details')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0');

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control', '')
        );
    }

    /** @test */
    public function ready_endpoint_exposes_details_for_authenticated_admin_requests(): void
    {
        $admin = $this->createPortalUser('admin', 'ready-admin@test.com', 'Ready Admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/ready');

        $response
            ->assertStatus(200)
            ->assertJsonPath('details.ai.mode', 'local_fallback')
            ->assertJsonPath('details.ai.external_provider_configured', false)
            ->assertJsonPath('details.ai.local_fallback_available', true)
            ->assertJsonPath('details.integrations.google_oauth.status', 'not_configured')
            ->assertJsonPath('details.integrations.academic_risk_webhook.status', 'not_configured');
    }

    /** @test */
    public function ready_endpoint_reports_external_ai_when_a_provider_is_configured(): void
    {
        putenv('HEALTH_EXPOSE_DETAILS=true');

        config([
            'services.openrouter.api_key' => 'test-openrouter',
        ]);

        $response = $this->getJson('/api/ready');

        $response
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('components.ai', true)
            ->assertJsonPath('details.ai.mode', 'external')
            ->assertJsonPath('details.ai.external_provider_configured', true)
            ->assertJsonPath('details.ai.external_provider_ready', true)
            ->assertJsonPath('details.ai.validation', 'configuration_only')
            ->assertJsonPath('details.ai.configured_providers.0', 'openrouter')
            ->assertJsonPath('details.ai.active_provider', 'openrouter');
    }

    /** @test */
    public function ready_endpoint_reports_fallback_when_a_probed_provider_is_degraded(): void
    {
        putenv('HEALTH_PROBE_EXTERNAL_AI=true');
        putenv('HEALTH_EXPOSE_DETAILS=true');

        config([
            'services.openrouter.api_key' => 'test-openrouter',
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ], 401),
        ]);

        $response = $this->getJson('/api/ready');

        $response
            ->assertStatus(200)
            ->assertJsonPath('details.ai.mode', 'local_fallback')
            ->assertJsonPath('details.ai.external_provider_configured', true)
            ->assertJsonPath('details.ai.external_provider_ready', false)
            ->assertJsonPath('details.ai.providers.openrouter.status', 'degraded')
            ->assertJsonPath('details.ai.providers.openrouter.http_status', 401);
    }

    /** @test */
    public function ready_endpoint_reports_optional_integrations_when_configured(): void
    {
        putenv('HEALTH_EXPOSE_DETAILS=true');

        config([
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
            'services.google.redirect' => 'https://example.com/auth/google/callback',
            'services.academic_risk.webhook_secret' => 'risk-secret',
        ]);

        $response = $this->getJson('/api/ready');

        $response
            ->assertStatus(200)
            ->assertJsonPath('details.integrations.google_oauth.status', 'ready')
            ->assertJsonPath('details.integrations.academic_risk_webhook.status', 'secured');
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
