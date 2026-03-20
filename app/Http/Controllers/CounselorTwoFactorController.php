<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserTwoFactorMethod;
use App\Support\SystemSettings;
use App\Support\TwoFactorTotp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class CounselorTwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $enabled = SystemSettings::getBool('two_factor_auth', false);
        $isCounselingRole = $this->isCounselingRole($user);
        $method = $this->resolveMethod($user);
        $isConfigured = (bool) $method;
        $isVerified = (bool) $method?->verified_at;
        $tokenVerified = $this->isCurrentTokenVerified($user);
        $required = $enabled && $isCounselingRole && (!$isConfigured || !$isVerified || !$tokenVerified);

        return response()->json([
            'enabled' => $enabled,
            'required' => $required,
            'is_counseling_role' => $isCounselingRole,
            'configured' => $isConfigured,
            'verified' => $isVerified,
            'token_verified' => $tokenVerified,
            'setup_required' => $enabled && $isCounselingRole && !$isConfigured,
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isCounselingRole($user)) {
            return response()->json(['message' => 'Only counselors can configure two-factor authentication.'], 403);
        }

        if (!SystemSettings::getBool('two_factor_auth', false)) {
            return response()->json([
                'message' => 'Two-factor authentication is currently disabled by system settings.',
            ], 422);
        }

        $secret = TwoFactorTotp::generateSecret();
        $method = UserTwoFactorMethod::query()->updateOrCreate(
            ['user_id' => $user->id, 'method' => 'totp'],
            [
                'secret_encrypted' => Crypt::encryptString($secret),
                'verified_at' => null,
            ]
        );

        $this->markCurrentTokenUnverified($user);

        $issuer = trim((string) config('app.name', 'AUCMS'));
        $account = trim((string) $user->email);
        $otpAuthUri = TwoFactorTotp::buildOtpAuthUri($secret, $account, $issuer);

        return response()->json([
            'message' => 'Two-factor setup initiated. Verify using the authenticator code to complete setup.',
            'secret' => $secret,
            'otpauth_uri' => $otpAuthUri,
            'configured' => true,
            'verified' => (bool) $method->verified_at,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->isCounselingRole($user)) {
            return response()->json(['message' => 'Only counselors can verify two-factor authentication.'], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        if (!SystemSettings::getBool('two_factor_auth', false)) {
            return response()->json([
                'message' => 'Two-factor authentication is currently disabled by system settings.',
            ], 422);
        }

        $method = $this->resolveMethod($user);
        if (!$method) {
            return response()->json([
                'message' => 'Two-factor is not configured yet. Complete setup first.',
                'setup_required' => true,
            ], 422);
        }

        try {
            $secret = Crypt::decryptString((string) $method->secret_encrypted);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Stored two-factor secret could not be read. Please set up again.',
                'setup_required' => true,
            ], 422);
        }

        $isValid = TwoFactorTotp::verifyCode(
            $secret,
            (string) $validated['code'],
            window: 1,
            digits: 6,
            periodSeconds: 30
        );

        if (!$isValid) {
            return response()->json([
                'message' => 'Invalid verification code. Please try again.',
            ], 422);
        }

        if (!$method->verified_at) {
            $method->forceFill(['verified_at' => now()])->save();
        }

        $this->markCurrentTokenVerified($user);

        return response()->json([
            'message' => 'Two-factor authentication verified.',
            'configured' => true,
            'verified' => true,
            'token_verified' => true,
            'required' => false,
        ]);
    }

    private function resolveMethod(User $user): ?UserTwoFactorMethod
    {
        return UserTwoFactorMethod::query()
            ->where('user_id', $user->id)
            ->where('method', 'totp')
            ->first();
    }

    private function isCounselingRole(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        return $user->hasRole('counselor') || $user->hasRole('peer_counselor');
    }

    private function isCurrentTokenVerified(User $user): bool
    {
        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken || !Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
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

    private function markCurrentTokenVerified(User $user): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
            return;
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return;
        }

        PersonalAccessToken::query()
            ->whereKey($token->id)
            ->update(['two_factor_passed_at' => now()]);
    }

    private function markCurrentTokenUnverified(User $user): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
            return;
        }

        $token = $user->currentAccessToken();
        if (!$token) {
            return;
        }

        PersonalAccessToken::query()
            ->whereKey($token->id)
            ->update(['two_factor_passed_at' => null]);
    }
}
