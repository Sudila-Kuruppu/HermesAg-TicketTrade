-- migrations/004_email_verifications.sql
-- Phase 2 Plan 02-01
-- Purpose:  Email verification tokens for the simulated verify flow (D-02 /
--           D-03). The token_hash column is CHAR(64) because it stores
--           hash('sha256', $rawToken) which always yields 64 hex chars
--           (Pitfall 12). The used_at column is the audit trail.
-- AD binds: AD-13 (auth primitives).
-- Reqs:     AUTH-01 (simulated email verification).
-- Depends:  002_users_auth.sql
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS email_verifications (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  expires_at   DATETIME NOT NULL,
  used_at      DATETIME NULL,
  created_at   DATETIME NOT NULL,
  KEY idx_email_verifications_user (user_id),
  UNIQUE KEY uniq_email_verifications_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
