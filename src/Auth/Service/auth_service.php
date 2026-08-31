<?php
/**
 * TicketTrade — Auth\Service\auth_service
 *
 * Per AD-18: the SOLE writer of password_hash() / password_verify() in
 * the codebase. Plan 02-02 lands the full method surface (register,
 * login, verify, forgotPassword, resetPassword, logout). Plan 02-01
 * ships the bcrypt primitives and the helpers used by Plan 02-02 and
 * the Wave 0 tests.
 */

declare(strict_types=1);

namespace App\Auth\Service;

use App\Support\Auth;
use App\Support\Crypto;

class auth_service
{
    private static ?string $dummyHash = null;

    /**
     * Hash a plaintext password at the configured bcrypt cost (12).
     */
    public static function hashPassword(string $plain): string
    {
        $cfg = require APP_ROOT . '/config/auth.php';
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => (int) $cfg['bcrypt_cost']]);
    }

    /**
     * Constant-time verify against a stored bcrypt hash.
     */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Compute a bcrypt "dummy" hash once per process. Use on failed
     * logins to keep the response time independent of whether the
     * user exists (Pitfall 3 — timing-attack mitigation).
     */
    public static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            $cfg = require APP_ROOT . '/config/auth.php';
            self::$dummyHash = password_hash(
                'dummy-for-timing-attack-mitigation-only',
                PASSWORD_BCRYPT,
                ['cost' => (int) $cfg['bcrypt_cost']]
            );
        }
        return self::$dummyHash;
    }

    /**
     * Generate a hex-encoded random token (delegates to Support\Crypto).
     */
    public static function randomToken(int $bytes = 32): string
    {
        return Crypto::randomToken($bytes);
    }

    /**
     * SHA-256 of a raw token — produces the CHAR(64) hash stored in
     * email_verifications.token_hash and password_resets.token_hash.
     */
    public static function hashToken(string $raw): string
    {
        return Crypto::hashToken($raw);
    }

    /**
     * Strip sensitive fields from a user row before passing to a View.
     */
    public static function sanitizeUser(array $row): array
    {
        return Auth::sanitizeUser($row);
    }

    /**
     * Resolve the rank tier for a given point balance.
     */
    public static function tierFromPoints(int $points): string
    {
        if (function_exists('tierFromPoints')) {
            return tierFromPoints($points);
        }
        $ladder = require APP_ROOT . '/config/ranks.php';
        $current = 'E';
        foreach ($ladder as $tier => $def) {
            if ($points >= $def['min_points']) {
                $current = $tier;
            }
        }
        return $current;
    }

    /**
     * Validate a post-login redirect target. Rejects absolute URLs,
     * protocol-relative URLs, and anything with a backslash to prevent
     * open-redirect attacks (Pitfall 5).
     */
    public static function nextRedirectIsSafe(?string $next): string
    {
        $next = (string) $next;
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '/board';
        }
        return $next;
    }
}
