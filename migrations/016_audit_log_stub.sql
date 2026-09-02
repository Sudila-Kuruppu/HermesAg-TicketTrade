-- migrations/016_audit_log_stub.sql
-- Phase 4 Plan 04-01
-- Purpose:  Plain audit_log table. Phase 4 ships the forward-
--           compatible stub per AD-12. Phase 8 wraps the INSERT with
--           a hash chain (adds prev_hash CHAR(64) column). The stub
--           shape is fixed so Phase 8's wrapper does not change the
--           public Support\Audit::log() signature.
-- AD binds: AD-12 (audit_log hash chain stub).
-- Reqs:     FR-ADM-006 (audit trail), NFR-SEC-010 (sensitive
--           actions write audit row).
-- Depends:  002_users_auth.sql.
-- Author:   Phase 4 (2026-09-02)

CREATE TABLE IF NOT EXISTS audit_log (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id   BIGINT UNSIGNED NULL,
  action          VARCHAR(60) NOT NULL,
  target_type     VARCHAR(30) NOT NULL,
  target_id       BIGINT UNSIGNED NOT NULL,
  metadata_json   JSON NULL,
  event_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_target (target_type, target_id),
  KEY idx_actor_time (actor_user_id, event_at),
  KEY idx_action_time (action, event_at),
  CONSTRAINT fk_audit_log_actor
    FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
