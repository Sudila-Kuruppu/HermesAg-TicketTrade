-- migrations/005_password_resets.sql
-- Phase 2 Plan 02-01
-- Purpose:  Password reset tokens (D-07) + the points_log table for the
--           +50 verification bonus stub (AD-10).
--           - password_resets.token_hash is CHAR(64) because it stores
--             hash('sha256', $rawToken) which always yields 64 hex chars
--             (Pitfall 12).
--           - points_log.event_uuid is CHAR(36) because it stores a
--             UUID v7 string from ramsey/uuid (RESEARCH UUID line 137).
--             The UNIQUE KEY on event_uuid closes the duplicate-NULL
--             hole (ARCHITECTURE-SPINE.md Reliability bullet).
-- AD binds: AD-10 (sole writer of points_log + users.points), AD-13.
-- Reqs:     AUTH-04 (password rules), AUTH-05 (route guards),
--           AUTH-06 (login rate-limited 5/5min/IP).
-- Depends:  002_users_auth.sql.
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS password_resets (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME NULL,
  created_at   DATETIME NOT NULL,
  KEY idx_password_resets_user (user_id),
  UNIQUE KEY uniq_password_resets_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS points_log (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED NOT NULL,
  delta           SMALLINT NOT NULL,
  reference_type  VARCHAR(40) NOT NULL,
  reference_id    BIGINT UNSIGNED NULL,
  balance_after   INT NOT NULL,
  event_uuid      CHAR(36) NOT NULL,
  metadata        JSON NULL,
  event_at        DATETIME NOT NULL,
  UNIQUE KEY uniq_event (event_uuid),
  KEY idx_points_log_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
