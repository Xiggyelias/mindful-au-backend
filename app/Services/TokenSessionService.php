<?php

namespace App\Services;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

class TokenSessionService
{
    private const DEVICE_ID_HEADER = 'X-Device-ID';
    private const DEVICE_NAME_HEADER = 'X-Device-Name';
    private const MIN_ACTIVITY_WRITE_GAP_SECONDS = 30;
    private const MAX_DEVICE_ID_LENGTH = 191;
    private const MAX_DEVICE_NAME_LENGTH = 120;
    private const MAX_IP_ADDRESS_LENGTH = 45;
    private const MAX_USER_AGENT_LENGTH = 2000;

    public function issueToken(Request $request, User $user, string $tokenName = 'auth_token'): NewAccessToken
    {
        $context = $this->buildContext($request);
        $this->deleteSameDeviceTokens($user, $context['device_id']);

        $issuedToken = $user->createToken($tokenName);
        $this->applyMetadata($issuedToken->accessToken, $context);

        return $issuedToken;
    }

    public function rotateCurrentToken(Request $request, User $user, string $tokenName = 'auth_token'): ?NewAccessToken
    {
        $currentToken = $user->currentAccessToken();
        if (!$currentToken instanceof PersonalAccessToken) {
            return null;
        }

        $context = $this->buildContext($request);
        $issuedToken = $user->createToken($tokenName);
        $replacement = $issuedToken->accessToken;
        $this->applyMetadata($replacement, [
            'device_id' => (string) ($currentToken->device_id ?: $context['device_id']),
            'device_name' => (string) ($context['device_name'] ?: $currentToken->device_name ?: 'Current device'),
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'last_activity_at' => now(),
            'two_factor_passed_at' => $currentToken->two_factor_passed_at,
        ]);

        $currentToken->delete();

        return $issuedToken;
    }

    public function touchCurrentToken(Request $request, User $user, bool $force = false): void
    {
        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return;
        }

        $requestDeviceId = $this->resolveDeviceId($request);
        $tokenDeviceId = trim((string) ($token->device_id ?? ''));
        if ($tokenDeviceId !== '' && !hash_equals($tokenDeviceId, $requestDeviceId)) {
            return;
        }

        $now = now();
        $lastActivityAt = $token->last_activity_at ?? $token->last_used_at ?? $token->created_at;
        if (
            !$force
            && $lastActivityAt instanceof \DateTimeInterface
            && $lastActivityAt->getTimestamp() >= $now->copy()->subSeconds(self::MIN_ACTIVITY_WRITE_GAP_SECONDS)->getTimestamp()
        ) {
            return;
        }

        $context = $this->buildContext($request);
        $this->applyMetadata($token, [
            'device_id' => $tokenDeviceId !== '' ? $tokenDeviceId : $context['device_id'],
            'device_name' => (string) ($token->device_name ?: $context['device_name']),
            'ip_address' => $context['ip_address'],
            'user_agent' => $context['user_agent'],
            'last_activity_at' => $now,
            'two_factor_passed_at' => $token->two_factor_passed_at,
        ]);
    }

    public function isRequestBoundToToken(Request $request, User $user): bool
    {
        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken) {
            return true;
        }

        $tokenDeviceId = trim((string) ($token->device_id ?? ''));
        if ($tokenDeviceId === '') {
            return true;
        }

        $requestDeviceId = $this->resolveDeviceId($request);
        return hash_equals($tokenDeviceId, $requestDeviceId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSessions(User $user): array
    {
        $currentTokenId = $user->currentAccessToken()?->getKey();

        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (PersonalAccessToken $token) => $this->tokenHasExpired($token))
            ->map(fn (PersonalAccessToken $token) => $this->formatSession($token, $currentTokenId))
            ->values()
            ->all();
    }

    public function revokeSession(User $user, int $tokenId): bool
    {
        $currentToken = $user->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken && (int) $currentToken->getKey() === $tokenId) {
            return false;
        }

        return PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->delete() > 0;
    }

    public function logoutOtherDevices(User $user): int
    {
        $currentTokenId = $user->currentAccessToken()?->getKey();

        $query = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id);

        if ($currentTokenId) {
            $query->whereKeyNot($currentTokenId);
        }

        return $query->delete();
    }

    /**
     * @return array{device_id: string, device_name: string, ip_address: string|null, user_agent: string|null, last_activity_at: Carbon, two_factor_passed_at: Carbon|null}
     */
    private function buildContext(Request $request): array
    {
        return [
            'device_id' => $this->resolveDeviceId($request),
            'device_name' => $this->resolveDeviceName($request),
            'ip_address' => $this->resolveIpAddress($request),
            'user_agent' => $this->limitNullableString($request->userAgent(), self::MAX_USER_AGENT_LENGTH),
            'last_activity_at' => now(),
            'two_factor_passed_at' => null,
        ];
    }

    private function resolveDeviceId(Request $request): string
    {
        $headerDeviceId = Str::of((string) $request->header(self::DEVICE_ID_HEADER, ''))->trim()->limit(self::MAX_DEVICE_ID_LENGTH, '');
        if ($headerDeviceId->isNotEmpty()) {
            return (string) $headerDeviceId;
        }

        return hash('sha256', implode('|', [
            trim((string) $request->userAgent()),
            trim((string) $request->ip()),
        ]));
    }

    private function resolveDeviceName(Request $request): string
    {
        $headerDeviceName = trim((string) $request->header(self::DEVICE_NAME_HEADER, ''));
        if ($headerDeviceName !== '') {
            return Str::limit($headerDeviceName, self::MAX_DEVICE_NAME_LENGTH, '');
        }

        $userAgent = trim((string) $request->userAgent());
        if ($userAgent !== '') {
            return Str::limit($userAgent, self::MAX_DEVICE_NAME_LENGTH, '');
        }

        return 'Browser session';
    }

    private function resolveIpAddress(Request $request): ?string
    {
        $ipAddress = $this->limitNullableString($request->ip(), self::MAX_IP_ADDRESS_LENGTH);
        if ($ipAddress === null) {
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $ipAddress;
        }

        $firstForwardedIp = trim(strtok($ipAddress, ',') ?: '');
        if ($firstForwardedIp !== '' && filter_var($firstForwardedIp, FILTER_VALIDATE_IP)) {
            return Str::limit($firstForwardedIp, self::MAX_IP_ADDRESS_LENGTH, '');
        }

        return $ipAddress;
    }

    private function limitNullableString(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return Str::limit($normalized, $maxLength, '');
    }

    private function deleteSameDeviceTokens(User $user, string $deviceId): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->where('device_id', $deviceId)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function applyMetadata(?PersonalAccessToken $token, array $context): void
    {
        if (!$token) {
            return;
        }

        $columns = $this->availableColumns();
        $updates = [];

        foreach (['device_id', 'device_name', 'ip_address', 'user_agent', 'last_activity_at', 'two_factor_passed_at'] as $key) {
            if (isset($columns[$key])) {
                $updates[$key] = $context[$key] ?? null;
            }
        }

        if ($updates === []) {
            return;
        }

        try {
            $token->forceFill($updates)->save();
        } catch (\Throwable $e) {
            Log::warning('Unable to persist auth token device metadata.', [
                'token_id' => $token->getKey(),
                'error' => $e::class,
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function availableColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $columns = [];
        if (!Schema::hasTable('personal_access_tokens')) {
            return $columns;
        }

        foreach (['device_id', 'device_name', 'ip_address', 'user_agent', 'last_activity_at', 'two_factor_passed_at'] as $column) {
            $columns[$column] = Schema::hasColumn('personal_access_tokens', $column);
        }

        return $columns;
    }

    private function tokenHasExpired(PersonalAccessToken $token): bool
    {
        $expirationMinutes = config('sanctum.expiration');
        if ($expirationMinutes === null) {
            return false;
        }

        $minutes = (int) $expirationMinutes;
        if ($minutes <= 0) {
            return false;
        }

        $createdAt = $token->created_at;
        if (!$createdAt instanceof \DateTimeInterface) {
            return false;
        }

        return Carbon::instance($createdAt)->addMinutes($minutes)->isPast();
    }

    /**
     * @param  int|string|null  $currentTokenId
     * @return array<string, mixed>
     */
    private function formatSession(PersonalAccessToken $token, int|string|null $currentTokenId): array
    {
        $lastActivityAt = $token->last_activity_at ?? $token->last_used_at ?? $token->created_at;
        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = null;

        if ($expirationMinutes !== null && (int) $expirationMinutes > 0 && $token->created_at instanceof \DateTimeInterface) {
            $expiresAt = Carbon::instance($token->created_at)->addMinutes((int) $expirationMinutes)->toIso8601String();
        }

        return [
            'id' => (int) $token->getKey(),
            'device_id' => (string) ($token->device_id ?? ''),
            'device_name' => (string) ($token->device_name ?: 'Browser session'),
            'ip_address' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'created_at' => optional($token->created_at)->toIso8601String(),
            'last_activity_at' => $lastActivityAt instanceof \DateTimeInterface ? Carbon::instance($lastActivityAt)->toIso8601String() : null,
            'last_used_at' => optional($token->last_used_at)->toIso8601String(),
            'expires_at' => $expiresAt,
            'is_current' => $currentTokenId !== null && (int) $currentTokenId === (int) $token->getKey(),
            'two_factor_verified' => !empty($token->two_factor_passed_at),
        ];
    }
}
