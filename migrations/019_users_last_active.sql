-- migrations/019_users_last_active.sql
-- Phase 6 Plan 06-01
-- Purpose:  Add users.last_active_at + the BEFORE INSERT trigger on
--           points_log that refreshes it (D-03). last_active_at is the
--           denormalized "is this user active in the last 14 days"
--           column; the trigger guarantees every successful points_log
--           write counts as activity. The trigger is the SOLE writer of
--           last_active_at on the points_log path; the auth login path
--           uses auth_service::recordLogin (Plan 06-02) which writes the
--           column directly.
-- AD binds: AD-10 (points sole writer); the trigger is migration-owned.
-- Reqs:     PTS-08 (on-break), PTS-10 (velocity flag substrate).
-- Depends:  002_users_auth.sql, 005_password_resets.sql.
-- Author:   Phase 6 (2026-09-04)

-- Native IF NOT EXISTS (MariaDB 10.0.2+ / MySQL 8.0+). The earlier
-- Phase 4 migration 014 uses an INFORMATION_SCHEMA pre-check + PREPARE/
-- EXECUTE pattern for portability with older MariaDB, but the runner
-- splits on `;` and PDO's unbuffered queries leave a pending result
-- open after the (SELECT COUNT(*)) subquery. Native IF NOT EXISTS
-- is a single DDL statement with no result set and is portable on
-- every MariaDB 10.0.2+ / MySQL 8.0+ deploy. The 014 pattern remains
-- intact for legacy coverage.

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active_at DATETIME NULL AFTER points_frozen;
ALTER TABLE users ADD COLUMN IF NOT EXISTS frozen_at DATETIME NULL AFTER last_active_at;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_unfrozen_at DATETIME NULL AFTER frozen_at;
CREATE INDEX IF NOT EXISTS idx_users_last_active ON users (last_active_at);

-- Trigger that refreshes users.last_active_at on every points_log
-- INSERT. The trigger is the canonical writer on the gamification
-- path; PHP code MUST NOT update the column from points_service.
-- DROP TRIGGER IF EXISTS + CREATE TRIGGER is the canonical idempotent
-- shape; the single-statement trigger body (no BEGIN/END block) keeps
-- the migration compatible with the `;`-splitting runner.
--
-- Anti-fraud cap-hit rows (velocity_cap_hit, pair_cap_hit) are zero-
-- delta and MUST NOT refresh last_active_at, otherwise a capped-out
-- user spamming hundreds of suppressed events per day would stay
-- "fresh" forever and the 14-day on-break pill (PTS-08) would never
-- trigger for them. Implementation: inline IF() expression that
-- returns NOW() when NEW.delta > 0 and the existing last_active_at
-- otherwise. Single statement, no compound block, no DELIMITER
-- change.
--
-- The earlier WHEN (NEW.delta > 0) clause attempt (commit 6bf4312)
-- was rejected by MariaDB 11.4.5 with `ERROR 1064 ... near 'WHEN
-- (NEW.delta > 0) UPDATE users ...'`. MariaDB does not support a
-- standalone WHEN predicate in a CREATE TRIGGER body that is itself
-- an UPDATE statement; the IF() expression keeps the same
-- zero-delta suppression semantics while staying a single DDL
-- statement the `;`-splitter can run.
DROP TRIGGER IF EXISTS trg_points_log_refresh_last_active;
CREATE TRIGGER trg_points_log_refresh_last_active BEFORE INSERT ON points_log FOR EACH ROW UPDATE users SET last_active_at = IF(NEW.delta > 0, NOW(), last_active_at) WHERE user_id = NEW.user_id;