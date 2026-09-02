-- migrations/014_users_redemption_count.sql
-- Phase 4 Plan 04-01
-- Purpose:  Add users.redeemed_count for the FR-PTS-007 new-account
--           halving check. points_service::awardTransaction() reads
--           this column to decide whether to apply the 50% multiplier
--           (the first 5 redemptions per user earn half points).
-- AD binds: AD-10 (points sole writer) — column is updated only by
--           points_service::awardTransaction() inside the same
--           transaction as the points_log INSERT.
-- Reqs:     FR-PTS-007.
-- Depends:  002_users_auth.sql.
-- Author:   Phase 4 (2026-09-02)

-- Idempotent: skip if the column already exists. MariaDB does not
-- support `ADD COLUMN IF NOT EXISTS` until 10.0.2+; use INFORMATION_SCHEMA
-- guard for portability across test + prod MariaDB versions.
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'redeemed_count'
);

SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN redeemed_count INT NOT NULL DEFAULT 0 AFTER points_frozen',
  'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
