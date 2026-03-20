<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use ReflectionClass;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    /** @test */
    public function it_normalizes_the_openrouter_base_url_for_relative_api_requests(): void
    {
        config([
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        ]);

        $service = app(OpenRouterService::class);
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('baseUrl');
        $property->setAccessible(true);

        $this->assertSame('https://openrouter.ai/api/v1/', $property->getValue($service));
    }
}
