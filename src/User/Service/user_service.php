<?php

/**
 * TicketTrade — User\Service\user_service
 *
 * Plan 02-02 owns the write surface (validateWhatsApp, validateAvatarId,
 * updateProfile, randomAvatarId). Plan 02-03 owns the public read view
 * (getByNicknameForPublicProfile, getPublicProfile). getByNickname
 * (case-insensitive) is shared and used by the owner-only edit flow.
 *
 * Per D-15: nickname is locked at registration. updateProfile() accepts
 * ONLY the 4 whitelisted fields (full_name, bio, whatsapp, avatar_id);
 * any other key in $fields is silently dropped.
 *
 * Per SEC-08: WhatsApp regex is `^(\+94|0)7[0-9]{8}$` — the canonical
 * Sri Lankan mobile pattern.
 *
 * Per Pitfall 11: avatar_id is (int) cast + clamped 1..12.
 *
 * Plan 06-03 ADDS:
 *   - getRecentActivityForProfile() — the Recent activity section on the
 *     owner Profile (D-07). Delegates to points_log_model::recentForUser.
 *   - recomputeStreakDisplay() — the daily-cron helper. For each user
 *     with a sessions row today, UPSERT into login_streaks, recompute
 *     users.current_streak / longest_streak, and award the streak bonus
 *     (points_service::awardStreakBonus) when the streak crosses the
 *     7-day or 30-day threshold.
 */

declare(strict_types=1);

namespace App\User\Service;

use App\Auth\Service\auth_service;
use App\Points\Model\points_log_model;
use App\Points\Service\points_service;
use App\Support\Db;
use InvalidArgumentException;
use PDO;

class user_service
{
    /**
     * Plan 02-02's case-insensitive nickname lookup for owner-only flows.
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
        $publicPoints = (int) $row['points'];
        $publicVerified = (bool) $row['is_verified'];
        $row = auth_service::sanitizeUser($row);
        $row['points'] = $publicPoints;
        $row['is_verified'] = $publicVerified;
        return $row;
    }

    /**
     * Alias for getByNicknameForPublicProfile.
     */
    public static function getPublicProfile(string $nickname): ?array
    {
        return self::getByNicknameForPublicProfile($nickname);
    }

    /**
     * Plan 02-02's owner profile lookup (by user_id).
     *
     * @return array<string,mixed>|null
     */
    public static function getById(int $userId): ?array
    {
        $row = \App\User\Model\user_model::findById(Db::pdo(), $userId);
        if ($row === null) {
            return null;
        }
        return auth_service::sanitizeUser($row);
    }

    /**
     * Plan 02-02's WhatsApp validator.
     *
     * Regex: `^(\+94|0)7[0-9]{8}$` (SEC-08, AGENTS.md Constraints).
     * Returns the canonicalized value if valid.
     * Returns null if empty (whatsapp is optional in the profile).
     * Throws InvalidArgumentException if the format is invalid.
     */
    public static function validateWhatsApp(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^(\+94|0)7[0-9]{8}$/', $s)) {
            throw new InvalidArgumentException(
                'Use a Sri Lankan mobile (e.g., +94771234567 or 0771234567).'
            );
        }
        return $s;
    }

    /**
     * Plan 02-02's avatar_id validator + clamp.
     *
     * Per Pitfall 11: (int) cast + clamp 1..12. Out-of-range values
     * are clamped to the nearest valid value, NOT rejected.
     */
    public static function validateAvatarId(mixed $v): int
    {
        return max(1, min(12, (int) $v));
    }

    /**
     * Plan 02-02's random avatar_id generator (D-19).
     */
    public static function randomAvatarId(): int
    {
        return random_int(1, 12);
    }

    /**
     * Plan 02-02's profile-update with strict field whitelist.
     *
     * Accepts ONLY full_name, bio, whatsapp, avatar_id. Any other key
     * in $fields is silently dropped (whitelist at the Service layer,
     * not the View).
     *
     * @param int $userId
     * @param array<string,mixed> $fields
     * @return array{ok:bool,updated?:array<string,mixed>,error?:array}
     */
    public static function updateProfile(int $userId, array $fields): array
    {
        $allowed = ['full_name', 'bio', 'whatsapp', 'avatar_id'];
        $clean = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $clean[$k] = $fields[$k];
            }
        }
        if (empty($clean)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'No editable fields provided.',
                ],
            ];
        }

        // Validate WhatsApp if present
        if (array_key_exists('whatsapp', $clean)) {
            try {
                $clean['whatsapp'] = self::validateWhatsApp($clean['whatsapp']);
            } catch (InvalidArgumentException $e) {
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_VALIDATION',
                        'message' => $e->getMessage(),
                        'fields' => ['whatsapp' => $e->getMessage()],
                    ],
                ];
            }
        }
        // Clamp avatar_id if present
        if (array_key_exists('avatar_id', $clean)) {
            $clean['avatar_id'] = self::validateAvatarId($clean['avatar_id']);
        }
        // full_name: non-empty
        if (array_key_exists('full_name', $clean) && trim((string) $clean['full_name']) === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Full name is required.',
                    'fields' => ['full_name' => 'Full name is required.'],
                ],
            ];
        }
        // bio: ≤ 500 chars
        if (array_key_exists('bio', $clean) && strlen((string) $clean['bio']) > 500) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Bio must be 500 characters or fewer.',
                    'fields' => ['bio' => 'Bio must be 500 characters or fewer.'],
                ],
            ];
        }

        $ok = \App\User\Model\user_model::updateProfile(Db::pdo(), $userId, $clean);
        if (!$ok) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ];
        }
        return ['ok' => true, 'updated' => $clean];
    }

    /**
     * Plan 06-03: Profile Recent activity section (D-07).
     *
     * Returns up to $limit rows from points_log for the given user,
     * newest first. The shape matches what the View expects (delta,
     * reference_type, event_at, metadata_decoded). Delegates to
     * points_log_model::recentForUser() which already returns the
     * locked projection.
     *
     * @return array<int, array{delta:int,reference_type:string,event_at:string,metadata:?array<string,mixed>}>
     */
    public static function getRecentActivityForProfile(int $userId, int $limit = 5): array
    {
        $rows = points_log_model::recentForUser(Db::pdo(), $userId, $limit);
        $out = [];
        foreach ($rows as $r) {
            $meta = null;
            if ($r['metadata'] !== null && $r['metadata'] !== '') {
                $decoded = json_decode((string) $r['metadata'], true);
                $meta = is_array($decoded) ? $decoded : null;
            }
            $out[] = [
                'delta' => $r['delta'],
                'reference_type' => $r['reference_type'],
                'event_at' => $r['event_at'],
                'metadata' => $meta,
            ];
        }
        return $out;
    }

    /**
     * Plan 06-03: daily-cron streak recompute.
     *
     * For each user with a sessions row whose last_seen is on or after
     * the current date (Asia/Colombo wall clock), UPSERT into
     * login_streaks(user_id, login_date, streak_count), then UPDATE
     * users.current_streak / longest_streak to match. When the
     * recomputed streak crosses the 7-day or 30-day threshold, call
     * points_service::awardStreakBonus() to write the bonus row.
     *
     * Idempotency: re-running on the same day produces the same end
     * state. The login_streaks UPSERT uses ON DUPLICATE KEY UPDATE so
     * a re-run never duplicates a row; the streak bonus is gated by
     * a `points_log` existence check (`streak_7day` / `streak_30day`
     * already awarded for this user) so re-running the cron — or
     * firing the manual trigger after the auto-run — never duplicates
     * the bonus. The streak bonus is a lifetime milestone, not
     * per-crossing.
     *
     * @return array{processed:int, awards: array<int, array{user_id:int, streak_days:int, delta:int}>}
     */
    public static function recomputeStreakDisplay(PDO $pdo): array
    {
        $awards = [];
        $processed = 0;
        try {
            // Find users who logged in recently (Asia/Colombo wall-clock).
            //
            // The 06-REVIEW CR-02 audit changed the predicate from
            // `DATE(s.last_seen) = today` to
            // `last_seen >= today - 1 day`. Reason: sessions.last_seen
            // is bumped by session_model::touch() in a 5-minute window,
            // so an idle user whose cookie persisted but who hasn't
            // loaded a page today has last_seen = yesterday — yet their
            // cookie session is still valid and they SHOULD receive
            // today's login_streaks row + streak continuation. The
            // 48-hour window (`yesterday OR today`) covers the realistic
            // "back-tab" case without breaking cold-start (sessions
            // remains the source of truth; login_streaks is updated
            // AFTER the loop).
            $today = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
                ->format('Y-m-d');
            $yesterday = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
                ->modify('-1 day')
                ->format('Y-m-d');
            $rows = $pdo->prepare(
                'SELECT DISTINCT s.user_id AS user_id '
                . 'FROM sessions s JOIN users u ON u.user_id = s.user_id '
                . 'WHERE s.last_seen >= ? AND u.is_banned = FALSE'
            );
            $rows->execute([$yesterday]);
            $userIds = $rows->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $pdo->prepare(
                'INSERT INTO login_streaks (user_id, login_date, streak_count, updated_at) '
                . 'VALUES (?, ?, 1, NOW()) '
                . 'ON DUPLICATE KEY UPDATE updated_at = NOW()'
            );
            $updateUserStmt = $pdo->prepare(
                'UPDATE users SET current_streak = ?, longest_streak = ?, updated_at = NOW() '
                . 'WHERE user_id = ?'
            );
            $longestStmt = $pdo->prepare(
                'SELECT longest_streak FROM users WHERE user_id = ?'
            );
            $bonusAwardedStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM points_log WHERE user_id = ? AND reference_type = ?'
            );

            foreach ($userIds as $row) {
                $userId = (int) $row['user_id'];
                $insertStmt->execute([$userId, $today]);

                // Compute the consecutive-day count: scan backward from
                // today and count how many distinct login_date rows are
                // contiguous. A break in the chain resets the count to 1.
                $consecutive = self::consecutiveLoginDays($pdo, $userId, $today);

                $longestStmt->execute([$userId]);
                $prevLongest = (int) $longestStmt->fetchColumn();
                $newLongest = max($prevLongest, $consecutive);

                $updateUserStmt->execute([$consecutive, $newLongest, $userId]);
                $processed++;

                // Award the streak bonus at thresholds. The bonus is a
                // lifetime milestone (FR-PTS-001 rows 7-8), so the
                // points_log existence check below is what guarantees
                // no duplicate bonus on re-runs. Without this guard,
                // a manual /admin/cron/daily trigger after the auto-run
                // would re-award +15 / +50 for the same crossing.
                if ($consecutive === 7 || $consecutive === 30) {
                    $refType = $consecutive === 7 ? 'streak_7day' : 'streak_30day';
                    $bonusAwardedStmt->execute([$userId, $refType]);
                    if ((int) $bonusAwardedStmt->fetchColumn() > 0) {
                        continue; // already awarded; lifetime milestone
                    }
                    $res = points_service::awardStreakBonus($userId, $consecutive);
                    if (($res['ok'] ?? false) === true && !empty($res['data']['delta'])) {
                        $awards[] = [
                            'user_id' => $userId,
                            'streak_days' => $consecutive,
                            'delta' => (int) $res['data']['delta'],
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[user_service::recomputeStreakDisplay] ' . $e->getMessage());
        }
        return ['processed' => $processed, 'awards' => $awards];
    }

    /**
     * Helper: count consecutive login days ending at $today (inclusive).
     * Used by recomputeStreakDisplay. Public-static so tests can call it
     * directly.
     *
     * @return int >= 1
     */
    public static function consecutiveLoginDays(PDO $pdo, int $userId, string $today): int
    {
        $stmt = $pdo->prepare(
            'SELECT login_date FROM login_streaks '
            . 'WHERE user_id = ? AND login_date <= ? '
            . 'ORDER BY login_date DESC LIMIT 60'
        );
        $stmt->execute([$userId, $today]);
        $dates = array_map(
            static fn ($r) => (string) $r['login_date'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
        if (empty($dates)) {
            return 0;
        }
        $count = 0;
        $cursor = new \DateTime($today, new \DateTimeZone('Asia/Colombo'));
        foreach ($dates as $d) {
            $expected = $cursor->format('Y-m-d');
            if ($d !== $expected) {
                break;
            }
            $count++;
            $cursor->modify('-1 day');
        }
        return $count;
    }
}
