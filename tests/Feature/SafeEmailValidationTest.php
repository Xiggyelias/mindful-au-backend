<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SafeEmailValidationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_rejects_crlf_email_input_without_server_error(): void
    {
        $this->createPortalUser('counselor', 'safe-login@test.com', 'Safe Login');

        $response = $this->postJson('/api/login', [
            'email' => "safe-login@test.com\r\nBcc: attacker@example.com",
            'password' => 'SecretPass123!',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function registration_rejects_crlf_email_input(): void
    {
        $response = $this->postJson('/api/register', [
            'email' => "new-counselor@test.com\r\nBcc: attacker@example.com",
            'password' => 'SecretPass123!',
            'full_name' => 'New Counselor',
            'id_number' => 'STAFF-100',
            'role' => 'counselor',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function admin_managed_email_fields_reject_crlf_input(): void
    {
        $admin = $this->createPortalUser('admin', 'admin-safe-email@test.com', 'Admin Safe Email');

        $settingsResponse = $this->actingAs($admin)->putJson('/api/settings', [
            'settings' => [
                'admin_email' => "admin@test.com\r\nBcc: attacker@example.com",
            ],
        ]);

        $settingsResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.admin_email']);

        $institutionResponse = $this->actingAs($admin)->postJson('/api/institution-accounts', [
            'email' => "student@test.com\r\nBcc: attacker@example.com",
            'role' => 'student',
        ]);

        $institutionResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
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
