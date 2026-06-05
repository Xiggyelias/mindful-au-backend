<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettings
{
    private const CACHE_KEY = 'system_settings.all';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Central default values used when DB rows do not exist yet.
     */
    public static function defaults(): array
    {
        return [
            'panic_alerts' => true,
            'ai_risk_alerts' => true,
            'daily_reports' => false,
            'new_registrations' => true,
            'two_factor_auth' => false,
            'session_timeout' => false,
            'audit_logging' => true,
            'data_encryption' => true,
            'anonymous_mode_default' => false,
            'ai_auto_analysis' => true,
            'auto_backup' => false,
            'admin_email' => '',
            'support_email' => '',
            'crisis_hotline' => '+263 77 406 8265',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    public static function boolKeys(): array
    {
        return [
            'panic_alerts',
            'ai_risk_alerts',
            'daily_reports',
            'new_registrations',
            'two_factor_auth',
            'session_timeout',
            'audit_logging',
            'data_encryption',
            'anonymous_mode_default',
            'ai_auto_analysis',
            'auto_backup',
        ];
    }

    public static function stringKeys(): array
    {
        return [
            'admin_email',
            'support_email',
            'crisis_hotline',
        ];
    }

    public static function all(): array
    {
        if (app()->runningUnitTests()) {
            return self::loadFromDatabase();
        }

        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => self::loadFromDatabase()
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function getString(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);
        return is_string($value) ? $value : (string) $value;
    }

    public static function setMany(array $settings): array
    {
        $allowed = array_flip(self::keys());

        foreach ($settings as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        self::forgetCache();

        return self::all();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function loadFromDatabase(): array
    {
        $stored = SystemSetting::query()->pluck('value', 'key')->toArray();
        $allowed = array_flip(self::keys());

        $normalized = [];
        foreach ($stored as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            if (in_array($key, self::boolKeys(), true)) {
                $normalized[$key] = (bool) $value;
            } elseif (in_array($key, self::stringKeys(), true)) {
                $normalized[$key] = is_string($value) ? $value : (string) ($value ?? '');
            } else {
                $normalized[$key] = $value;
            }
        }

        return array_merge(self::defaults(), $normalized);
    }
}
