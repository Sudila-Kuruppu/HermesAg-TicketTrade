-- TicketTrade — Login Streaks (Plan 06-03)
--
-- Per D-03 + 06-CONTEXT.md: login_streaks is the AUTHORITATIVE table for
-- the Streak Kings leaderboard (top 10 by current_streak). The denormalized
-- `users.current_streak` / `users.longest_streak` columns are display copies
-- refreshed by the daily cron (user_service::recomputeStreakDisplay).
--
-- Schema (locked per the plan's must_haves):
--   user_id       BIGINT UNSIGNED
--   login_date    DATE          (Asia/Colombo wall-clock date the user logged in)
--   streak_count  SMALLINT UNSIGNED  (consecutive-day count INCLUDING today)
--   updated_at    DATETIME
--
-- UNIQUE KEY uq_user_date (user_id, login_date) — one row per user per day.
-- Idempotent: re-inserting the same (user_id, login_date) is a no-op per the
-- INSERT ... ON DUPLICATE KEY UPDATE pattern in the daily cron.

-- users.current_streak + longest_streak denormalized display columns
-- refreshed by the daily cron from the authoritative login_streaks table.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS current_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0
        AFTER last_unfrozen_at,
    ADD COLUMN IF NOT EXISTS longest_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0
        AFTER current_streak;

CREATE TABLE IF NOT EXISTS login_streaks (
    user_id       BIGINT UNSIGNED NOT NULL,
    login_date    DATE NOT NULL,
    streak_count  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at    DATETIME NOT NULL,
    PRIMARY KEY (user_id, login_date),
    UNIQUE KEY uq_user_date (user_id, login_date),
    CONSTRAINT fk_login_streaks_user
        FOREIGN KEY (user_id) REFERENCES users (user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;