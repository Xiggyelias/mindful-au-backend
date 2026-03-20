<?php

namespace Tests\Feature;

use Tests\TestCase;

class OAuthRedirectSecurityTest extends TestCase
{
    /** @test */
    public function production_oauth_redirect_ignores_untrusted_frontend_override(): void
    {
        $previousEnv = (string) config('app.env');
        $previousFrontendUrl = (string) config('app.frontend_url');

        config()->set('app.env', 'production');
        config()->set('app.frontend_url', 'https://app.example.com');

        try {
            $response = $this->get('/api/auth/google?frontend_url=http://127.0.0.1:3000');

            $location = (string) $response->headers->get('Location', '');
            $this->assertStringStartsWith('https://app.example.com/oauth/callback?', $location);
            $this->assertStringNotContainsString('127.0.0.1:3000', $location);
            $this->assertStringContainsString('Google+sign-in+is+not+configured+yet.', $location);
        } finally {
            config()->set('app.env', $previousEnv);
            config()->set('app.frontend_url', $previousFrontendUrl);
        }
    }

    /** @test */
    public function local_oauth_redirect_can_use_loopback_frontend_override(): void
    {
        $previousEnv = (string) config('app.env');
        $previousFrontendUrl = (string) config('app.frontend_url');
        $previousAppUrl = (string) config('app.url');

        config()->set('app.env', 'local');
        config()->set('app.frontend_url', '');
        config()->set('app.url', 'http://127.0.0.1:8000');

        try {
            $response = $this->get('/api/auth/google?frontend_url=http://127.0.0.1:5173');

            $location = (string) $response->headers->get('Location', '');
            $this->assertStringStartsWith('http://127.0.0.1:5173/oauth/callback?', $location);
            $this->assertStringContainsString('Google+sign-in+is+not+configured+yet.', $location);
        } finally {
            config()->set('app.env', $previousEnv);
            config()->set('app.frontend_url', $previousFrontendUrl);
            config()->set('app.url', $previousAppUrl);
        }
    }
}
