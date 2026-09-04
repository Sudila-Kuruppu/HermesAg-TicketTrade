-- TicketTrade — Leaderboard Summary Tables (Plan 06-03)
--
-- Per AD-2 + AD-10 + D-04 + D-05 + PER-05: the Leaderboard bounded context
-- is the SOLE writer of these four summary tables. The leaderboard_service
-- truncates + inserts from the source-of-truth tables (users, points_log,
-- tickets, listings) and the View reads from them (or the JSON cache that
-- mirrors them).
--
-- All four tables share the locked column shape per the plan's must_haves:
--   user_id BIGINT UNSIGNED
--   score INT
--   rank_position INT (computed by ROW_NUMBER() at insert time)
--   metadata_json JSON (per-board payload — category_id, weekly_range, etc.)
--   snapshot_at DATETIME (when the daily cron ran)
--
-- Composite indexes match the ORDER BY shape of each board (D-05 tiebreaker).
-- The four boards:
--   1. leaderboard_campus_legends   — top 20 tier S users, ORDER BY points DESC, user_id ASC
--   2. leaderboard_weekly_risers    — top 10 with weekly_points >= 50
--   3. leaderboard_category_leaders — top 3 per category by successful sales
--   4. leaderboard_streak_kings     — top 10 by current_streak

CREATE TABLE IF NOT EXISTS leaderboard_campus_legends (
    user_id        BIGINT UNSIGNED NOT NULL,
    score          INT NOT NULL,
    rank_position  INT NOT NULL,
    metadata_json  JSON NULL,
    snapshot_at    DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    KEY idx_score_rank (score, user_id),
    CONSTRAINT fk_lb_legends_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leaderboard_weekly_risers (
    user_id        BIGINT UNSIGNED NOT NULL,
    score          INT NOT NULL,
    rank_position  INT NOT NULL,
    metadata_json  JSON NULL,
    snapshot_at    DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    KEY idx_score (score, user_id),
    CONSTRAINT fk_lb_weekly_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leaderboard_category_leaders (
    user_id        BIGINT UNSIGNED NOT NULL,
    category_id    BIGINT UNSIGNED NOT NULL,
    score          INT NOT NULL,
    rank_position  INT NOT NULL,
    metadata_json  JSON NULL,
    snapshot_at    DATETIME NOT NULL,
    PRIMARY KEY (category_id, user_id),
    KEY idx_cat_score (category_id, score, user_id),
    CONSTRAINT fk_lb_cat_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lb_cat_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leaderboard_streak_kings (
    user_id        BIGINT UNSIGNED NOT NULL,
    score          INT NOT NULL,
    rank_position  INT NOT NULL,
    metadata_json  JSON NULL,
    snapshot_at    DATETIME NOT NULL,
    PRIMARY KEY (user_id),
    KEY idx_score_streak (score, user_id),
    CONSTRAINT fk_lb_streak_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;