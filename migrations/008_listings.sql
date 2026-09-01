-- migrations/008_listings.sql
-- Phase 3 Plan 03-01
-- Purpose:  Core listings tables for Phase 3 marketplace slice.
--           - listings.status ENUM covers draft -> pending -> active |
--             rejected | sold | removed per D-09.
--           - listing_images stores one row per (listing, size) tuple
--             (thumb, medium, full); the same SHA256 is reused across
--             the three sizes, only the size enum differs.
--           - listing_revisions (in 010) captures pre-edit state for
--             soft-revert of rejected edits to active listings (D-09).
--           - review_flag marks an edit to an `active` listing that
--             needs admin re-review without taking the listing offline
--             (D-09). approved_at + approved_by enable the approved-
--             content fast-track on relist (D-04).
-- AD binds: AD-1 (sole-writer), AD-14 (image storage 4-layer
--           pipeline enforced at the Service layer), AD-16 (failure
--           envelope at the Service layer).
-- Reqs:     LST-01..16, LST-08, LST-10, LST-11, LST-12, PER-02, PER-03.
-- Depends:  002_users_auth.sql, 009_categories.sql (FK target).
-- Author:   Phase 3 (2026-09-01)

-- FK validation is deferred across migration files; migrations 009 and 010
-- also toggle it. Per D-23 this is acceptable because the migration runner
-- processes files in lexical order with IF NOT EXISTS discipline.

CREATE TABLE IF NOT EXISTS listings (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  seller_id         BIGINT UNSIGNED NOT NULL,
  category_id       BIGINT UNSIGNED NOT NULL,
  title             VARCHAR(80) NOT NULL,
  description       TEXT NOT NULL,
  price_cents       BIGINT NOT NULL,
  type              ENUM('product','service') NOT NULL,
  `condition`       ENUM('new','like_new','good','fair') NULL,
  duration_minutes  INT NULL,
  delivery_method   ENUM('in_person','online','hybrid') NULL,
  availability      VARCHAR(500) NULL,
  quantity          INT NOT NULL DEFAULT 1,
  quantity_sold     INT NOT NULL DEFAULT 0,
  status            ENUM('draft','pending','active','rejected','sold','removed') NOT NULL DEFAULT 'draft',
  review_flag       TINYINT(1) NOT NULL DEFAULT 0,
  review_flag_at    DATETIME NULL,
  rejection_reason  TEXT NULL,
  source_listing_id BIGINT UNSIGNED NULL,
  approved_at       DATETIME NULL,
  approved_by       BIGINT UNSIGNED NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_listings_seller_status (seller_id, status),
  KEY idx_listings_category_status_created (category_id, status, created_at),
  KEY idx_listings_created (created_at),
  KEY idx_listings_source (source_listing_id),
  FULLTEXT KEY ft_title_desc (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_images (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  listing_id   BIGINT UNSIGNED NOT NULL,
  sha256       CHAR(64) NOT NULL,
  size         ENUM('thumb','medium','full') NOT NULL,
  is_primary   TINYINT(1) NOT NULL DEFAULT 0,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_listing_images_listing_sort (listing_id, sort_order),
  KEY idx_listing_images_sha (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
