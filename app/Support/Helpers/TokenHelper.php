<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Illuminate\Support\Str;

/**
 * Generation and verification of the opaque secrets used by player devices and
 * QR check-in links.
 *
 * Plain values are shown exactly once; only hashes are persisted, so a database
 * leak cannot be replayed against the API.
 */
final class TokenHelper
{
    private const DEVICE_TOKEN_BYTES = 32;

    /**
     * Build a device pairing token.
     *
     * @return array{plain: string, hash: string, preview: string}
     */
    public static function generateDeviceToken(): array
    {
        $plain = 'dkm_'.Str::lower(Str::random(8)).'_'.bin2hex(random_bytes(self::DEVICE_TOKEN_BYTES));

        return [
            'plain' => $plain,
            'hash' => self::hash($plain),
            'preview' => self::preview($plain),
        ];
    }

    /**
     * SHA-256 keeps device authentication fast enough for a 15-second poll while
     * remaining one-way. Entropy comes from `random_bytes`, not from a password,
     * so a slow KDF would only add latency without adding security.
     */
    public static function hash(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('app.key'));
    }

    public static function matches(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::hash($plain));
    }

    /**
     * `dkm_ab12…f9c3` — safe to display in listings.
     */
    public static function preview(string $plain): string
    {
        if (strlen($plain) <= 12) {
            return $plain;
        }

        return substr($plain, 0, 8).'…'.substr($plain, -4);
    }

    /**
     * Random token used inside attendance QR codes.
     */
    public static function generateSessionToken(): string
    {
        return Str::lower(Str::random(40));
    }
}
