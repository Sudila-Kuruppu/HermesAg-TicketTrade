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
-- Idempotent: each ALTER is wrapped in a procedure that checks
-- information_schema and only adds the constraint if missing, so
-- re-runs of the migration runner are no-ops.

DELIMITER $$

DROP PROCEDURE IF EXISTS _tt_add_fk_if_missing $$
CREATE PROCEDURE _tt_add_fk_if_missing(
    IN p_table VARCHAR(64),
    IN p_constraint VARCHAR(64),
    IN p_ddl TEXT
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO v_exists
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND CONSTRAINT_NAME = p_constraint
          AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    IF v_exists = 0 THEN
        SET @sql := p_ddl;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

CALL _tt_add_fk_if_missing(
    'listing_revisions',
    'fk_listing_revisions_listing',
    'ALTER TABLE listing_revisions ADD CONSTRAINT fk_listing_revisions_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE'
);

CALL _tt_add_fk_if_missing(
    'listings',
    'fk_listings_source',
    'ALTER TABLE listings ADD CONSTRAINT fk_listings_source FOREIGN KEY (source_listing_id) REFERENCES listings (id) ON DELETE SET NULL'
);

DROP PROCEDURE _tt_add_fk_if_missing;