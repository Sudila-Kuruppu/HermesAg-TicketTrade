-- migrations/015_reports.sql
-- Phase 4 Plan 04-01
-- Purpose:  Reports table for the dispute flow. Phase 4 ships the
--           buyer/seller file-dispute Action (writes a reports row
--           with target_type='ticket'); admin resolution Actions
--           (Phase 7) flip status to 'dismissed' or 'actioned'.
--           The table supports 3 target_types (ticket, listing, user)
--           for future Phase 7 listings/users reports.
-- AD binds: AD-2 (Report bounded context; the table belongs to the
--           Report context but the file-dispute Service in the Ticket
--           context inserts via a Service-layer call).
-- Reqs:     REL-04, SEC-06.
-- Depends:  002_users_auth.sql.
-- Author:   Phase 4 (2026-09-02)

CREATE TABLE IF NOT EXISTS reports (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  target_type   ENUM('ticket','listing','user') NOT NULL,
  target_id     BIGINT UNSIGNED NOT NULL,
  reporter_id   BIGINT UNSIGNED NOT NULL,
  reason        VARCHAR(60) NOT NULL,
  text          VARCHAR(200) NOT NULL,
  status        ENUM('pending','dismissed','actioned') NOT NULL DEFAULT 'pending',
  resolved_by   BIGINT UNSIGNED NULL,
  resolved_at   DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_target_status (target_type, target_id, status),
  KEY idx_reporter (reporter_id),
  KEY idx_status (status),
  CONSTRAINT fk_reports_reporter
    FOREIGN KEY (reporter_id) REFERENCES users (user_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_reports_resolver
    FOREIGN KEY (resolved_by) REFERENCES users (user_id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
