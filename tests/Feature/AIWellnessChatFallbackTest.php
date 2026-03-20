<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AIWellnessChatFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function greeting_messages_get_a_conversational_fallback_response(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        config([
            'services.kwaipilot.api_key' => null,
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
            'services.openai.api_key' => null,
        ]);

        $student = $this->createPortalUser('student', 'ai-fallback-student@test.com', 'AI Fallback Student');

        $response = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'hello',
        ]);

        $response->assertOk();

        $assistantText = (string) $response->json('response');
        $normalizedAssistantText = strtolower($assistantText);

        $response
            ->assertJsonPath('provider_mode', 'local_fallback')
            ->assertJsonPath('provider_name', 'offline_companion')
            ->assertJsonPath('external_ai_configured', false);

        $this->assertStringContainsString('hi', $normalizedAssistantText);
        $this->assertStringContainsString('how has your day been so far', $normalizedAssistantText);
        $this->assertStringNotContainsString('name the main pressure', $normalizedAssistantText);
        $this->assertDatabaseHas('chat_messages', [
            'role' => 'assistant',
            'content' => $assistantText,
        ]);
    }

    /** @test */
    public function follow_up_messages_use_conversation_context_in_fallback_mode(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        config([
            'services.kwaipilot.api_key' => null,
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
            'services.openai.api_key' => null,
        ]);

        $student = $this->createPortalUser('student', 'ai-follow-up-student@test.com', 'AI Follow Up Student');

        $firstResponse = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'I am anxious about my exams',
        ]);

        $firstResponse->assertOk();
        $conversationId = (int) $firstResponse->json('conversation_id');

        $secondResponse = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'what should i do first?',
            'conversation_id' => $conversationId,
        ]);

        $secondResponse->assertOk();

        $assistantText = strtolower((string) $secondResponse->json('response'));

        $this->assertStringContainsString('step by step', $assistantText);
        $this->assertStringContainsString('breathing', $assistantText);
        $this->assertStringContainsString('10 to 15 minute task', $assistantText);
        $this->assertStringNotContainsString('tell me a little more', $assistantText);
    }

    /** @test */
    public function casual_check_in_replies_stay_conversational_instead_of_jump_to_old_advice(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        config([
            'services.kwaipilot.api_key' => null,
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
            'services.openai.api_key' => null,
        ]);

        $student = $this->createPortalUser('student', 'ai-social-student@test.com', 'AI Social Student');

        $firstResponse = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'hi',
        ]);

        $firstResponse->assertOk();
        $conversationId = (int) $firstResponse->json('conversation_id');

        $secondResponse = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'am good u',
            'conversation_id' => $conversationId,
        ]);

        $secondResponse->assertOk();

        $assistantText = strtolower((string) $secondResponse->json('response'));

        $this->assertStringContainsString('glad to hear you are doing okay', $assistantText);
        $this->assertStringContainsString('what has been on your mind today', $assistantText);
        $this->assertStringNotContainsString('academic pressure can feel intense', $assistantText);
    }

    /** @test */
    public function physical_health_messages_receive_health_specific_support(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );

        config([
            'services.kwaipilot.api_key' => null,
            'services.openrouter.api_key' => null,
            'services.gemini.api_key' => null,
            'services.openai.api_key' => null,
        ]);

        $student = $this->createPortalUser('student', 'ai-health-student@test.com', 'AI Health Student');

        $response = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'am sick',
        ]);

        $response->assertOk();

        $assistantText = strtolower((string) $response->json('response'));

        $this->assertStringContainsString('feeling sick', $assistantText);
        $this->assertStringContainsString('rest', $assistantText);
        $this->assertStringContainsString('what symptoms are bothering you most', $assistantText);
        $this->assertStringNotContainsString('i am listening', $assistantText);
    }

    /** @test */
    public function crisis_language_is_caught_before_any_provider_call_and_returns_immediate_help_guidance(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => false]
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'crisis_hotline'],
            ['value' => '+263 000 000 000']
        );

        config([
            'services.kwaipilot.api_key' => 'test-kwaipilot',
            'services.openrouter.api_key' => 'test-openrouter',
            'services.gemini.api_key' => 'test-gemini',
            'services.openai.api_key' => 'test-openai',
        ]);

        Http::fake();

        $student = $this->createPortalUser('student', 'ai-crisis-student@test.com', 'AI Crisis Student');

        $response = $this->actingAs($student)->postJson('/api/ai/wellness-chat', [
            'message' => 'I want to jump off a building',
        ]);

        $response->assertOk()
            ->assertJson([
                'risk_level' => 'crisis',
                'requires_immediate_help' => true,
                'show_panic_button' => true,
                'crisis_hotline' => '+263 000 000 000',
                'provider_mode' => 'safety_guardrail',
                'provider_name' => 'crisis_guardrail',
                'external_ai_configured' => true,
            ]);

        $assistantText = strtolower((string) $response->json('response'));

        $this->assertStringContainsString('immediate danger', $assistantText);
        $this->assertStringContainsString('move away from the edge', $assistantText);
        $this->assertStringContainsString('crisis contact', $assistantText);
        $this->assertStringNotContainsString('academic pressure can feel intense', $assistantText);

        Http::assertNothingSent();
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
