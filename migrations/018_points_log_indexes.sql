-- migrations/018_points_log_indexes.sql
-- Phase 6 Plan 06-01
-- Purpose:  Add composite indexes on points_log so the velocity and
--           same-pair cap checks (Phase 6 Plan 06-02) are O(log n)
--           reads:
--             * idx_points_user_event (user_id, event_at DESC) — the
--               velocity window reads ("sum for this user in the last
--               hour / day") and the on-break detection join.
--             * idx_points_pair (user_id, reference_id, event_at DESC)
--               — the same-pair cap check ("how many counted rows for
--               this (buyer,seller,reference_id) tuple today").
--           No new columns; no rename; the existing
--           KEY idx_points_log_user (user_id) from migration 005 is
--           superseded by idx_points_user_event for the velocity query
--           but is left in place (PostgreSQL/MariaDB can't drop it
--           without a redundant (user_id) follow-up; the query planner
--           picks the composite when the WHERE filters both columns).
-- AD binds: AD-10 (points sole writer).
-- Reqs:     PTS-01..04 (game engine substrate), PER-05 (indexed reads).
-- Depends:  005_password_resets.sql (creates points_log).
-- Author:   Phase 6 (2026-09-04)

-- Idempotent: guard via information_schema.STATISTICS. MariaDB does
-- not support `CREATE INDEX IF NOT EXISTS` until 10.0.2+, so we
-- pre-check like 014_users_redemption_count.sql does.

SET @idx_user_event_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'points_log'
    AND INDEX_NAME = 'idx_points_user_event'
);

SET @sql = IF(@idx_user_event_exists = 0,
  'CREATE INDEX idx_points_user_event ON points_log (user_id, event_at DESC)',
  'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_pair_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'points_log'
    AND INDEX_NAME = 'idx_points_pair'
);

SET @sql = IF(@idx_pair_exists = 0,
  'CREATE INDEX idx_points_pair ON points_log (user_id, reference_id, event_at DESC)',
  'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;