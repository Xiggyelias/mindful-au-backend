<?php

namespace App\Http\Controllers;

use App\Models\InstitutionAccount;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\Profile;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    private const OAUTH_TICKET_CACHE_PREFIX = 'oauth:ticket:';
    private const OAUTH_FRONTEND_URL_SESSION_KEY = 'oauth:frontend:url';
    private const OAUTH_PORTAL_SESSION_KEY = 'oauth:portal';

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $this->rememberFrontendUrl($request);
        $this->rememberRequestedPortal($request);

        $requestedPortal = $this->requestedPortal($request);

        if (!$this->isGoogleOAuthConfigured()) {
            return $this->redirectToFrontendWithError(
                'Google sign-in is not configured yet. Please contact support.',
                $requestedPortal
            );
        }

        try {
            $url = Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->with([
                    // Hint Google account chooser to institutional domain.
                    'hd' => $this->primaryAllowedDomain(),
                    'prompt' => 'select_account',
                ])
                ->redirect()
                ->getTargetUrl();

            return redirect()->away($url);
        } catch (Throwable $e) {
            Log::error('Google OAuth redirect bootstrap failed', [
                'error' => $e->getMessage(),
                'redirect_url' => $this->googleRedirectUrl(),
            ]);

            return $this->redirectToFrontendWithError(
                'Google sign-in is temporarily unavailable. Please try again.',
                $requestedPortal
            );
        }
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $requestedPortal = $this->requestedPortal($request, true);

        if (!$this->isGoogleOAuthConfigured()) {
            return $this->redirectToFrontendWithError(
                'Google sign-in is not configured yet. Please contact support.',
                $requestedPortal
            );
        }

        try {
            $providerError = Str::lower(trim((string) $request->query('error', '')));
            if ($providerError !== '') {
                $providerDescription = trim((string) $request->query('error_description', ''));
                $this->recordGoogleLogin(
                    request: $request,
                    user: null,
                    email: null,
                    success: false,
                    failureReason: 'provider_error:' . $providerError
                );

                return $this->redirectToFrontendWithError(
                    $this->mapProviderCallbackError($providerError, $providerDescription),
                    $requestedPortal
                );
            }

            if (!$request->filled('code')) {
                $this->recordGoogleLogin($request, null, null, false, 'missing_authorization_code');
                return $this->redirectToFrontendWithError(
                    'Google sign-in did not return a valid authorization code. Please try again.',
                    $requestedPortal
                );
            }

            $googleUser = Socialite::driver('google')->user();

            $email = Str::lower(trim((string) ($googleUser->getEmail() ?? '')));
            if ($email === '') {
                $this->recordGoogleLogin($request, null, null, false, 'missing_email');
                return $this->redirectToFrontendWithError(
                    'Google account did not provide an email address.',
                    $requestedPortal
                );
            }

            if (!$this->isAllowedInstitutionEmail($email)) {
                $this->recordGoogleLogin($request, null, $email, false, 'non_institution_email');
                return $this->redirectToFrontendWithError(
                    'Only official institutional accounts are allowed.',
                    $requestedPortal
                );
            }

            $emailVerified = (bool) data_get($googleUser->user, 'email_verified', false);
            if (!$emailVerified) {
                $this->recordGoogleLogin($request, null, $email, false, 'email_not_verified');
                return $this->redirectToFrontendWithError(
                    'Your Google account email is not verified.',
                    $requestedPortal
                );
            }

            $resolved = $this->resolveRoleForEmail($email);
            if (!$resolved['role']) {
                $this->recordGoogleLogin($request, null, $email, false, 'role_not_authorized');
                return $this->redirectToFrontendWithError(
                    'Your account is not authorized for this platform. Contact the system administrator.',
                    $requestedPortal
                );
            }

            if ($resolved['role'] === 'admin') {
                $this->recordGoogleLogin($request, null, $email, false, 'admin_must_use_password_portal');
                return $this->redirectToFrontendWithError(
                    'Administrators must sign in from the Admin portal using email and password.',
                    $requestedPortal
                );
            }

            if (!$this->isRoleAllowedForPortal($resolved['role'], $requestedPortal)) {
                $this->recordGoogleLogin(
                    request: $request,
                    user: null,
                    email: $email,
                    success: false,
                    failureReason: 'portal_mismatch:' . ($requestedPortal ?? 'unknown') . ':' . $resolved['role']
                );

                return $this->redirectToFrontendWithError(
                    $this->portalMismatchMessage($requestedPortal, $resolved['role']),
                    $requestedPortal
                );
            }

            if (!$resolved['approved']) {
                $this->recordGoogleLogin($request, null, $email, false, 'account_pending_approval');
                return $this->redirectToFrontendWithError(
                    'Your account exists but is pending approval.',
                    $requestedPortal
                );
            }

            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    // Random value for schema compatibility; Google passwords are never stored.
                    'password' => Hash::make(Str::random(64)),
                    'last_seen_at' => now(),
                ]
            );

            $profile = Profile::query()->firstOrNew(['user_id' => $user->id]);
            if (($resolved['full_name'] ?? null) !== null) {
                $profile->full_name = (string) $resolved['full_name'];
            } elseif (!$profile->full_name) {
                $profile->full_name = (string) ($googleUser->getName() ?: Str::before($email, '@'));
            }

            if (($resolved['id_number'] ?? null) !== null) {
                $profile->id_number = (string) $resolved['id_number'];
            }

            if ($googleUser->getAvatar()) {
                $profile->avatar_url = (string) $googleUser->getAvatar();
            }
            $profile->save();

            UserRole::query()->updateOrCreate(
                ['user_id' => $user->id, 'role' => $resolved['role']],
                ['approved' => true]
            );

            if (($resolved['source'] ?? null) === 'institution_accounts') {
                UserRole::query()
                    ->where('user_id', $user->id)
                    ->where('role', '!=', $resolved['role'])
                    ->delete();
            }

            $user->forceFill(['last_seen_at' => now()])->saveQuietly();

            // Rotate existing API tokens on new OAuth session.
            $user->tokens()->delete();
            $token = $user->createToken('google_oauth')->plainTextToken;
            $loginTicket = $this->issueLoginTicket($token);

            $this->recordGoogleLogin($request, $user, $email, true, null);

            return $this->redirectToFrontendWithLoginTicket($loginTicket, $requestedPortal);
        } catch (Throwable $e) {
            Log::error('OAuth authentication failed', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'query' => $request->except(['code']),
            ]);

            $this->recordGoogleLogin($request, null, null, false, 'oauth_exception');

            return $this->redirectToFrontendWithError(
                $this->resolveOAuthExceptionMessage($e),
                $requestedPortal
            );
        }
    }

    public function exchangeLoginTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket' => 'required|string|min:32|max:255',
        ]);

        $ticket = trim((string) $validated['ticket']);
        if ($ticket === '') {
            return response()->json([
                'message' => 'Invalid OAuth login ticket.',
            ], 422);
        }

        $cacheKey = $this->oauthTicketCacheKey($ticket);
        $payload = Cache::pull($cacheKey);
        if (!is_array($payload)) {
            return response()->json([
                'message' => 'OAuth login ticket is invalid or expired.',
            ], 422);
        }

        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            return response()->json([
                'message' => 'OAuth login ticket is invalid or expired.',
            ], 422);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpirySeconds(),
        ]);
    }

    /**
     * Resolve application role from institutional records first, then existing approved user roles.
     *
     * @return array{
     *   role: 'admin'|'counselor'|'peer_counselor'|'student'|null,
     *   approved: bool,
     *   full_name: string|null,
     *   id_number: string|null,
     *   source: 'institution_accounts'|'existing_user_roles'|'auto_student'
     * }
     */
    private function resolveRoleForEmail(string $email): array
    {
        $record = null;
        try {
            if (Schema::hasTable('institution_accounts')) {
                $record = InstitutionAccount::query()
                    ->where('email', $email)
                    ->where('is_active', true)
                    ->first();
            }
        } catch (\Throwable $e) {
            Log::warning('Institution account lookup skipped during OAuth role resolution', [
                'error' => $e->getMessage(),
            ]);
        }

        if ($record) {
            $mappedRole = $this->normalizeRole($record->role);

            return [
                'role' => $mappedRole,
                'approved' => (bool) $record->approved,
                'full_name' => $record->full_name,
                'id_number' => $record->id_number,
                'source' => 'institution_accounts',
            ];
        }

        $existingUser = User::query()
            ->with('roles')
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            if ($existingUser->roles->contains(fn (UserRole $role) => $this->normalizeRole((string) $role->role) === 'admin')) {
                return ['role' => 'admin', 'approved' => true, 'full_name' => null, 'id_number' => null, 'source' => 'existing_user_roles'];
            }
            if (
                $existingUser->roles->contains(
                    fn (UserRole $role) => $this->normalizeRole((string) $role->role) === 'peer_counselor' && $role->approved
                )
            ) {
                return ['role' => 'peer_counselor', 'approved' => true, 'full_name' => null, 'id_number' => null, 'source' => 'existing_user_roles'];
            }
            if (
                $existingUser->roles->contains(
                    fn (UserRole $role) => $this->normalizeRole((string) $role->role) === 'counselor' && $role->approved
                )
            ) {
                return ['role' => 'counselor', 'approved' => true, 'full_name' => null, 'id_number' => null, 'source' => 'existing_user_roles'];
            }
            if (
                $existingUser->roles->contains(
                    fn (UserRole $role) => $this->normalizeRole((string) $role->role) === 'student' && $role->approved
                )
            ) {
                return ['role' => 'student', 'approved' => true, 'full_name' => null, 'id_number' => null, 'source' => 'existing_user_roles'];
            }
        }

        if ($this->autoProvisionStudents()) {
            return ['role' => 'student', 'approved' => true, 'full_name' => null, 'id_number' => null, 'source' => 'auto_student'];
        }

        return ['role' => null, 'approved' => false, 'full_name' => null, 'id_number' => null, 'source' => 'existing_user_roles'];
    }

    private function normalizeRole(string $role): ?string
    {
        $normalized = Str::lower(trim($role));

        return match ($normalized) {
            'admin' => 'admin',
            'student' => 'student',
            'peer_counselor', 'peercounselor', 'peer-counselor', 'peer counselor' => 'peer_counselor',
            'counselor', 'counsellor' => 'counselor',
            // Staff is mapped to counselor portal features by default.
            'staff' => 'counselor',
            default => null,
        };
    }

    private function autoProvisionStudents(): bool
    {
        return filter_var(env('AUTH_AUTO_PROVISION_STUDENTS', true), FILTER_VALIDATE_BOOL);
    }

    private function isAllowedInstitutionEmail(string $email): bool
    {
        $domain = Str::after($email, '@');
        if ($domain === '') {
            return false;
        }

        return in_array(Str::lower($domain), $this->allowedDomains(), true);
    }

    /**
     * @return array<int, string>
     */
    private function allowedDomains(): array
    {
        $raw = (string) env('INSTITUTION_EMAIL_DOMAINS', 'africau.edu');

        return collect(explode(',', $raw))
            ->map(fn (string $domain) => Str::lower(trim($domain)))
            ->filter(fn (string $domain) => $domain !== '')
            ->values()
            ->all();
    }

    private function primaryAllowedDomain(): string
    {
        $domains = $this->allowedDomains();
        return $domains[0] ?? 'africau.edu';
    }

    private function frontendBaseUrl(): string
    {
        $request = request();
        if ($request instanceof Request && $request->hasSession()) {
            $sessionFrontendUrl = $this->normalizeHttpUrl(
                (string) $request->session()->get(self::OAUTH_FRONTEND_URL_SESSION_KEY, '')
            );
            if ($sessionFrontendUrl !== null) {
                return $sessionFrontendUrl;
            }
        }

        $normalizedFrontendUrl = $this->normalizeHttpUrl((string) config('app.frontend_url', ''));
        if ($normalizedFrontendUrl !== null) {
            return $normalizedFrontendUrl;
        }

        if ($request instanceof Request) {
            $requestFrontendUrl = $this->resolveTrustedFrontendUrl($request);
            if ($requestFrontendUrl !== null) {
                return $requestFrontendUrl;
            }
        }

        $appUrl = $this->normalizeHttpUrl((string) config('app.url', ''));
        if ($appUrl !== null) {
            return $appUrl;
        }

        return 'http://127.0.0.1:5173';
    }

    private function rememberRequestedPortal(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $portal = $this->normalizePortal((string) $request->query('portal', ''));
        if ($portal === null) {
            $request->session()->forget(self::OAUTH_PORTAL_SESSION_KEY);
            return;
        }

        $request->session()->put(self::OAUTH_PORTAL_SESSION_KEY, $portal);
    }

    private function requestedPortal(Request $request, bool $forgetFromSession = false): ?string
    {
        $queryPortal = $this->normalizePortal((string) $request->query('portal', ''));
        if ($queryPortal !== null) {
            return $queryPortal;
        }

        if (!$request->hasSession()) {
            return null;
        }

        $storedPortal = $forgetFromSession
            ? $request->session()->pull(self::OAUTH_PORTAL_SESSION_KEY, '')
            : $request->session()->get(self::OAUTH_PORTAL_SESSION_KEY, '');

        return $this->normalizePortal((string) $storedPortal);
    }

    private function rememberFrontendUrl(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $frontendUrl = $this->resolveTrustedFrontendUrl($request);
        if ($frontendUrl === null) {
            return;
        }

        $request->session()->put(self::OAUTH_FRONTEND_URL_SESSION_KEY, $frontendUrl);
    }

    private function resolveTrustedFrontendUrl(Request $request): ?string
    {
        $candidates = [
            (string) $request->query('frontend_url', ''),
            (string) $request->headers->get('origin', ''),
            (string) ($this->originFromUrl((string) $request->headers->get('referer', '')) ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeHttpUrl($candidate);
            if ($normalized === null) {
                continue;
            }

            if ($this->isTrustedFrontendUrl($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    private function isTrustedFrontendUrl(string $url): bool
    {
        $configuredFrontendUrls = array_filter([
            $this->normalizeHttpUrl((string) config('app.frontend_url', '')),
        ]);

        if (in_array($url, $configuredFrontendUrls, true)) {
            return true;
        }

        $appEnv = Str::lower((string) config('app.env', env('APP_ENV', 'production')));
        if (!in_array($appEnv, ['local', 'testing'], true)) {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    private function normalizeHttpUrl(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $normalized = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $normalized .= ':' . (int) $parts['port'];
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($path !== '') {
            $normalized .= $path;
        }

        return $normalized;
    }

    private function originFromUrl(string $value): ?string
    {
        $normalized = $this->normalizeHttpUrl($value);
        if ($normalized === null) {
            return null;
        }

        $parts = parse_url($normalized);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = (string) ($parts['scheme'] ?? '');
        $host = (string) ($parts['host'] ?? '');
        if ($scheme === '' || $host === '') {
            return null;
        }

        $origin = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }

    private function normalizePortal(string $portal): ?string
    {
        $normalized = Str::lower(trim($portal));

        return match ($normalized) {
            'student' => 'student',
            'counselor', 'counsellor' => 'counselor',
            'admin' => 'admin',
            default => null,
        };
    }

    private function isRoleAllowedForPortal(string $resolvedRole, ?string $requestedPortal): bool
    {
        if ($requestedPortal === null) {
            return true;
        }

        return match ($requestedPortal) {
            'student' => $resolvedRole === 'student',
            'counselor' => in_array($resolvedRole, ['counselor', 'peer_counselor'], true),
            'admin' => $resolvedRole === 'admin',
            default => true,
        };
    }

    private function portalMismatchMessage(?string $requestedPortal, string $resolvedRole): string
    {
        $requestedPortalLabel = match ($requestedPortal) {
            'student' => 'Student portal',
            'counselor' => 'Counselor portal',
            'admin' => 'Admin portal',
            default => 'selected portal',
        };

        $resolvedPortalLabel = match ($resolvedRole) {
            'student' => 'Student portal',
            'counselor', 'peer_counselor' => 'Counselor portal',
            'admin' => 'Admin portal',
            default => 'correct portal',
        };

        return "This Google account is not authorized for the {$requestedPortalLabel}. Use the {$resolvedPortalLabel} instead.";
    }

    private function isGoogleOAuthConfigured(): bool
    {
        return $this->googleConfigValue('client_id') !== ''
            && $this->googleConfigValue('client_secret') !== ''
            && $this->googleRedirectUrl() !== '';
    }

    private function googleConfigValue(string $key): string
    {
        $configured = trim((string) config("services.google.$key", ''));
        if ($configured !== '') {
            return $configured;
        }

        return match ($key) {
            'client_id' => trim((string) env('GOOGLE_CLIENT_ID', '')),
            'client_secret' => trim((string) env('GOOGLE_CLIENT_SECRET', '')),
            'redirect' => trim((string) env('GOOGLE_REDIRECT_URL', '')),
            default => '',
        };
    }

    private function googleRedirectUrl(): string
    {
        return $this->googleConfigValue('redirect');
    }

    private function mapProviderCallbackError(string $providerError, string $providerDescription = ''): string
    {
        $normalized = Str::lower(trim($providerError));
        $description = trim($providerDescription);

        return match ($normalized) {
            'access_denied' => 'Google sign-in was canceled.',
            'temporarily_unavailable' => 'Google sign-in is temporarily unavailable. Please try again.',
            default => $description !== ''
                ? "Google sign-in error: {$description}"
                : 'Google sign-in failed. Please try again.',
        };
    }

    private function resolveOAuthExceptionMessage(Throwable $e): string
    {
        $message = Str::lower((string) $e->getMessage());

        if (str_contains($message, 'access_denied')) {
            return 'Google sign-in was canceled.';
        }

        if (str_contains($message, 'redirect_uri_mismatch')) {
            return 'Google redirect URI mismatch. Add this URI in Google Cloud Console: ' . $this->googleRedirectUrl();
        }

        if (str_contains($message, 'invalid_client')) {
            return 'Google OAuth client credentials are invalid. Verify GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.';
        }

        if (str_contains($message, 'invalid_grant')) {
            return 'Google sign-in session expired or code was invalid. Please try again.';
        }

        if (
            str_contains($message, 'timed out')
            || str_contains($message, 'could not resolve host')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'failed to connect')
        ) {
            return 'Unable to reach Google OAuth servers. Check your internet connection and try again.';
        }

        if ((bool) config('app.debug', false)) {
            $raw = trim((string) $e->getMessage());
            $snippet = Str::limit($raw !== '' ? $raw : get_class($e), 240);
            return 'Google sign-in failed: ' . $snippet;
        }

        return 'Google sign-in failed. Please try again.';
    }

    private function redirectToFrontendWithLoginTicket(string $ticket, ?string $portal = null): RedirectResponse
    {
        $payload = [
            'ticket' => $ticket,
        ];
        if ($portal !== null) {
            $payload['portal'] = $portal;
        }
        $query = http_build_query($payload);

        return redirect()->away($this->oauthCallbackUrl() . '?' . $query);
    }

    private function redirectToFrontendWithError(string $errorMessage, ?string $portal = null): RedirectResponse
    {
        $payload = [
            'error' => $errorMessage,
        ];
        if ($portal !== null) {
            $payload['portal'] = $portal;
        }
        $query = http_build_query($payload);

        return redirect()->away($this->oauthCallbackUrl() . '?' . $query);
    }

    private function issueLoginTicket(string $token): string
    {
        $ticket = Str::random(96);
        Cache::put(
            $this->oauthTicketCacheKey($ticket),
            [
                'token' => $token,
                'issued_at' => now()->toIso8601String(),
            ],
            now()->addSeconds($this->oauthTicketTtlSeconds())
        );

        return $ticket;
    }

    private function oauthTicketCacheKey(string $ticket): string
    {
        return self::OAUTH_TICKET_CACHE_PREFIX . hash('sha256', $ticket);
    }

    private function oauthTicketTtlSeconds(): int
    {
        return max(30, (int) env('OAUTH_LOGIN_TICKET_TTL_SECONDS', 120));
    }

    private function tokenExpirySeconds(): ?int
    {
        $minutes = config('sanctum.expiration');
        if ($minutes === null) {
            return null;
        }

        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return null;
        }

        return $minutes * 60;
    }

    private function oauthCallbackUrl(): string
    {
        $base = $this->frontendBaseUrl();
        if (Str::endsWith($base, '/oauth/callback')) {
            return $base;
        }

        return $base . '/oauth/callback';
    }

    private function recordGoogleLogin(
        Request $request,
        ?User $user,
        ?string $email,
        bool $success,
        ?string $failureReason
    ): void {
        try {
            if (!Schema::hasTable('login_logs')) {
                return;
            }

            LoginLog::query()->create([
                'user_id' => $user?->id,
                'email' => $email,
                'role' => $user ? $this->resolveKnownRole($user) : null,
                'auth_method' => 'google',
                'success' => $success,
                'failure_reason' => $failureReason,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // OAuth flow should not fail because audit logging failed.
        }
    }

    private function resolveKnownRole(User $user): ?string
    {
        $roles = $user->roles()->pluck('role')->all();
        if (in_array('admin', $roles, true)) {
            return 'admin';
        }
        if (in_array('counselor', $roles, true)) {
            return 'counselor';
        }
        if (in_array('peer_counselor', $roles, true)) {
            return 'peer_counselor';
        }
        if (in_array('student', $roles, true)) {
            return 'student';
        }

        return null;
    }
}
