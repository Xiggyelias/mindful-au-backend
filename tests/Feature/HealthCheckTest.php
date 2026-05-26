<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
        ]);
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
            ->assertJsonPath('details.ai.mode', 'local_fallback')
            ->assertJsonPath('details.ai.external_provider_configured', false);
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
            ->assertJsonPath('details.ai.mode', 'local_fallback');
    }

    /** @test */
    public function ready_endpoint_reports_external_ai_when_a_provider_is_configured(): void
    {
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
            ->assertJsonPath('details.ai.configured_providers.0', 'openrouter');
    }
}
