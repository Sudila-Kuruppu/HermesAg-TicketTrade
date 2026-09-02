-- migrations/013_tickets.sql
-- Phase 4 Plan 04-01
-- Purpose:  Core tickets table. Per AD-1 (sole writer is
--           Ticket/Service/ticket_service) + AD-7 (quantity_sold
--           increments only inside ticket-creation transaction) +
--           AD-9 (atomic UPDATE guards) + AD-8 (ticket code format
--           TK-XXXX-XXXX-XXXX-XXXX-XXXX, six 4-char base62 groups).
--           ticket_id is BIGINT UNSIGNED; ticket_code is the
--           public-facing dashed code; status + dispute_status
--           enums cover the state machines per PRD 4.2/4.3.
-- AD binds: AD-1 (sole-writer), AD-7 (inventory invariant),
--           AD-8 (ticket code), AD-9 (atomic UPDATE), AD-10 (sole
--           writer of points_log via points_service), AD-15 (review
--           state gate). FK to listings + users ON DELETE RESTRICT
--           per NFR-REL-006.
-- Reqs:     TKT-01..12, BUY-01, BUY-02, REL-01, REL-02, REL-06.
-- Depends:  008_listings.sql, 002_users_auth.sql.
-- Author:   Phase 4 (2026-09-02)

CREATE TABLE IF NOT EXISTS tickets (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ticket_code     VARCHAR(30) NOT NULL,
  listing_id      BIGINT UNSIGNED NOT NULL,
  buyer_id        BIGINT UNSIGNED NOT NULL,
  seller_id       BIGINT UNSIGNED NOT NULL,
  status          ENUM('active','redeemed','expired','disputed') NOT NULL DEFAULT 'active',
  dispute_status  ENUM('none','pending','rejected','upheld') NOT NULL DEFAULT 'none',
  price_cents     BIGINT NOT NULL,
  session_number  INT NOT NULL DEFAULT 1,
  total_sessions  INT NOT NULL DEFAULT 1,
  expires_at      DATETIME NOT NULL,
  redeemed_at     DATETIME NULL,
  disputed_at     DATETIME NULL,
  resolved_at     DATETIME NULL,
  resolution_note TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ticket_code (ticket_code),
  KEY idx_buyer_status (buyer_id, status),
  KEY idx_seller_status (seller_id, status),
  KEY idx_expires (expires_at),
  KEY idx_dispute (dispute_status),
  CONSTRAINT fk_tickets_listing
    FOREIGN KEY (listing_id) REFERENCES listings (id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_buyer
    FOREIGN KEY (buyer_id) REFERENCES users (user_id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_tickets_seller
    FOREIGN KEY (seller_id) REFERENCES users (user_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
