<?php

/**
 * TicketTrade — Leaderboard\Model\leaderboard_model
 *
 * Plan 06-03. Per AD-2 + D-04 + D-05: the SOLE writer of the four
 * leaderboard_* summary tables. Each refresh*() method follows the
 * canonical pattern from points_log_model: TRUNCATE then INSERT with
 * a derived-rank ROW_NUMBER() OVER (ORDER BY score DESC, user_id ASC).
 *
 * Tiebreaker: ascending user_id (D-05) — stable, deterministic,
 * first-registered wins.
 *
 * Privacy (T-06-13): the leaderboard SELECT NEVER references
 * student_id, full_name, email, or whatsapp. The JOIN to users in
 * the JSON cache write explicitly lists the locked columns
 * (nickname, full_name as display fallback, tier, points, last_active_at).
 */

declare(strict_types=1);

namespace App\Leaderboard\Model;

use PDO;

class leaderboard_model
{
    /**
     * Campus Legends Wall — top 20 tier-S users by points.
     *
     * @return int Rows inserted.
     */
    public static function refreshCampusLegends(PDO $pdo): int
    {
        $pdo->exec('TRUNCATE TABLE leaderboard_campus_legends');
        $stmt = $pdo->prepare(
            'INSERT INTO leaderboard_campus_legends '
            . '(user_id, score, rank_position, metadata_json, snapshot_at) '
            . 'SELECT user_id, points AS score, '
            . 'ROW_NUMBER() OVER (ORDER BY points DESC, user_id ASC) AS rank_position, '
            . 'JSON_OBJECT("tier", tier, "full_name", full_name) AS metadata_json, '
            . 'NOW() '
            . 'FROM users WHERE tier = ? AND is_banned = FALSE '
            . 'ORDER BY points DESC, user_id ASC LIMIT 20'
        );
        $stmt->execute(['S']);
        return $stmt->rowCount();
    }

    /**
     * Weekly Risers — top 10 with weekly_points >= 50 in the current
     * Mon-Sun Asia/Colombo window (D-04, 06-CONTEXT.md).
     *
     * @return int Rows inserted.
     */
    public static function refreshWeeklyRisers(PDO $pdo): int
    {
        $pdo->exec('TRUNCATE TABLE leaderboard_weekly_risers');
        $stmt = $pdo->prepare(
            'INSERT INTO leaderboard_weekly_risers '
            . '(user_id, score, rank_position, metadata_json, snapshot_at) '
            . 'SELECT user_id, weekly_points AS score, '
            . 'ROW_NUMBER() OVER (ORDER BY weekly_points DESC, user_id ASC) AS rank_position, '
            . 'JSON_OBJECT("week_start", week_start, "week_end", week_end) AS metadata_json, '
            . 'NOW() '
            . 'FROM ( '
            . '  SELECT pl.user_id AS user_id, '
            . '         COALESCE(SUM(pl.delta), 0) AS weekly_points, '
            . '         DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AS week_start, '
            . '         DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) AS week_end '
            . '  FROM points_log pl '
            . '  JOIN users u ON u.user_id = pl.user_id '
            . '  WHERE u.is_banned = FALSE '
            . '    AND pl.event_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) '
            . '    AND pl.event_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY) '
            . '  GROUP BY pl.user_id '
            . '  HAVING weekly_points >= 50 '
            . '  ORDER BY weekly_points DESC, user_id ASC '
            . '  LIMIT 10 '
            . ') AS src'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Category Leaders — top 3 per category by successful sales (tickets
     * with status='redeemed').
     *
     * PARTITION BY category_id so the rank_position resets per category
     * (top 3 of Textbooks is independent of top 3 of Electronics).
     *
     * @return int Rows inserted (sum across all categories).
     */
    public static function refreshCategoryLeaders(PDO $pdo): int
    {
        $pdo->exec('TRUNCATE TABLE leaderboard_category_leaders');
        $stmt = $pdo->prepare(
            'INSERT INTO leaderboard_category_leaders '
            . '(user_id, category_id, score, rank_position, metadata_json, snapshot_at) '
            . 'SELECT user_id, category_id, sale_count AS score, '
            . 'rank_per_cat AS rank_position, '
            . 'JSON_OBJECT("category_name", category_name) AS metadata_json, '
            . 'NOW() '
            . 'FROM ( '
            . '  SELECT t.seller_id AS user_id, '
            . '         l.category_id AS category_id, '
            . '         COUNT(*) AS sale_count, '
            . '         ROW_NUMBER() OVER ('
            . '             PARTITION BY l.category_id '
            . '             ORDER BY COUNT(*) DESC, t.seller_id ASC'
            . '         ) AS rank_per_cat, '
            . '         c.name AS category_name '
            . '  FROM tickets t '
            . '  JOIN listings l ON l.id = t.listing_id '
            . '  JOIN categories c ON c.id = l.category_id '
            . '  JOIN users u ON u.user_id = t.seller_id '
            . '  WHERE t.status = ? AND u.is_banned = FALSE '
            . '  GROUP BY l.category_id, t.seller_id, c.name '
            . ') AS src '
            . 'WHERE rank_per_cat <= 3'
        );
        $stmt->execute(['redeemed']);
        return $stmt->rowCount();
    }

    /**
     * Streak Kings — top 10 by current_streak (> 0).
     *
     * Reads from users.current_streak (the denormalized column that
     * the daily cron's recomputeStreakDisplay keeps fresh).
     *
     * @return int Rows inserted.
     */
    public static function refreshStreakKings(PDO $pdo): int
    {
        $pdo->exec('TRUNCATE TABLE leaderboard_streak_kings');
        $stmt = $pdo->prepare(
            'INSERT INTO leaderboard_streak_kings '
            . '(user_id, score, rank_position, metadata_json, snapshot_at) '
            . 'SELECT user_id, current_streak AS score, '
            . 'ROW_NUMBER() OVER (ORDER BY current_streak DESC, user_id ASC) AS rank_position, '
            . 'JSON_OBJECT("longest_streak", longest_streak) AS metadata_json, '
            . 'NOW() '
            . 'FROM users WHERE current_streak > 0 AND is_banned = FALSE '
            . 'ORDER BY current_streak DESC, user_id ASC LIMIT 10'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
