<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserTwoFactorMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CounselorTwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function counselor_login_reports_two_factor_required_when_enabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => true]
        );

        $user = User::factory()->create([
            'email' => 'counselor-2fa@test.com',
            'password' => Hash::make('SecretPass123!'),
        ]);
        $user->roles()->create([
            'role' => 'counselor',
            'approved' => true,
        ]);

        UserTwoFactorMethod::query()->create([
            'user_id' => $user->id,
            'method' => 'totp',
            'secret_encrypted' => 'encrypted-placeholder',
            'verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'counselor-2fa@test.com',
            'password' => 'SecretPass123!',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'two_factor_enabled' => true,
                'two_factor_required' => true,
                'two_factor_setup_required' => false,
                'two_factor_verified' => true,
            ]);

        $this->assertNotEmpty($response->json('access_token'));
    }

    #[Test]
    public function counselor_login_reports_setup_required_when_two_factor_not_configured(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'two_factor_auth'],
            ['value' => true]
        );

        $user = User::factory()->create([
            'email' => 'counselor-setup@test.com',
            'password' => Hash::make('SecretPass123!'),
        ]);
        $user->roles()->create([
            'role' => 'counselor',
            'approved' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'counselor-setup@test.com',
            'password' => 'SecretPass123!',
        ]);

        $response
            ->assertStatus(200)
            ->assertJson([
                'two_factor_enabled' => true,
                'two_factor_required' => true,
                'two_factor_setup_required' => true,
            ]);
    }
}
