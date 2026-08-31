<?php
/**
 * TicketTrade — Support\Crypto
 *
 * The canonical wrapper for random_bytes(), hash(), hash_hmac(), and
 * hash_equals(). Per AD-18, this file plus Auth/Service/auth_service.php
 * are the only places these primitives are called from. The Phase 9
 * phpcs sniff allow-lists both files.
 */

declare(strict_types=1);

namespace App\Support;

class Crypto
{
    /**
     * Generate a hex-encoded random token of $bytes bytes (default 32 → 64 hex chars).
     */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Hash a raw token with SHA-256 — produces 64 hex chars.
     *
     * Stored in email_verifications.token_hash and password_resets.token_hash
     * (CHAR(64) columns per Pitfall 12).
     */
    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * Constant-time string comparison. Use for any token comparison.
     */
    public static function constantTimeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    /**
     * HMAC-SHA256 with the given key.
     */
    public static function hmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }
}
