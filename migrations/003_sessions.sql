-- migrations/003_sessions.sql
-- Phase 2 Plan 02-01
-- Purpose:  DB-backed sessions per D-05 + AD-13.
--           session_id is CHAR(48) to match session.sid_length=48 with
--           sid_bits_per_char=5 (Pitfall 13). The IP is VARBINARY(16) so
--           both IPv4 and IPv6 fit without padding garbage.
-- AD binds: AD-13 (auth/session shape), AD-5 (utf8mb4 / InnoDB).
-- Reqs:     AUTH-02 (session persists across refresh), AUTH-03 (logout
--           destroys session), AUTH-04 (bcrypt + secure session).
-- Depends:  002_users_auth.sql
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS sessions (
  session_id   CHAR(48) NOT NULL PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  last_seen    DATETIME NOT NULL,
  ip           VARBINARY(16) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL,
  KEY idx_sessions_user (user_id),
  KEY idx_sessions_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
