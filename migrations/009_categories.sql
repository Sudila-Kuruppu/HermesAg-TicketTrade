-- migrations/009_categories.sql
-- Phase 3 Plan 03-01
-- Purpose:  Category taxonomy for listings. Seven 7 seed categories ship
--           with stable sort_order (D-31) so the board tab strip and the
--           create-listing form have content from day one. Admin CRUD
--           lands in Phase 8; this Service ships read-only.
--           - is_active flag enables soft-deletion of categories without
--             losing historical listing references (D-32).
--           - sort_order is UNIQUE so admin reorders in Phase 8 cannot
--             collide.
-- AD binds: AD-1, AD-16.
-- Reqs:     LST-03 (board category tabs), LST-01 (create-listing form
--           category picker).
-- Depends:  none.
-- Author:   Phase 3 (2026-09-01)

CREATE TABLE IF NOT EXISTS categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60) NOT NULL,
  description VARCHAR(200) NULL,
  sort_order  INT NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_categories_name (name),
  UNIQUE KEY uniq_categories_sort (sort_order),
  KEY idx_categories_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, description, sort_order, is_active) VALUES
  ('Textbooks',  'Course books, reference material, notes',     1, 1),
  ('Electronics','Phones, laptops, accessories, gadgets',       2, 1),
  ('Fashion',    'Clothing, shoes, accessories',                3, 1),
  ('Services',   'Tutoring, design, freelance help',            4, 1),
  ('Food',       'Homemade, snacks, baked goods',               5, 1),
  ('Events',     'Tickets, group buys, event services',         6, 1),
  ('Other',      'Anything else campus-trade',                   7, 1);
