-- migrations/002_users_auth.sql
-- Phase 2 Plan 02-01
-- Purpose:  Core users + student_id_allowlist tables. Phase 2 ships the
--           schema; Phase 9's seed script populates the allowlist with ~50
--           demo accounts. Per D-01 the allowlist is empty here.
-- AD binds: AD-2 (Auth/User bounded contexts), AD-5 (utf8mb4/InnoDB/
--           BIGINT UNSIGNED PKs), AD-13 (auth shape), AD-18 (bcrypt sole
--           writer — password_hash column carries the cost-12 bcrypt
--           hash from Auth/Service/auth_service.php).
-- Reqs:     AUTH-01 (allowlist-gated register), AUTH-04 (bcrypt cost >=12),
--           PROF-01 (profile fields), PROF-04 (verified flag).
-- Depends:  001_initial.sql
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS users (
  user_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(190) NOT NULL,
  student_id     VARCHAR(40) NOT NULL,
  nickname       VARCHAR(40) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  full_name      VARCHAR(120) NOT NULL DEFAULT '',
  bio            VARCHAR(500) NOT NULL DEFAULT '',
  whatsapp       VARCHAR(20) NULL,
  avatar_id      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  points         INT NOT NULL DEFAULT 0,
  points_frozen  BOOLEAN NOT NULL DEFAULT FALSE,
  tier           CHAR(1) NOT NULL DEFAULT 'E',
  is_admin       BOOLEAN NOT NULL DEFAULT FALSE,
  is_banned      BOOLEAN NOT NULL DEFAULT FALSE,
  is_verified    BOOLEAN NOT NULL DEFAULT FALSE,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  -- D-15: nickname is locked at registration, must be unique.
  UNIQUE KEY uniq_email (email),
  UNIQUE KEY uniq_student_id (student_id),
  UNIQUE KEY uniq_nickname (nickname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_id_allowlist (
  student_id  VARCHAR(40) NOT NULL PRIMARY KEY,
  email       VARCHAR(190) NOT NULL,
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uniq_allow_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
