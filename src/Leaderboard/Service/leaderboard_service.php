<?php

/**
 * TicketTrade — Leaderboard\Service\leaderboard_service
 *
 * Plan 06-03. Per AD-2 + PER-05: SOLE writer of the four leaderboard_*
 * summary tables AND the var/leaderboards/*.json cache files. No other
 * code writes these surfaces; the View reads from the cache (with a
 * fallback to the summary table on cold-start before the first daily cron).
 *
 * Public API:
 *   - refreshAll(PDO): array — runs all 4 refresh*() Model methods, returns
 *     rows-affected per board.
 *   - writeJsonCache(PDO, string $cacheDir): array<string,int> — writes the
 *     4 cache files. Returns the 4 filenames written.
 *   - getCached(string $boardSlug): ?array — reads var/leaderboards/{slug}.json
 *     (decoded), or null on miss.
 *   - readSummary(PDO, string $boardSlug): array — direct read from the
 *     summary table (cold-start fallback for the View).
 *
 * Privacy (T-06-13): the SELECT explicitly lists the locked columns
 * (nickname, tier, points). It NEVER reads student_id, full_name (used
 * only as a display fallback in metadata_json for campus_legends), email,
 * or whatsapp. The public leaderboards page only exposes the locked
 * subset to the View.
 */

declare(strict_types=1);

namespace App\Leaderboard\Service;

use App\Leaderboard\Model\leaderboard_model;
use PDO;

class leaderboard_service
{
    /**
     * Locked board slugs (used by getCached + writeJsonCache + readSummary).
     * The order matches the locked page ordering per 06-UI-SPEC.md.
     */
    public const BOARDS = [
        'campus_legends',
        'weekly_risers',
        'category_leaders',
        'streak_kings',
    ];

    /**
     * Run all 4 refresh*() Model methods. Each TRUNCATE+INSERT is idempotent.
     *
     * @return array<string,int> Rows inserted per board.
     */
    public static function refreshAll(PDO $pdo): array
    {
        return [
            'campus_legends' => leaderboard_model::refreshCampusLegends($pdo),
            'weekly_risers' => leaderboard_model::refreshWeeklyRisers($pdo),
            'category_leaders' => leaderboard_model::refreshCategoryLeaders($pdo),
            'streak_kings' => leaderboard_model::refreshStreakKings($pdo),
        ];
    }

    /**
     * Write the 4 JSON cache files from the summary tables (post-refresh).
     *
     * Files written:
     *   {cacheDir}/campus_legends.json
     *   {cacheDir}/weekly_risers.json
     *   {cacheDir}/category_leaders.json
     *   {cacheDir}/streak_kings.json
     *
     * Each file shape:
     *   {
     *     "generated_at": "2026-09-04 12:34:56",
     *     "rows": [
     *       {"rank": 1, "user_id": 7, "nickname": "alice", "tier": "S",
     *        "score": 1820, "metadata": {...}},
     *       ...
     *     ]
     *   }
     *
     * @return array<string,string> The 4 filenames written (basename only).
     */
    public static function writeJsonCache(PDO $pdo, string $cacheDir): array
    {
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $written = [];
        $generatedAt = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');

        foreach (self::BOARDS as $slug) {
            $rows = self::readSummary($pdo, $slug);
            $payload = [
                'generated_at' => $generatedAt,
                'rows' => $rows,
            ];
            $path = rtrim($cacheDir, '/') . '/' . $slug . '.json';
            file_put_contents(
                $path,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
            $written[$slug] = basename($path);
        }
        return $written;
    }

    /**
     * Read a single board's cached JSON file.
     *
     * @return ?array{generated_at:string,rows:array<int,array<string,mixed>>}
     */
    public static function getCached(string $boardSlug): ?array
    {
        if (!in_array($boardSlug, self::BOARDS, true)) {
            return null;
        }
        $path = self::cacheDir() . '/' . $boardSlug . '.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
            return null;
        }
        return $decoded;
    }

    /**
     * Direct read from the summary table (fallback when the JSON cache
     * has never been written — cold start before the first daily cron).
     *
     * Returns the same shape the View expects. Selects the locked
     * columns explicitly: user_id, nickname, tier. NEVER student_id,
     * email, whatsapp.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function readSummary(PDO $pdo, string $boardSlug): array
    {
        switch ($boardSlug) {
            case 'campus_legends':
                return self::readSimple($pdo, 'leaderboard_campus_legends', 'score DESC, user_id ASC');

            case 'weekly_risers':
                return self::readSimple($pdo, 'leaderboard_weekly_risers', 'score DESC, user_id ASC');

            case 'streak_kings':
                return self::readSimple($pdo, 'leaderboard_streak_kings', 'score DESC, user_id ASC');

            case 'category_leaders':
                return self::readCategoryLeaders($pdo);

            default:
                return [];
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function readSimple(PDO $pdo, string $table, string $order): array
    {
        $stmt = $pdo->prepare(
            'SELECT s.user_id, s.score, s.rank_position, s.metadata_json, '
            . 'u.nickname, u.tier '
            . 'FROM ' . $table . ' s '
            . 'JOIN users u ON u.user_id = s.user_id '
            . 'ORDER BY ' . $order . ' LIMIT 20'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'rank' => (int) $r['rank_position'],
                'user_id' => (int) $r['user_id'],
                'nickname' => (string) $r['nickname'],
                'tier' => (string) $r['tier'],
                'score' => (int) $r['score'],
                'metadata' => $r['metadata_json'] !== null
                    ? json_decode((string) $r['metadata_json'], true)
                    : null,
            ];
        }
        return $out;
    }

    /**
     * Category Leaders has a composite PK (category_id, user_id) and the
     * rank_position is per-category. Flatten with category_id in the row.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function readCategoryLeaders(PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT s.user_id, s.category_id, s.score, s.rank_position, s.metadata_json, '
            . 'u.nickname, u.tier '
            . 'FROM leaderboard_category_leaders s '
            . 'JOIN users u ON u.user_id = s.user_id '
            . 'ORDER BY s.category_id ASC, s.rank_position ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $meta = $r['metadata_json'] !== null
                ? json_decode((string) $r['metadata_json'], true)
                : null;
            $out[] = [
                'rank' => (int) $r['rank_position'],
                'user_id' => (int) $r['user_id'],
                'category_id' => (int) $r['category_id'],
                'category_name' => is_array($meta) ? (string) ($meta['category_name'] ?? '') : '',
                'nickname' => (string) $r['nickname'],
                'tier' => (string) $r['tier'],
                'score' => (int) $r['score'],
                'metadata' => $meta,
            ];
        }
        return $out;
    }

    /**
     * Default cache directory — var/leaderboards/ at the project root.
     * Test code can override by passing $cacheDir to writeJsonCache().
     */
    public static function cacheDir(): string
    {
        return APP_ROOT . '/var/leaderboards';
    }
}
