-- migrations/023_listing_fk_constraints.sql
-- Phase 3 Plan 03-02 (post-review fix for CR-05).
--
-- Adds the missing FK constraints declared in the spec but omitted from
-- 008_listings.sql / 010_listing_revisions.sql:
--   - listing_revisions.listing_id -> listings.id  ON DELETE CASCADE
--     (so soft-revert cannot survive a hard-delete of the parent
--     listing; revisions are audit-history of the listing, not
--     standalone data).
--   - listings.source_listing_id -> listings.id  ON DELETE SET NULL
--     (so deleting the source listing on a relist clears the
--     fast-track pointer rather than leaving a dangling FK).
--
-- Idempotent: each ALTER is gated on information_schema.TABLE_CONSTRAINTS
-- and only issued when the named FK is missing. Re-runs of the migration
-- runner become no-ops. Same information_schema + PREPARE/EXECUTE idiom
-- as 014_users_redemption_count.sql and 018_points_log_indexes.sql —
-- plain statements, no DELIMITER blocks (PDO does not understand the
-- DELIMITER directive and migrate.php splits on ';', D-27 invariant).

SET @fk_revisions_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'listing_revisions'
    AND CONSTRAINT_NAME = 'fk_listing_revisions_listing'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_revisions_exists = 0,
  'ALTER TABLE listing_revisions ADD CONSTRAINT fk_listing_revisions_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE',
  'DO 0');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_source_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'listings'
    AND CONSTRAINT_NAME = 'fk_listings_source'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_source_exists = 0,
  'ALTER TABLE listings ADD CONSTRAINT fk_listings_source FOREIGN KEY (source_listing_id) REFERENCES listings (id) ON DELETE SET NULL',
  'DO 0');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;