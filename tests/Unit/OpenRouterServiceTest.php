<?php

namespace Tests\Unit;

use App\Services\OpenRouterService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    #[Test]
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

    #[Test]
    public function it_uses_the_configured_openrouter_model_roles(): void
    {
        config([
            'services.openrouter.chat_model' => 'meta-llama/llama-3.3-70b-instruct:free',
            'services.openrouter.core_model' => 'qwen/qwen3-next-80b-a3b-thinking',
            'services.openrouter.heavy_analysis_model' => 'deepseek/deepseek-v4-pro',
            'services.openrouter.speed_model' => 'liquid/lfm-2.5-1.2b-thinking:free',
        ]);

        $this->assertSame('meta-llama/llama-3.3-70b-instruct:free', OpenRouterService::configuredChatModel());
        $this->assertSame('qwen/qwen3-next-80b-a3b-thinking', OpenRouterService::configuredCoreModel());
        $this->assertSame('deepseek/deepseek-v4-pro', OpenRouterService::configuredHeavyAnalysisModel());
        $this->assertSame('liquid/lfm-2.5-1.2b-thinking:free', OpenRouterService::configuredSpeedModel());
    }

    #[Test]
    public function it_replaces_legacy_openai_chat_model_requests_with_llama(): void
    {
        config([
            'services.openrouter.chat_model' => 'meta-llama/llama-3.3-70b-instruct:free',
        ]);

        $this->assertSame(
            'meta-llama/llama-3.3-70b-instruct:free',
            OpenRouterService::resolveChatModel('openai/gpt-4o')
        );

        $this->assertSame(
            'meta-llama/llama-3.3-70b-instruct:free',
            OpenRouterService::resolveChatModel('gpt-4o-mini')
        );
    }

    #[Test]
    public function it_ignores_legacy_openai_model_values_in_configuration(): void
    {
        config([
            'services.openrouter.chat_model' => 'openai/gpt-4o',
            'services.openrouter.core_model' => 'gpt-4o-mini',
        ]);

        $this->assertSame('meta-llama/llama-3.3-70b-instruct:free', OpenRouterService::configuredChatModel());
        $this->assertSame('qwen/qwen3-next-80b-a3b-thinking', OpenRouterService::configuredCoreModel());
    }
}
