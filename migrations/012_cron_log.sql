-- TicketTrade — Cron run log (Plan 03-02)
--
-- Records every manual cron trigger (Phase 3 Plan 03-02: ticket expiry
-- sweep). Phase 9 will migrate the cron jobs to a hash-chained
-- audit_log; this table is the Phase 3 stop-gap.
--
-- Per D-28..D-30 the ListingAutoApprove Action writes a row per sweep
-- with the affected count + errors + actor. Idempotent re-runs append
-- rows (no UNIQUE on run_at); the admin UI shows the last N runs.

CREATE TABLE cron_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_name VARCHAR(60) NOT NULL,
    run_at DATETIME NOT NULL,
    processed_count INT UNSIGNED NOT NULL DEFAULT 0,
    errors_json JSON NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_run_at (job_name, run_at),
    CONSTRAINT fk_cron_log_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
