<?php
/**
 * Phase 6 — Integration Test Fixtures
 *
 * Extends the Phase 4 fixtures to additionally TRUNCATE the Phase 6
 * tables added in Plan 06-01+ (none yet from this plan; the truncate
 * patterns from Phase 04 use try/catch to ignore missing tables so
 * the leaderboard_* + login_streaks tables from Plan 06-03+ will
 * just work when added later).
 *
 * Mirrors the Phase 05 Fixtures shape.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06\Fixtures;

use App\Tests\Integration\Phase04\Fixtures\Fixtures as Phase04Fixtures;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

abstract class Fixtures extends Phase04Fixtures
{
    // No new tables from Plan 06-01 itself (the schema additions land
    // in migrations 018/019 and don't introduce TRUNCATE-needing
    // tables). Plan 06-03 adds leaderboard_* and login_streaks; the
    // truncate-IGNORE pattern from Phase 04 will handle them when
    // those plans ship.
}