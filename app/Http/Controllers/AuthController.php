<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\UserRole;
use App\Models\InstitutionAccount;
use App\Models\LoginLog;
use App\Models\Notification;
use App\Models\UserTwoFactorMethod;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const DEFAULT_PRESENCE_TOUCH_INTERVAL_SECONDS = 60;

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $validated = $request->validate([
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:128',
            'full_name' => 'required|string|max:255',
            'id_number' => 'nullable|string|max:255|required_if:role,counselor,peer_counselor',
            'role' => 'required|in:student,counselor,peer_counselor',
        ]);

        $normalizedEmail = $validated['email'];

        $emailTaken = User::query()
            ->where('email', $normalizedEmail)
            ->exists();

        if (!$emailTaken) {
            // Fallback for legacy rows with mixed-case emails.
            $emailTaken = User::query()
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->exists();
        }

        if ($emailTaken) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [
                    'email' => ['The email has already been taken.'],
                ],
            ], 422);
        }

        if (
            $this->isGoogleRequiredForStudents()
            && $validated['role'] === 'student'
        ) {
            return response()->json([
                'message' => 'Student registration must use institutional Google sign-in.',
            ], 403);
        }

        if (
            !SystemSettings::getBool('new_registrations', true)
            && in_array($validated['role'], ['counselor', 'peer_counselor'], true)
        ) {
            return response()->json([
                'message' => 'New staff registrations are currently closed.',
            ], 403);
        }

        $user = User::create([
            'email' => $normalizedEmail,
            'password' => Hash::make($validated['password']),
            'last_seen_at' => now(),
        ]);

        Profile::create([
            'user_id' => $user->id,
            'full_name' => $validated['full_name'],
            'id_number' => $validated['id_number'] ?? null,
            'anonymous_mode' => $validated['role'] === 'student'
                ? SystemSettings::getBool('anonymous_mode_default', false)
                : false,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role' => $validated['role'],
            'approved' => $validated['role'] === 'student',
        ]);

        if (
            in_array($validated['role'], ['counselor', 'peer_counselor'], true)
            && SystemSettings::getBool('new_registrations', true)
        ) {
            $admins = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('role', 'admin')->where('approved', true);
                })
                ->pluck('id');

            foreach ($admins as $adminId) {
                Notification::query()->create([
                    'user_id' => (int) $adminId,
                    'title' => 'New Staff Registration',
                    'message' => sprintf(
                        '%s registered as %s and is awaiting approval.',
                        $validated['full_name'],
                        str_replace('_', ' ', $validated['role'])
                    ),
                    'type' => 'info',
                ]);
            }
        }

        $requiresAdminApproval = in_array(
            $validated['role'],
            ['counselor', 'peer_counselor'],
            true
        );

        if ($requiresAdminApproval) {
            return response()->json([
                'message' => 'Registration submitted. Your account must be approved by an admin before you can sign in.',
                'user' => $user->loadMissing(['profile', 'roles']),
                'access_token' => null,
                'token_type' => null,
                'expires_in' => $this->tokenExpirySeconds(),
                'pending_approval' => true,
            ], 201);
        }

        // Issue Sanctum token for roles that do not require admin approval.
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user->loadMissing(['profile', 'roles']),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpirySeconds(),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email:rfc|max:255',
                'password' => 'required|string|min:1|max:128',
            ]);
        } catch (ValidationException $e) {
            $email = trim((string) $request->input('email', ''));
            $this->recordLoginLog(
                request: $request,
                user: null,
                email: $email !== '' ? $email : null,
                method: 'password',
                success: false,
                failureReason: 'validation_failed'
            );

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $normalizedEmail = $this->normalizeEmail($validated['email']);
        $user = $this->findUserForLogin($normalizedEmail);

        if (!$user || !$this->verifyPassword($user, $validated['password'])) {
            $this->recordLoginLog(
                request: $request,
                user: $user,
                email: $normalizedEmail,
                method: 'password',
                success: false,
                failureReason: 'invalid_credentials'
            );

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $this->ensureUserAccessRecords($user);

        $hasApprovedPortalRole = $this->hasAnyApprovedPortalRole($user);
        if (!$hasApprovedPortalRole && $this->hasPendingManualApproval($user)) {
            $this->recordLoginLog(
                request: $request,
                user: $user,
                email: $normalizedEmail,
                method: 'password',
                success: false,
                failureReason: 'account_pending_approval'
            );

            return response()->json([
                'message' => 'Your account is pending admin approval.',
            ], 403);
        }

        if (!$hasApprovedPortalRole) {
            $this->recordLoginLog(
                request: $request,
                user: $user,
                email: $normalizedEmail,
                method: 'password',
                success: false,
                failureReason: 'no_approved_role'
            );

            return response()->json([
                'message' => 'Your account does not have an approved portal role.',
            ], 403);
        }

        if ($this->isGoogleRequiredForStudents() && $user->hasRole('student')) {
            $this->recordLoginLog(
                request: $request,
                user: $user,
                email: $normalizedEmail,
                method: 'password',
                success: false,
                failureReason: 'google_required_for_student'
            );

            return response()->json([
                'message' => 'Students must sign in using institutional Google authentication.',
            ], 403);
        }

        $this->touchPresenceIfStale($user);

        // Revoke older tokens to reduce token reuse risk.
        $user->tokens()->delete();

        // Issue Sanctum token
        $issuedToken = $user->createToken('auth_token');
        $twoFactorState = $this->resolveTwoFactorState($user, false);
        $this->setTokenTwoFactorPassed($issuedToken, !$twoFactorState['required']);
        if (!$twoFactorState['required']) {
            $twoFactorState['token_verified'] = true;
        }

        $this->recordLoginLog(
            request: $request,
            user: $user,
            email: $normalizedEmail,
            method: 'password',
            success: true,
            failureReason: null
        );

        return response()->json([
            'message' => $twoFactorState['required']
                ? 'Two-factor authentication required.'
                : 'Login successful',
            'user' => $user->loadMissing(['profile', 'roles']),
            'access_token' => $issuedToken->plainTextToken,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpirySeconds(),
            'two_factor_enabled' => $twoFactorState['enabled'],
            'two_factor_required' => $twoFactorState['required'],
            'two_factor_setup_required' => $twoFactorState['setup_required'],
            'two_factor_verified' => $twoFactorState['verified'],
            'two_factor_token_verified' => $twoFactorState['token_verified'],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function refresh(): JsonResponse
    {
        return response()->json([
            'message' => 'Refresh not supported for Sanctum tokens. Request a new token via login.',
        ], 400);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserAccessRecords($user);
        if (!$this->hasAnyApprovedPortalRole($user)) {
            return response()->json([
                'message' => 'Your account does not have an approved portal role.',
            ], 403);
        }
        $this->touchPresenceIfStale($user);

        $user->loadMissing(['profile', 'roles']);
        $twoFactorState = $this->resolveTwoFactorState($user, $this->isTokenTwoFactorVerified($user));
        $payload = $user->toArray();
        $payload['two_factor_enabled'] = $twoFactorState['enabled'];
        $payload['two_factor_required'] = $twoFactorState['required'];
        $payload['two_factor_setup_required'] = $twoFactorState['setup_required'];
        $payload['two_factor_verified'] = $twoFactorState['verified'];
        $payload['two_factor_token_verified'] = $twoFactorState['token_verified'];

        return response()->json($payload);
    }

    public function presence(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureUserAccessRecords($user);
        if (!$this->hasAnyApprovedPortalRole($user)) {
            return response()->json([
                'message' => 'Your account does not have an approved portal role.',
            ], 403);
        }
        $this->touchPresenceIfStale($user);

        return response()->json([
            'status' => 'ok',
            'last_seen_at' => optional($user->last_seen_at)->toIso8601String(),
        ]);
    }

    private function ensureUserAccessRecords(User $user): void
    {
        $user->loadMissing(['profile', 'roles']);

        if (!$user->profile) {
            $fallbackName = Str::of(Str::before($user->email, '@'))
                ->replace(['.', '_', '-'], ' ')
                ->title()
                ->value();

            Profile::query()->create([
                'user_id' => $user->id,
                'full_name' => $fallbackName !== '' ? $fallbackName : "User {$user->id}",
                'id_number' => null,
                'anonymous_mode' => false,
            ]);

            $user->unsetRelation('profile');
            $user->load('profile');
        }

        if ($user->roles->isEmpty()) {
            $this->provisionRoleFromInstitutionAccount($user);
            $user->unsetRelation('roles');
            $user->load('roles');
        }
    }

    private function provisionRoleFromInstitutionAccount(User $user): void
    {
        if (!Schema::hasTable('institution_accounts')) {
            return;
        }

        $email = $this->normalizeEmail($user->email);

        $account = InstitutionAccount::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->where('approved', true)
            ->first();

        if (!$account) {
            // Fallback for legacy rows with mixed-case emails.
            $account = InstitutionAccount::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('is_active', true)
                ->where('approved', true)
                ->first();
        }

        if (!$account) {
            return;
        }

        $role = $this->normalizeInstitutionRole((string) $account->role);
        if ($role === null) {
            return;
        }

        UserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => $role],
            ['approved' => true]
        );

        if ($user->profile) {
            $profileUpdates = [];
            if (!$user->profile->full_name && !empty($account->full_name)) {
                $profileUpdates['full_name'] = (string) $account->full_name;
            }
            if (!$user->profile->id_number && !empty($account->id_number)) {
                $profileUpdates['id_number'] = (string) $account->id_number;
            }
            if ($profileUpdates !== []) {
                $user->profile->update($profileUpdates);
                $user->unsetRelation('profile');
            }
        }

        $user->unsetRelation('roles');
        $user->load('roles');
    }

    private function isGoogleRequiredForStudents(): bool
    {
        $value = env('AUTH_REQUIRE_GOOGLE_FOR_STUDENTS', env('AUTH_REQUIRE_GOOGLE_FOR_STUDENT_STAFF', true));
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function recordLoginLog(
        Request $request,
        ?User $user,
        ?string $email,
        string $method,
        bool $success,
        ?string $failureReason
    ): void {
        try {
            if (!Schema::hasTable('login_logs')) {
                return;
            }

            LoginLog::query()->create([
                'user_id' => $user?->id,
                'email' => $email ? Str::lower(trim($email)) : null,
                'role' => $user ? $this->resolvePrimaryRole($user) : null,
                'auth_method' => $method,
                'success' => $success,
                'failure_reason' => $failureReason,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Login should not fail because audit logging failed.
        }
    }

    private function resolvePrimaryRole(User $user): ?string
    {
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        $roles = $user->roles
            ->pluck('role')
            ->filter(fn ($role) => is_string($role) && $role !== '')
            ->values()
            ->all();

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

    private function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    private function findUserForLogin(string $normalizedEmail): ?User
    {
        $user = User::query()
            ->where('email', $normalizedEmail)
            ->first();

        if ($user) {
            return $user;
        }

        // Fallback for legacy rows with mixed-case emails.
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();
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

    private function verifyPassword(User $user, string $password): bool
    {
        $storedPassword = (string) $user->password;
        if ($storedPassword === '') {
            return false;
        }

        if (Hash::check($password, $storedPassword)) {
            return true;
        }

        // Legacy compatibility: if a plaintext password was stored, accept once and repair.
        if (!str_starts_with($storedPassword, '$') && hash_equals($storedPassword, $password)) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->saveQuietly();
            return true;
        }

        return false;
    }

    private function hasPendingManualApproval(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles
                ->whereIn('role', ['student', 'counselor', 'peer_counselor'])
                ->contains(fn ($role) => !(bool) ($role->approved ?? false));
        }

        return $user->roles()
            ->whereIn('role', ['student', 'counselor', 'peer_counselor'])
            ->where('approved', false)
            ->exists();
    }

    private function hasAnyApprovedPortalRole(User $user): bool
    {
        if ($user->relationLoaded('roles')) {
            return $user->roles
                ->whereIn('role', ['admin', 'student', 'counselor', 'peer_counselor'])
                ->contains(fn ($role) => (bool) ($role->approved ?? false));
        }

        return $user->roles()
            ->whereIn('role', ['admin', 'student', 'counselor', 'peer_counselor'])
            ->where('approved', true)
            ->exists();
    }

    private function normalizeInstitutionRole(string $role): ?string
    {
        $normalized = Str::lower(trim($role));

        return match ($normalized) {
            'admin' => 'admin',
            'student' => 'student',
            'counselor', 'counsellor', 'staff' => 'counselor',
            'peer_counselor', 'peercounselor', 'peer-counselor', 'peer counselor' => 'peer_counselor',
            default => null,
        };
    }

    private function touchPresenceIfStale(User $user): void
    {
        $lastSeenAt = $user->last_seen_at;
        $presenceTouchIntervalSeconds = $this->presenceTouchIntervalSeconds();
        if (
            $lastSeenAt instanceof \DateTimeInterface
            && $lastSeenAt->getTimestamp() >= now()->subSeconds($presenceTouchIntervalSeconds)->getTimestamp()
        ) {
            return;
        }

        if (!Cache::add($this->presenceTouchCacheKey((int) $user->id), 1, now()->addSeconds($presenceTouchIntervalSeconds))) {
            return;
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    private function presenceTouchIntervalSeconds(): int
    {
        return max(15, (int) env('PRESENCE_TOUCH_INTERVAL_SECONDS', self::DEFAULT_PRESENCE_TOUCH_INTERVAL_SECONDS));
    }

    private function presenceTouchCacheKey(int $userId): string
    {
        return "presence:touch:user:{$userId}";
    }

    /**
     * @return array{
     *   enabled: bool,
     *   required: bool,
     *   setup_required: bool,
     *   verified: bool,
     *   token_verified: bool
     * }
     */
    private function resolveTwoFactorState(User $user, bool $tokenVerified): array
    {
        $enabled = SystemSettings::getBool('two_factor_auth', false);
        $isCounselingRole = $this->isCounselingRole($user);

        if (!$enabled || !$isCounselingRole) {
            return [
                'enabled' => $enabled,
                'required' => false,
                'setup_required' => false,
                'verified' => false,
                'token_verified' => $tokenVerified,
            ];
        }

        $method = UserTwoFactorMethod::query()
            ->where('user_id', $user->id)
            ->where('method', 'totp')
            ->first();

        $setupRequired = !$method;
        $verified = (bool) $method?->verified_at;
        $required = $setupRequired || !$verified || !$tokenVerified;

        return [
            'enabled' => true,
            'required' => $required,
            'setup_required' => $setupRequired,
            'verified' => $verified,
            'token_verified' => $tokenVerified,
        ];
    }

    private function isCounselingRole(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        return $user->hasRole('counselor') || $user->hasRole('peer_counselor');
    }

    private function isTokenTwoFactorVerified(User $user): bool
    {
        if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
            return false;
        }

        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return false;
        }

        $freshToken = PersonalAccessToken::query()
            ->whereKey($token->id)
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->first();
        if (!$freshToken) {
            return false;
        }

        $freshTokenValue = (string) ($freshToken->token ?? '');
        $currentTokenValue = (string) ($token->token ?? '');
        $hashesMatch = hash_equals(
            hash('sha256', $freshTokenValue),
            hash('sha256', $currentTokenValue)
        );
        if (!$hashesMatch || $freshTokenValue === '' || $currentTokenValue === '') {
            return false;
        }

        return !empty($freshToken->two_factor_passed_at);
    }

    private function setTokenTwoFactorPassed(NewAccessToken $issuedToken, bool $passed): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
            return;
        }

        $tokenModel = $issuedToken->accessToken ?? null;
        if (!$tokenModel instanceof PersonalAccessToken) {
            return;
        }

        $tokenModel->forceFill([
            'two_factor_passed_at' => $passed ? now() : null,
        ])->save();
    }
}
