-- migrations/007_cache_rate.sql
-- Phase 2 Plan 02-01
-- Purpose:  Rate-limit state per D-12 + AD-13. rate_key is the bucket key
--           e.g. login:ip:192.168.1.1:2026-09-01-10:30. The INSERT ... ON
--           DUPLICATE KEY UPDATE in Support/RateLimit.php is the atomic
--           check-and-increment. expires_at is the TTL for the periodic
--           cleanup job (Phase 9 cron).
-- AD binds: AD-13, AD-5.
-- Reqs:     AUTH-06 (login rate-limited 5/5min/IP), SEC-01 (layered rate
--           limits), OPS-07 (no new Composer deps).
-- Depends:  002_users_auth.sql.
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS cache_rate (
  rate_key      VARCHAR(190) NOT NULL PRIMARY KEY,
  count         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start  DATETIME NOT NULL,
  expires_at    DATETIME NOT NULL,
  KEY idx_cache_rate_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
