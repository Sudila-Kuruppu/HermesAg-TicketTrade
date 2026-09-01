---
phase: 03-marketplace-listings-discovery
plan: 01
subsystem: listings substrate (migrations + image pipeline + Service layer)
tags:
  - migrations
  - image-upload
  - image-proxy
  - webp
  - rate-limit
  - listings
  - categories
  - listing-revisions
dependency_graph:
  requires:
    - phase: 02-student-authentication-profiles
      provides: PDO singleton, RateLimit::hit, Auth::currentUser, Error envelope, route map shape
  provides:
    - 4 new SQL migrations (008_listings, 009_categories, 010_listing_revisions)
    - 7-row categories seed (Textbooks, Electronics, Fashion, Services, Food, Events, Other)
    - FULLTEXT(title, description) index on listings
    - src/Support/ImageUpload (4-layer pipeline: finfo MIME -> getimagesize -> magic bytes -> GD WebP)
    - src/Support/ImageProxy (auth-gated full size, rate-limited thumb/medium, 404-not-403 on auth miss)
    - src/Support/Action/ImageProxyAction (thin wrapper routed at GET /img/{id}/{size})
    - src/Listing/Model/{listing,listing_image,listing_revision}_model.php
    - src/Listing/Service/listing_service.php (sole writer, AD-1)
    - src/Category/Model/category_model.php
    - src/Category/Service/category_service.php (read-only in Phase 3)
    - src/Support/View/partials/{listing_card,listing_card_cork}.php
    - 4 new named rate limits: listing_create (20/hr/user), admin_cron (5/min/IP), img_thumb (60/min/IP), img_full (30/min/user)
    - 6 new error codes: E_IMAGE_INVALID, E_IMAGE_TOO_LARGE, E_LISTING_NOT_FOUND, E_LISTING_FORBIDDEN, E_CATEGORY_NOT_FOUND, E_RATE_LIMIT
    - Webroot discipline: public/uploads/.htaccess (Deny from all) + public/img/.htaccess (routes through router)
  affects:
    - Plan 03-02 builds CreateListingAction/EditListingAction/DeleteListingAction/RelistListingAction/SubmitDraftAction on top of listing_service
    - Plan 03-03 builds BrowseAction board view + listing modal that calls ImageProxy at /img/{id}/thumb
    - Plan 03-04 lands landing page + auto-approve cron that calls listing_service::setStatus
tech-stack:
  added: []
  patterns:
    - 4-layer image validation pipeline (D-11..D-14): finfo MIME, getimagesize dims/size, magic-byte sniff, GD re-encode to WebP
    - SHA256-of-original-bytes as filename (computed before GD re-encode)
    - 3 thumbnails written at upload time (200/600/1200 px) per D-13
    - ImageProxy auth: thumb/medium public + rate-limited, full requires session AND (seller OR ticket holder OR admin); missing auth returns 404 not 403 (AD-14)
    - Per-IP rate limit for thumb/medium (60/min/IP via Support\RateLimit), per-user for full (30/min/user)
    - listing_service is sole writer to listings + listing_images + listing_revisions (AD-1)
    - category_service is sole writer to categories (read-only in Phase 3)
    - 8-image cap enforced at Service layer (D-09) + UPLOAD_ERR_* codes mapped to typed error codes
    - fulltext(title, description) IN BOOLEAN MODE for search, newest-first ORDER BY created_at DESC
key-files:
  created:
    - migrations/008_listings.sql
    - migrations/009_categories.sql
    - migrations/010_listing_revisions.sql
    - config/uploads.php
    - src/Support/ImageUpload.php
    - src/Support/ImageProxy.php
    - src/Support/Action/ImageProxyAction.php
    - src/Listing/Model/listing_model.php
    - src/Listing/Model/listing_image_model.php
    - src/Listing/Model/listing_revision_model.php
    - src/Listing/Service/listing_service.php
    - src/Category/Model/category_model.php
    - src/Category/Service/category_service.php
    - src/Support/View/partials/listing_card.php
    - src/Support/View/partials/listing_card_cork.php
    - public/uploads/.htaccess
    - public/uploads/listings/.gitkeep
    - public/img/.htaccess
    - tests/Integration/Phase03/Listing/ListingServiceTest.php
    - tests/Integration/Phase03/Listing/ListingModelTest.php
    - tests/Integration/Phase03/Category/CategoryServiceTest.php
    - tests/Integration/Phase03/MigrationTest.php
    - tests/Unit/Phase03/Support/ImageUploadTest.php
    - tests/Unit/Phase03/Support/ImageProxyTest.php
  modified:
    - config/rate_limits.php (4 new named limits)
    - config/error_codes.php (6 new codes)
    - config/routes.php (GET /img/{listing_id}/{size})
    - phpunit.xml (phase-3-integration + phase-3-unit testsuites)
decisions:
  - "Plan 03-01 was already on disk when execute-phase was invoked: migrations 008-010, ImageUpload pipeline, ImageProxy, Listing/Category Service+Model layer, and 6 test files were committed in two atomic commits (5db3394 substrate, 52c774b validation+rate-limits). This SUMMARY backfills the missing paperwork after verifying the full test suite (147 tests / 945 assertions) is green."
  - "UPLOAD_STORAGE_ROOT env var overrides config/uploads.php::storage_root so tests + constrained-filesystem hosts don't write to public/uploads. migrate.php now temps in sys_get_temp_dir() for the same reason."
  - "ImageProxy returns 404 (not 403) on missing auth for `full` size, per AD-14: a 403 would leak that the resource exists. Same 404 for invalid size enum, missing listing, or missing image row."
  - "Per-IP rate limit (60/min) is keyed on the client IP for thumb/medium, per-user for full (30/min) — the full-size limit is tied to the session, not the IP, to prevent one tenant exhausting another's budget."
  - "listing_revisions is captured on every edit-to-active transition (snapshot_json of the pre-change row, created_by = actor) per D-09 audit requirement. The hash-chained audit log wires in Phase 4 (AD-12)."
metrics:
  duration: "(see git history: 5db3394 2026-09-01 substrate + 52c774b 2026-09-01 validation)"
  completed_date: "2026-09-01"
  tasks: 3
  commits: 2
  tokens: 95000
status: complete
actuals:
  tokens: 95000
  tasks: 3
  commits: 2
---

# Phase 3 Plan 01: Listings substrate + image pipeline + Listing/Category Service layer — Summary

## What Got Built

The Phase 3 substrate layer is live. Plans 03-02 (CRUD Actions), 03-03
(board view + modal), and 03-04 (landing + auto-approve cron) can now
assume the listings + categories schema, the 4-layer image validation
pipeline, the auth-gated ImageProxy, and the sole-writer Service layer.

### Substrate (commit `5db3394`)

- **4 SQL migrations**: `008_listings.sql` (listings + listing_images with
  FULLTEXT(title, description), status ENUM('draft','pending','active',
  'rejected','sold','removed'), type/condition ENUMs, price_cents BIGINT,
  quantity/quantity_sold, approved_at/approved_by/rejection_reason),
  `009_categories.sql` (id, name UNIQUE, sort_order UNIQUE, is_active +
  7-row seed), `010_listing_revisions.sql` (FK listings CASCADE, snapshot_json
  JSON, created_by FK users RESTRICT).
- **`config/uploads.php`**: storage_root swap by APP_ENV, thumb/medium/full
  px sizes (200/600/1200), webp_quality=80 (NFR-PER-003), max_files=8 (D-09),
  max_file_bytes=5MB, max_dim_px=4000.
- **`config/rate_limits.php`** (modified): 4 new named limits — `listing_create`
  (20/hr/user), `admin_cron` (5/min/IP), `img_thumb` (60/min/IP), `img_full`
  (30/min/user).
- **`config/error_codes.php`** (modified): 6 new codes — `E_IMAGE_INVALID`,
  `E_IMAGE_TOO_LARGE`, `E_LISTING_NOT_FOUND`, `E_LISTING_FORBIDDEN`,
  `E_CATEGORY_NOT_FOUND`, `E_RATE_LIMIT`.
- **`config/routes.php`** (modified): `GET /img/{listing_id}/{size}` routed to
  `App\Support\Action\ImageProxyAction::handle` (auth=false, csrf=false;
  proxy's own per-size auth gate handles the check).
- **Webroot discipline**: `public/uploads/.htaccess` denies direct reads
  (Deny from all); `public/uploads/listings/.gitkeep` keeps the dir tracked;
  `public/img/.htaccess` rewrites through `router.php`.
- **`Support\ImageUpload::process(int $listingId, array $files): array`**:
  the 4-layer pipeline runs on each file. Layer 1 = `finfo_file` MIME check
  (jpeg/png/webp only). Layer 2 = `getimagesize` dims <= 4000px + size <= 5MB.
  Layer 3 = magic bytes (JPEG `FF D8 FF`, PNG `89 50 4E 47`, WebP `RIFF...WEBP`).
  Layer 4 = `imagecreatefromstring` + `imagewebp($gd, $path, 80)`. SHA256 of
  original bytes (BEFORE re-encoding) is the filename stem; three WebP files
  written per accepted upload: `<sha256>_thumb.webp`, `<sha256>_medium.webp`,
  `<sha256>_full.webp`. Returns `['ok'=>bool, 'data'=>['uploaded'=>[...],
  'errors'=>[]], 'error'=>null]`. No exceptions.
- **`Support\ImageProxy::serve(int $listingId, string $size): void`**:
  reads `listing_images` row for the `(listing_id, size)` pair, emits the
  WebP bytes with `Content-Type: image/webp`, `Cache-Control: public,
  max-age=86400`, `Content-Length`. Auth: `full` requires session AND (seller
  OR ticket holder OR admin); missing returns `http_response_code(404); exit;`.
  Rate limit: `Support\RateLimit::hit('img_thumb', $ip)` for thumb/medium,
  `('img_full', (string)$userId)` for full. Invalid size enum or missing row
  → 404. The 429 + Retry-After path (rate-limit excess) is wired in the
  follow-up commit.
- **`Support\Action\ImageProxyAction`** (new): thin wrapper that reads the
  route params, casts `listing_id` to int, validates `size` in `{thumb, medium,
  full}`, delegates to `ImageProxy::serve`.
- **Listing Models** (3 new): `listing_model.php` (insert, findById,
  findBySellerId, search via MATCH...AGAINST IN BOOLEAN MODE, setStatus,
  incrementSold, decrementSold, setReviewFlag, getSearchCount); `listing_image_model.php`
  (insert, findByListingId, findOne, deleteByListingId, updateSortOrder,
  countByListingId); `listing_revision_model.php` (insert, findByListingId).
  All raw PDO via `Support\Db::pdo()->prepare(...)` (AD-1, AD-5).
- **`Listing\Service\listing_service.php`** (new, sole writer per AD-1):
  createDraft / saveDraft / submitDraft / relist / setReviewFlag /
  uploadImages / validateListingData / getWithImages / getSearchResults /
  getSellerListings. All public methods return
  `['ok'=>bool, 'data'=>mixed, 'error'=>['code'=>string, 'message'=>string,
  'fields'=>array|null]]` (AD-16 failure envelope). Asia/Colombo TZ for
  timestamps. Internal `enforceRateLimit(int $userId)` calls `RateLimit::hit('listing_create', ...)`.
  All DB writes inside explicit transactions (beginTransaction / commit /
  rollBack).
- **Category Model + Service**: `category_model.php` (findAllActive,
  findById, insert, setActive) and `category_service.php` (listActive, getById).
  Service is read-only in Phase 3; admin CRUD lands in Phase 8.
- **2 view partials**: `listing_card.php` (Bootstrap card, plain grid) and
  `listing_card_cork.php` (paper surface, ±2° rotation seeded by
  `crc32($listing['id']) % 5 - 2`, alternating red/blue pushpin,
  `aria-hidden` on decoration). NO business logic in partials.

### Validation, error states, rate-limit enforcement (commit `52c774b`)

- `ListingService::createDraft` + `saveDraft` now call `enforceRateLimit`
  BEFORE any DB write; 21st call in 60 min returns
  `E_RATE_LIMIT` envelope.
- `ListingService::uploadImages` enforces the 8-file cap: existing image
  count + new files > 8 → excess files get per-file `E_IMAGE_INVALID` entries
  in `data.errors[]`; listing still has 8 rows.
- `Support\ImageUpload::process` handles every `$_FILES['error']` code:
  `UPLOAD_ERR_OK` proceeds, `UPLOAD_ERR_INI_SIZE`/`UPLOAD_ERR_FORM_SIZE` →
  `E_IMAGE_TOO_LARGE`, `UPLOAD_ERR_NO_FILE` is silently skipped (no error,
  no row), other `UPLOAD_ERR_*` → `E_IMAGE_INVALID`.
- Non-image MIME from `finfo` is rejected at layer 1 with `E_IMAGE_INVALID`.
- `Support\ImageProxy::serve` returns `http_response_code(429)` +
  `Retry-After: <retry_after>` + `Content-Type: text/plain; charset=utf-8`
  + body `Rate limit exceeded` when `RateLimit::hit` returns
  `['allowed'=>false]`. Rate-limit check runs BEFORE file read.

### Tests

- 6 test files committed (Phase 2 test pattern: each Integration test
  begins a transaction in `setUp()`, rolls back in `tearDown()`; Unit tests
  are self-contained).
- `phpunit.xml` gains `<testsuite name="phase-3-integration">` and
  `<testsuite name="phase-3-unit">` entries pointing at the new test dirs.

## Verification Log

```text
$ APP_ENV=test php migrate.php
  Applied: 001_initial.sql ... 010_listing_revisions.sql
Applied 10 files in 0.18s.

$ APP_ENV=test php migrate.php
Already up-to-date (0 files to apply).

$ APP_ENV=test vendor/bin/phpunit --testsuite=phase-3-integration
..................                                                18 / 18 (100%)
OK (18 tests, 122 assertions)

$ APP_ENV=test vendor/bin/phpunit --testsuite=phase-3-unit
..........                                                        10 / 10 (100%)
OK (10 tests, 85 assertions)

$ APP_ENV=test vendor/bin/phpunit
...............................................................  63 / 147 ( 42%)
................................................Contrast ledger: 46/46 token references resolved.
............... 126 / 147 ( 85%)
.....................                                           147 / 147 (100%)
OK (147 tests, 945 assertions)
```

Confirmed at verification time:
- 4 expected tables exist in `tickettrade_test`: `categories` (7 rows
  seeded), `listings`, `listing_images`, `listing_revisions`.
- Re-running `php migrate.php` is a no-op (idempotent).
- FULLTEXT index `ft_title_desc` present on `listings(title, description)`.
- The full test suite (147 / 945) is green — no Phase 1 or Phase 2 test broke.

## Deviations from Plan

### Auto-fixed Issues

**1. UPLOAD_STORAGE_ROOT env override + migrate tempdir swap (filesystem-constraint fix)**
- **Found during:** Task 3 test bootstrap (commit `abe66c0`, post-03-01).
- **Issue:** `vendor/bin/phpunit` failed on the host because
  `config/uploads.php::storage_root` (`public/uploads/listings`) and the
  migrate runner's `tempnam($migrationsDir, '.applied-')` both targeted
  a filesystem that rejected writes (full disk / constrained mount).
- **Fix:** Added `UPLOAD_STORAGE_ROOT` env-var override to `config/uploads.php`
  (env wins over APP_ENV switch); tests now `putenv('UPLOAD_STORAGE_ROOT=' . sys_get_temp_dir() . '/tt-img-...')`.
  `migrate.php` uses `tempnam(sys_get_temp_dir(), '.applied-')` instead of
  `tempnam($migrationsDir, ...)`. Stale `.applied-*` orphan suffix files
  cleaned up.
- **Files modified:** `config/uploads.php`, `migrate.php`,
  `tests/Unit/Phase03/Support/ImageUploadTest.php`,
  `tests/Unit/Phase03/Support/ImageProxyTest.php`.
- **Verification:** `vendor/bin/phpunit --testsuite=phase-3` 28/28 green;
  full suite 147/147 green.
- **Committed in:** `abe66c0` (post-plan cleanup; recorded here because the
  fix made 03-01 verifiable on the current host).

**2. SUMMARY.md backfill (process artifact, not a code deviation)**
- **Found during:** execute-phase invocation on Phase 3.
- **Issue:** Two atomic commits (`5db3394`, `52c774b`) shipped the
  substrate + validation work, but no `03-01-SUMMARY.md` existed. The
  workflow's safe-resume gate would block re-dispatch; rather than
  re-running executor work that's verifiably complete, the orchestrator
  closed out the plan manually by inspecting on-disk artifacts + running
  the test suite.
- **Fix:** This file.
- **Verification:** All 27 must-have artifacts from the plan's
  `must_haves.artifacts` list exist on disk; full test suite is green.
- **Committed in:** this commit + its companion.

**Total deviations:** 2 (one env-var fixup + one paperwork backfill).
**Impact on plan:** No scope creep; the fixup preserves the documented
storage_root swap (dev vs prod) while making it overridable for constrained
hosts. The backfill restores the SUMMARY paperwork that should have shipped
with the substrate commits.

## Issues Encountered

- The local MariaDB instance is configured with `socket=/tmp/mysql.sock`,
  but the package default client connects via `/run/mysqld/mysqld.sock`.
  Workaround: pass `--socket=/tmp/mysql.sock` to the `mysql` client for
  one-off inspection; `config/db.php` and `config/db.test.php` already
  target `/tmp/mysql.sock`, so PHPUnit works without overrides.
- `migrations/.applied` was synced on disk but `tickettrade_test` had no
  schema (the migrate runner skips when `.applied` lists the file even if
  the DDL never ran on this DB). Workaround: drop + recreate the test DB,
  truncate `.applied`, re-run `php migrate.php` to apply all 10 fresh.

## Next Phase Readiness

- **Plan 03-02 (CRUD Actions)** can start immediately — `ListingService`
  has `createDraft`, `saveDraft`, `submitDraft`, `relist`, `setReviewFlag`,
  `uploadImages` ready to be called from new Actions.
- **Plan 03-03 (Board view + modal)** can start immediately — `ListingModel::search`,
  `CategoryService::listActive`, and `ImageProxy::serve` (already routed at
  `GET /img/{id}/{size}`) are all live.
- **Plan 03-04 (Landing + auto-approve cron)** can start immediately —
  `ListingService::setStatus` and `RateLimit::hit('admin_cron', $ip)` are
  ready for the cron Action.
- The `_phase2_meta` placeholder table is still in the test DB (from
  `001_initial.sql`); harmless, kept for Phase 9 substrate cleanup.
- No outstanding blockers.

---
*Phase: 03-marketplace-listings-discovery*
*Plan: 01*
*Completed: 2026-09-01*
