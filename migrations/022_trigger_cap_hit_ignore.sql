-- migrations/022_trigger_cap_hit_ignore.sql
-- Phase 6 audit fix
-- Purpose:  Apply the WHEN (NEW.delta > 0) clause to the
--           trg_points_log_refresh_last_active trigger on already-
--           applied databases. Migration 019 was updated in source
--           but the migrate.php runner never re-applies migrations
--           (uses an .applied marker), so existing DBs still carry
--           the un-conditional trigger. This migration is the
--           patch path for those DBs.
-- Replaces: trg_points_log_refresh_last_active (defined in 019).
-- Reqs:     PTS-08 (on-break), PTS-10 (velocity flag substrate).
-- Depends:  019_users_last_active.sql (the original trigger).
-- Author:   Phase 6 audit (2026-09-05)

-- Same shape as 019: DROP + CREATE, single-statement body, no
-- BEGIN/END block (compatible with the `;`-splitting runner).
-- Native WHEN clause syntax requires MariaDB 10.0.2+ / MySQL 5.7+;
-- project runs MariaDB 11.4.5.
DROP TRIGGER IF EXISTS trg_points_log_refresh_last_active;
CREATE TRIGGER trg_points_log_refresh_last_active BEFORE INSERT ON points_log FOR EACH ROW WHEN (NEW.delta > 0) UPDATE users SET last_active_at = NOW() WHERE user_id = NEW.user_id;