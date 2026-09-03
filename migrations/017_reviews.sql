-- migrations/017_reviews.sql
-- Phase 5 Plan 05-01
-- Purpose:  Core reviews table. Per AD-2 (Review is a new bounded
--           context — Review/Service is the sole writer) + AD-15
--           (review gate: tickets.status IN ('redeemed','expired')
--           AND tickets.dispute_status='none'). reviewer_role ENUM
--           enforces the single-row-per-(ticket,role) invariant at
--           the DB level via UNIQUE KEY uq_review_per_role
--           (D-02: buyer-as-reviewer OR seller-as-reviewer, never
--           both in the same row). DEFENSE-IN-DEPTH: chk_reviewer_not_reviewee
--           CHECK (reviewer_id <> reviewee_id) guards the 2-party
--           ticket invariant at the schema level (D-07).
-- AD binds: AD-1 (sole writer is Review/Service/review_service),
--           AD-2 (bounded context), AD-15 (review state gate),
--           AD-16 (failure envelope on Action exit).
-- Reqs:     RAT-01, RAT-02, RAT-03, RAT-04, RAT-06, SEC-06, PTS-04.
-- Depends:  013_tickets.sql, 002_users_auth.sql.
-- Author:   Phase 5 (2026-09-03)

CREATE TABLE IF NOT EXISTS reviews (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_id      BIGINT UNSIGNED NOT NULL,
  reviewer_id    BIGINT UNSIGNED NOT NULL,
  reviewee_id    BIGINT UNSIGNED NOT NULL,
  rating         TINYINT UNSIGNED NOT NULL,
  comment        TEXT NULL,
  reviewer_role  ENUM('buyer','seller') NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_per_role (ticket_id, reviewer_role),
  KEY idx_reviewee (reviewee_id, created_at),
  KEY idx_reviewer (reviewer_id, created_at),
  CONSTRAINT fk_reviews_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_reviewer
    FOREIGN KEY (reviewer_id) REFERENCES users (user_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_reviews_reviewee
    FOREIGN KEY (reviewee_id) REFERENCES users (user_id)
    ON DELETE RESTRICT,
  CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT chk_reviewer_not_reviewee CHECK (reviewer_id <> reviewee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
