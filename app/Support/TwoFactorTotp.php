<?php

namespace App\Support;

class TwoFactorTotp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $length = 32): string
    {
        $length = max(16, min(64, $length));
        $bytes = random_bytes($length);
        $alphabet = self::BASE32_ALPHABET;
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[ord($bytes[$i]) % 32];
        }

        return $secret;
    }

    public static function verifyCode(
        string $secret,
        string $code,
        int $window = 1,
        int $digits = 6,
        int $periodSeconds = 30
    ): bool {
        $digits = max(6, min(8, $digits));
        $periodSeconds = max(15, min(120, $periodSeconds));
        $cleanCode = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($cleanCode) !== $digits) {
            return false;
        }

        $counter = (int) floor(time() / $periodSeconds);
        for ($offset = -abs($window); $offset <= abs($window); $offset++) {
            $expected = self::generateCode($secret, $counter + $offset, $digits);
            if ($expected !== '' && hash_equals($expected, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    public static function buildOtpAuthUri(
        string $secret,
        string $accountName,
        string $issuer
    ): string {
        $safeIssuer = trim($issuer) !== '' ? trim($issuer) : 'AUCMS';
        $label = rawurlencode($safeIssuer . ':' . $accountName);
        $encodedIssuer = rawurlencode($safeIssuer);
        $encodedSecret = rawurlencode($secret);

        return "otpauth://totp/{$label}?secret={$encodedSecret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    private static function generateCode(string $secret, int $counter, int $digits): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '';
        }

        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0f;

        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );

        $mod = 10 ** $digits;
        return str_pad((string) ($binary % $mod), $digits, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $value): string
    {
        $clean = strtoupper(trim($value));
        $clean = preg_replace('/[^A-Z2-7]/', '', $clean) ?? '';
        if ($clean === '') {
            return '';
        }

        $alphabetMap = array_flip(str_split(self::BASE32_ALPHABET));
        $bits = '';

        foreach (str_split($clean) as $char) {
            if (!array_key_exists($char, $alphabetMap)) {
                continue;
            }
            $bits .= str_pad(decbin((int) $alphabetMap[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        $bitLength = strlen($bits);
        for ($i = 0; $i + 8 <= $bitLength; $i += 8) {
            $decoded .= chr(bindec(substr($bits, $i, 8)));
        }

        return $decoded;
    }
}
