-- migrations/001_initial.sql
-- Phase 2 Plan 02-01
-- Purpose:  Placeholder demonstrating the migrate.php runner. The phase 2
--           schema (users, sessions, etc.) ships in 002..007.
-- AD binds: AD-6 (versioned SQL migrations + idempotent runner)
-- Reqs:     OPS-02 (migrations are runnable end-to-end)
-- Depends:  none
-- Author:   Phase 2 (2026-08-31)

CREATE TABLE IF NOT EXISTS _phase2_meta (
  id INT UNSIGNED NOT NULL PRIMARY KEY,
  note VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
