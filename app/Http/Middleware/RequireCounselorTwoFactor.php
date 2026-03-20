<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserTwoFactorMethod;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireCounselorTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $next($request);
        }

        if (!$this->isCounselingRole($user)) {
            return $next($request);
        }

        if ($this->isAllowedWithoutTwoFactor($request->path())) {
            return $next($request);
        }

        if (!SystemSettings::getBool('two_factor_auth', false)) {
            return $next($request);
        }

        $method = UserTwoFactorMethod::query()
            ->where('user_id', $user->id)
            ->where('method', 'totp')
            ->first();

        if (!$method || !$method->verified_at) {
            return response()->json([
                'message' => 'Two-factor setup is required for counselor access.',
                'two_factor_setup_required' => true,
                'two_factor_required' => true,
            ], 403);
        }

        if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Two-factor verification is required.',
                'two_factor_required' => true,
            ], 403);
        }

        $freshToken = PersonalAccessToken::query()
            ->whereKey($token->id)
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->first();
        if (!$freshToken) {
            return response()->json([
                'message' => 'Two-factor verification is required.',
                'two_factor_required' => true,
            ], 403);
        }

        $freshTokenValue = (string) ($freshToken->token ?? '');
        $currentTokenValue = (string) ($token->token ?? '');
        $hashesMatch = hash_equals(
            hash('sha256', $freshTokenValue),
            hash('sha256', $currentTokenValue)
        );
        if (
            !$hashesMatch
            || $freshTokenValue === ''
            || $currentTokenValue === ''
            || !$freshToken->two_factor_passed_at
        ) {
            return response()->json([
                'message' => 'Two-factor verification is required.',
                'two_factor_required' => true,
            ], 403);
        }

        return $next($request);
    }

    private function isCounselingRole(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        return $user->hasRole('counselor') || $user->hasRole('peer_counselor');
    }

    private function isAllowedWithoutTwoFactor(string $path): bool
    {
        $normalized = ltrim(trim($path), '/');

        if (str_starts_with($normalized, 'api/auth/2fa')) {
            return true;
        }

        return in_array($normalized, [
            'api/me',
            'api/me/presence',
            'api/logout',
            'api/refresh',
            'api/health',
            'api/ready',
        ], true);
    }
}
