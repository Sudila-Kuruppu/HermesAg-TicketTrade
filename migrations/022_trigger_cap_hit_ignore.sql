-- migrations/022_trigger_cap_hit_ignore.sql
-- Phase 6 audit fix
-- Purpose:  Patch the trg_points_log_refresh_last_active trigger on
--           already-applied databases so cap-hit zero-delta rows
--           (velocity_cap_hit, pair_cap_hit) do not refresh
--           last_active_at. Migration 019 is the source-of-truth
--           for fresh installs; this file is the patch path for
--           existing DBs whose trigger was created with the earlier
--           unconditional version.
-- Replaces: trg_points_log_refresh_last_active (defined in 019).
-- Reqs:     PTS-08 (on-break), PTS-10 (velocity flag substrate).
-- Depends:  019_users_last_active.sql (the original trigger).
-- Author:   Phase 6 audit (2026-09-05; revised 2026-09-05 IF() form)

-- Same shape as 019: DROP + CREATE, single-statement body, no
-- BEGIN/END block (compatible with the `;`-splitting runner).
--
-- Implementation: inline IF() expression that returns NOW() when
-- NEW.delta > 0 and the existing last_active_at otherwise. Single
-- DDL statement, no DELIMITER change.
--
-- The earlier attempt at this migration used a WHEN (NEW.delta > 0)
-- clause in the trigger body. MariaDB 11.4.5 rejects that syntax
-- (`ERROR 1064 ... near 'WHEN (NEW.delta > 0) UPDATE users ...'`)
-- because MariaDB does not allow a standalone WHEN predicate inside
-- a CREATE TRIGGER whose body is itself an UPDATE statement. The
-- IF() expression preserves the zero-delta suppression semantics
-- and stays a single statement the `;`-splitter can apply.
DROP TRIGGER IF EXISTS trg_points_log_refresh_last_active;
CREATE TRIGGER trg_points_log_refresh_last_active BEFORE INSERT ON points_log FOR EACH ROW UPDATE users SET last_active_at = IF(NEW.delta > 0, NOW(), last_active_at) WHERE user_id = NEW.user_id;