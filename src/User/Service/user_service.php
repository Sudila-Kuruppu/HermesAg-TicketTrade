<?php

/**
 * TicketTrade — User\Service\user_service
 *
 * Profile-edit operations on an existing user (Plan 02-02 fills the
 * write surface). Plan 02-03 adds the public read-view lookup.
 *
 * The public lookup (getByNicknameForPublicProfile) is a separate method
 * from getByNickname (Plan 02-02, owner-only flows) because
 * auth_service::sanitizeUser is strict by design (strips points /
 * points_frozen / is_admin / is_banned / password_hash for ANY caller),
 * while the public profile View explicitly needs `points` re-injected
 * for the summary header.
 *
 * Per AD-14 ("don't reveal the resource exists"): is_banned = TRUE
 * rows are filtered at the query level so the public profile is hidden
 * for banned users (D-06). The same generic 404 page is rendered for
 * banned, non-existent, case-mismatched, and invalid-character URLs.
 *
 * Per D-15: nickname lookup is case-sensitive. The users table uses
 * utf8mb4_unicode_ci collation (case-insensitive by default), so the
 * lookup uses `BINARY nickname = ?` for the public profile (case-mismatched
 * URLs are 404s, not aliases). Plan 02-02's getByNickname uses
 * LOWER(...) for owner-edit flows and is left untouched here.
 */

declare(strict_types=1);

namespace App\User\Service;

use App\Auth\Service\auth_service;
use App\Support\Db;

class user_service
{
    /**
     * Plan 02-02's case-insensitive nickname lookup for owner-only flows.
     *
     * Wraps User\Model\user_model::findByNickname + auth_service::sanitizeUser.
     * Returns the strict-sanitized row (no points, points_frozen, is_admin,
     * is_banned, password_hash). Plan 02-02 owns this method.
     *
     * @param string $nickname
     * @return array<string,mixed>|null
     */
    public static function getByNickname(string $nickname): ?array
    {
        $row = \App\User\Model\user_model::findByNickname(Db::pdo(), $nickname);
        if ($row === null) {
            return null;
        }
        return auth_service::sanitizeUser($row);
    }

    /**
     * Plan 02-03's case-sensitive public-profile lookup.
     *
     * Same query shape as Plan 02-02's getByNickname (filters is_banned
     * at the SQL level per D-06), but uses BINARY nickname = ? for
     * case-sensitivity (D-15: nickname is locked at registration and
     * preserved in storage; the URL is the literal stored value).
     *
     * Re-injects `points` and `is_verified` AFTER auth_service::sanitizeUser
     * strips them — the public profile summary header renders both
     * fields explicitly. password_hash, is_admin, is_banned, points_frozen
     * are NOT re-injected (T-02-10, T-02-20, T-02-27).
     *
     * @param string $nickname
     * @return array<string,mixed>|null
     */
    public static function getByNicknameForPublicProfile(string $nickname): ?array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT user_id, nickname, full_name, bio, avatar_id, tier, '
            . 'points, is_verified, created_at '
            . 'FROM users '
            . 'WHERE BINARY nickname = ? AND is_banned = FALSE '
            . 'LIMIT 1'
        );
        $stmt->execute([$nickname]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        // Capture the public-visible fields BEFORE sanitizeUser strips
        // them (sanitizeUser is strict-by-design: strips points,
        // points_frozen, is_admin, is_banned, password_hash).
        $publicPoints = (int) $row['points'];
        $publicVerified = (bool) $row['is_verified'];
        // sanitizeUser strips password_hash, is_admin, is_banned,
        // points, points_frozen. After sanitization, re-inject the two
        // public-visible fields the View needs.
        $row = auth_service::sanitizeUser($row);
        $row['points'] = $publicPoints;
        $row['is_verified'] = $publicVerified;
        return $row;
    }

    /**
     * Alias for getByNicknameForPublicProfile. Preserved for callers that
     * reference the public read view by either name (Plan 02-02 Task 1
     * listed getPublicProfile as the canonical alias).
     */
    public static function getPublicProfile(string $nickname): ?array
    {
        return self::getByNicknameForPublicProfile($nickname);
    }
}
