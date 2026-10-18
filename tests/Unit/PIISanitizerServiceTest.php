<?php

namespace Tests\Unit;

use App\Models\Profile;
use App\Models\User;
use App\Services\PIISanitizerService;
use Tests\TestCase;

class PIISanitizerServiceTest extends TestCase
{
    private PIISanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new PIISanitizerService;
    }

    public function test_it_masks_email_addresses(): void
    {
        $input = 'Please email me at student123@africau.edu or personal.email@gmail.com for updates.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('student123@africau.edu', $result);
        $this->assertStringNotContainsString('personal.email@gmail.com', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_EMAIL, $result);
    }

    public function test_it_masks_phone_numbers_without_masking_years(): void
    {
        $input = 'My phone is +263 77 123 4567, or call 0772987654. In 2024 I had 3 exams and rated stress 8/10.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('+263 77 123 4567', $result);
        $this->assertStringNotContainsString('0772987654', $result);
        $this->assertStringContainsString('2024', $result);
        $this->assertStringContainsString('8/10', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_PHONE, $result);
    }

    public function test_it_masks_student_identification_numbers(): void
    {
        $input = 'My student ID is AU/2023/5678 and matriculation number is MAT-889922.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('AU/2023/5678', $result);
        $this->assertStringNotContainsString('MAT-889922', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_STUDENT_ID, $result);
    }

    public function test_it_masks_campus_hall_and_room_locations(): void
    {
        $input = 'I am currently in Hostel 3 Room 204 and feeling dizzy.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('Hostel 3 Room 204', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_LOCATION, $result);
        $this->assertStringContainsString('feeling dizzy', $result);
    }

    public function test_it_masks_known_user_profile_data(): void
    {
        $user = new User;
        $user->email = 'tendai.chidzero@africau.edu';
        $profile = new Profile([
            'full_name' => 'Tendai Chidzero',
            'id_number' => 'AU202499',
        ]);
        $user->setRelation('profile', $profile);

        $input = 'Hello, Tendai Chidzero here. My matric is AU202499 and email is tendai.chidzero@africau.edu.';
        $result = $this->sanitizer->sanitizeText($input, $user);

        $this->assertStringNotContainsString('Tendai', $result);
        $this->assertStringNotContainsString('Chidzero', $result);
        $this->assertStringNotContainsString('AU202499', $result);
        $this->assertStringNotContainsString('tendai.chidzero@africau.edu', $result);
    }

    public function test_it_preserves_clinical_symptoms_and_emotional_states(): void
    {
        $clinicalText = 'I am extremely depressed, having panic attacks and I feel overwhelmed by loneliness and suicidal thoughts.';
        $result = $this->sanitizer->sanitizeText($clinicalText);

        $this->assertStringContainsString('depressed', $result);
        $this->assertStringContainsString('panic attacks', $result);
        $this->assertStringContainsString('overwhelmed', $result);
        $this->assertStringContainsString('loneliness', $result);
        $this->assertStringContainsString('suicidal thoughts', $result);
        // Ensure "I am extremely depressed" is NOT masked as a person's name
        $this->assertStringNotContainsString('[Student]', $result);
    }

    public function test_it_masks_self_introduction_names_while_preserving_context(): void
    {
        $input = 'Hello, my name is Farai. I am struggling with sleep.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('Farai', $result);
        $this->assertMatchesRegularExpression('/my name is '.preg_quote(PIISanitizerService::TOKEN_STUDENT, '/').'/i', $result);
        $this->assertStringContainsString('struggling with sleep', $result);
    }

    public function test_it_sanitizes_message_arrays(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hi, I am Kudzai. Email me at kudzai@domain.com.'],
            ['role' => 'assistant', 'content' => 'Hello! How are you feeling today?'],
        ];

        $sanitized = $this->sanitizer->sanitizeMessages($messages);

        $this->assertCount(2, $sanitized);
        $this->assertStringNotContainsString('Kudzai', $sanitized[0]['content']);
        $this->assertStringNotContainsString('kudzai@domain.com', $sanitized[0]['content']);
        $this->assertEquals('Hello! How are you feeling today?', $sanitized[1]['content']);
    }

    public function test_it_masks_honorific_names(): void
    {
        $input = 'I spoke to Dr. Sibanda and Professor Johnson about my academic status.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('Dr. Sibanda', $result);
        $this->assertStringNotContainsString('Professor Johnson', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_COUNSELOR, $result);
    }

    public function test_it_masks_financial_card_numbers_and_ip_addresses(): void
    {
        $input = 'Card: 4111 2222 3333 4444 from IP 192.168.1.100.';
        $result = $this->sanitizer->sanitizeText($input);

        $this->assertStringNotContainsString('4111 2222 3333 4444', $result);
        $this->assertStringNotContainsString('192.168.1.100', $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_CARD, $result);
        $this->assertStringContainsString(PIISanitizerService::TOKEN_IP, $result);
    }

    public function test_it_appends_privacy_system_directive(): void
    {
        $prompt = 'You are a wellness guide.';
        $augmented = $this->sanitizer->appendPrivacySystemDirective($prompt);

        $this->assertStringContainsString('CRITICAL PRIVACY DIRECTIVE:', $augmented);
        $this->assertStringContainsString('NEVER ask the student or user for their real name', $augmented);
    }
}
