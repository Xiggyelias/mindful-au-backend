<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PIISanitizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PIISanitizationChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_wellness_chat_masks_pii_in_outbound_model_payload_while_preserving_local_records(): void
    {
        Http::fake([
            'https://openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello. I hear that you are going through a difficult exam period. Take a deep breath.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.openrouter.api_key' => 'test-openrouter-key',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        ]);

        $user = User::factory()->create([
            'email' => 'panashe.mutasa@africau.edu',
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => 'student',
            'approved' => true,
        ]);

        Profile::create([
            'user_id' => $user->id,
            'full_name' => 'Panashe Mutasa',
            'id_number' => 'AU2024881',
        ]);

        $rawMessage = 'Hi, my name is Panashe Mutasa, email panashe.mutasa@africau.edu, phone +263 77 999 1122, student ID AU2024881 in Hostel 4 Room 102. I am extremely overwhelmed with exams and having panic attacks.';

        $response = $this->actingAs($user)->postJson('/api/ai/wellness-chat', [
            'message' => $rawMessage,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'response',
            'conversation_id',
            'user_message_id',
            'assistant_message_id',
        ]);

        // 1. Verify local database stored the authentic message for clinical counseling records
        $savedMessage = ChatMessage::where('role', 'user')->latest('id')->first();
        $this->assertNotNull($savedMessage);
        $this->assertStringContainsString('Panashe Mutasa', $savedMessage->content);
        $this->assertStringContainsString('AU2024881', $savedMessage->content);

        // 2. Verify outbound HTTP payload to OpenRouter was sanitized and contains NO PII
        Http::assertSent(function ($request) {
            $body = json_decode((string) $request->body(), true);
            $messages = $body['messages'] ?? [];

            $allContent = implode(' ', array_column($messages, 'content'));

            // Must NOT contain personal identifying information
            $hasRealName = str_contains($allContent, 'Panashe Mutasa');
            $hasEmail = str_contains($allContent, 'panashe.mutasa@africau.edu');
            $hasPhone = str_contains($allContent, '+263 77 999 1122');
            $hasMatric = str_contains($allContent, 'AU2024881');
            $hasHostel = str_contains($allContent, 'Hostel 4 Room 102');

            // Must contain masked tokens
            $hasMaskedEmail = str_contains($allContent, PIISanitizerService::TOKEN_EMAIL);
            $hasMaskedStudent = str_contains($allContent, PIISanitizerService::TOKEN_STUDENT);
            $hasMaskedPhone = str_contains($allContent, PIISanitizerService::TOKEN_PHONE);
            $hasMaskedLocation = str_contains($allContent, PIISanitizerService::TOKEN_LOCATION);

            // Must preserve clinical distress keywords
            $hasDepression = str_contains($allContent, 'overwhelmed with exams');
            $hasPanic = str_contains($allContent, 'having panic attacks');

            return ! $hasRealName
                && ! $hasEmail
                && ! $hasPhone
                && ! $hasMatric
                && ! $hasHostel
                && $hasMaskedEmail
                && $hasMaskedStudent
                && $hasMaskedPhone
                && $hasMaskedLocation
                && $hasDepression
                && $hasPanic;
        });
    }
}
