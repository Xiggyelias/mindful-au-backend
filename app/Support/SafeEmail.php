<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class SafeEmail
{
    private const CONTROL_CHARACTER_PATTERN = '/[\r\n]/';

    /**
     * @param  array<int, mixed>  $extraRules
     * @return array<int, mixed>
     */
    public static function required(array $extraRules = []): array
    {
        return array_merge(['required', 'string', 'max:255', self::noControlCharactersRule(), 'email:rfc'], $extraRules);
    }

    /**
     * @param  array<int, mixed>  $extraRules
     * @return array<int, mixed>
     */
    public static function sometimes(array $extraRules = []): array
    {
        return array_merge(['sometimes', 'string', 'max:255', self::noControlCharactersRule(), 'email:rfc'], $extraRules);
    }

    /**
     * @param  array<int, mixed>  $extraRules
     * @return array<int, mixed>
     */
    public static function nullable(array $extraRules = []): array
    {
        return array_merge(['sometimes', 'nullable', 'string', 'max:255', self::noControlCharactersRule(), 'email:rfc'], $extraRules);
    }

    public static function normalize(?string $email): string
    {
        return strtolower(trim(preg_replace(self::CONTROL_CHARACTER_PATTERN, '', (string) $email) ?? ''));
    }

    public static function hasControlCharacters(?string $email): bool
    {
        return preg_match(self::CONTROL_CHARACTER_PATTERN, (string) $email) === 1;
    }

    /**
     * @return \Closure(string, mixed, \Closure(string): void): void
     */
    private static function noControlCharactersRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if (self::hasControlCharacters(is_string($value) ? $value : (string) $value)) {
                $fail("The {$attribute} field must be a valid email address.");
            }
        };
    }

    public static function unique(string $table, string $column = 'email'): mixed
    {
        return Rule::unique($table, $column);
    }
}
