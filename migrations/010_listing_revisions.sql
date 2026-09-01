-- migrations/010_listing_revisions.sql
-- Phase 3 Plan 03-01
-- Purpose:  Listing edit history audit. Every edit to an `active` listing
--           writes a snapshot row before the change (D-09), so admin
--           "reject edit" can soft-revert by re-loading the previous
--           approved version. The audit table is also used to demo
--           edit history in the WAD video walkthrough.
--           - snapshot_json stores the full pre-edit listing row as JSON.
--           - created_by is the seller (the only writer of edits is the
--             owner; admin audit log is Phase 4 / AD-12).
-- AD binds: AD-1, AD-16.
-- Reqs:     LST-09 (edit history preservation).
-- Depends:  002_users_auth.sql, 008_listings.sql.
-- Author:   Phase 3 (2026-09-01)

CREATE TABLE IF NOT EXISTS listing_revisions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id    BIGINT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT UNSIGNED NOT NULL,
  KEY idx_listing_revisions_listing_created (listing_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
