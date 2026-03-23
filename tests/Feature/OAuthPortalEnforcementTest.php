<?php

namespace Tests\Feature;

use App\Models\InstitutionAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthPortalEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.frontend_url', 'https://mindful.africau.co.zw');
        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect', 'https://api.example.com/api/auth/google/callback');
    }

    /** @test */
    public function student_portal_rejects_google_accounts_resolved_to_counselor_roles(): void
    {
        InstitutionAccount::query()->create([
            'email' => 'staff.member@africau.edu',
            'role' => 'counselor',
            'approved' => true,
            'is_active' => true,
            'full_name' => 'Staff Member',
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($this->fakeGoogleUser('staff.member@africau.edu', 'Staff Member'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this
            ->withSession([
                'oauth:frontend:url' => 'https://mindful.africau.co.zw',
                'oauth:portal' => 'student',
            ])
            ->get('/api/auth/google/callback?code=test-code');

        $location = (string) $response->headers->get('Location', '');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://mindful.africau.co.zw/oauth/callback?', $location);
        $this->assertSame('student', $query['portal'] ?? null);
        $this->assertSame(
            'This Google account is not authorized for the Student portal. Use the Counselor portal instead.',
            $query['error'] ?? null
        );
        $this->assertDatabaseMissing('users', ['email' => 'staff.member@africau.edu']);
    }

    /** @test */
    public function student_portal_accepts_google_accounts_resolved_to_student_role(): void
    {
        InstitutionAccount::query()->create([
            'email' => 'student.one@africau.edu',
            'role' => 'student',
            'approved' => true,
            'is_active' => true,
            'full_name' => 'Student One',
        ]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($this->fakeGoogleUser('student.one@africau.edu', 'Student One'));

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $response = $this
            ->withSession([
                'oauth:frontend:url' => 'https://mindful.africau.co.zw',
                'oauth:portal' => 'student',
            ])
            ->get('/api/auth/google/callback?code=test-code');

        $location = (string) $response->headers->get('Location', '');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://mindful.africau.co.zw/oauth/callback?', $location);
        $this->assertSame('student', $query['portal'] ?? null);
        $this->assertNotEmpty($query['ticket'] ?? null);
        $this->assertDatabaseHas('users', ['email' => 'student.one@africau.edu']);
        $this->assertDatabaseHas('user_roles', [
            'role' => 'student',
            'approved' => true,
        ]);
    }

    private function fakeGoogleUser(string $email, string $name): SocialiteUser
    {
        return (new SocialiteUser())
            ->map([
                'id' => 'google-user-123',
                'email' => $email,
                'name' => $name,
                'avatar' => 'https://example.com/avatar.png',
            ])
            ->setRaw([
                'email_verified' => true,
            ]);
    }
}
