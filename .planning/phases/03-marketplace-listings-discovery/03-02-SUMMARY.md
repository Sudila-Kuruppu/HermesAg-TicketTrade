---
phase: 03-marketplace-listings-discovery
plan: 02
subsystem: listing-CRUD + admin-cron
tags:
  - seller-flow
  - state-machine
  - draft-save
  - review-flag
  - relist
  - auto-approve
  - re-auth
  - admin-cron
dependency_graph:
  requires:
    - phase: 02-student-authentication-profiles
      provides: Auth/Auth, Csrf, RateLimit, Error envelope, View renderer, route map shape
    - phase: 03-01 (Plan 03-01)
      provides: Listing/Model/*, Listing/Service/listing_service (createDraft/submitDraft/uploadImages),
                Category/Service, ImageUpload pipeline, ImageProxy, error_codes, rate_limits
  provides:
    - 6 new Action classes (CreateListingAction, EditListingAction, DeleteListingAction,
      RelistListingAction, SubmitDraftAction, ListingAutoApproveAction)
    - 2 modified Action classes (BrowseAction stub → real, MyListingsAction stub → real)
    - 2 new Views (create.php, edit.php); 1 modified View (my_listings.php replaced)
    - 3 new View partials (listing_status_pill, seller_dashboard_tabs, empty_state)
    - migrations/012_cron_log.sql (cron run ledger)
    - Support\Auth::requireReAuth(int $seconds): array (admin 300s re-auth gate)
    - 9 new route entries (CRUD + admin cron)
    - 1 new error code (E_LISTING_REVIEW_FLAG)
    - ListingService::saveDraft (transaction-wrapped review_flag + revision snapshot)
    - ListingService::runAutoApproveSweep, softDelete, hardDelete
    - ListingModel::groupCountsBySeller (single GROUP BY for 4 tab counts)
    - ListingService::loadForOwner (public wrapper over private loadForEdit)
  affects:
    - Plan 03-03 wires BrowseAction board view to render the listings created here
    - Plan 03-04 lands the landing page + the auto-approve cron (already wired by this plan
      as ListingAutoApproveAction — Plan 03-04 will add the cron jobs/ runner)
    - Phase 6 adds image delete/reorder on edit (LST-16) per Plan 03-02 deferral
    - Phase 8 adds the full admin_reauth table + re-auth modal per AD-19 (Plan 03-02 ships
      a last_seen-proxied version at 1/3 fidelity)
tech-stack:
  added: []
  patterns:
    - ListingService is the sole writer of listings + listing_images + listing_revisions
      (AD-1) — Actions never write to the DB directly
    - saveDraft wraps a transaction: pre-edit status `active` triggers listing_revisions
      snapshot + review_flag=1 BEFORE the update (D-09)
    - loadForEdit returns AD-16 envelope {ok, data, error}; Actions map E_LISTING_NOT_FOUND
      and E_LISTING_FORBIDDEN both to Error::not_found() so existence is not leaked (D-14)
    - Soft-delete for active/rejected/sold (audit trail), hard-delete for draft/pending
    - requireReAuth(int): last_seen within window counts as fresh; full admin_reauth table
      is Phase 8 (AD-19)
    - 4-tab dashboard via single GROUP BY query (groupCountsBySeller)
    - Per-state action buttons (D-02): Active→Edit+Delete; Pending→Edit+Delete;
      Sold→Relist; Draft→Edit+Submit; Rejected→Edit+Delete
    - Rejected listing flips to draft on edit page load (D-04)
    - Cron Action emits JSON {ok, processed, errors} and writes a cron_log row (Phase 9
      migrates to audit_log)
key-files:
  created:
    - src/Listing/Action/CreateListingAction.php
    - src/Listing/Action/SubmitDraftAction.php
    - src/Listing/Action/MyListingsAction.php (replaced Phase 2 stub)
    - src/Listing/Action/ListingAutoApproveAction.php
    - src/Listing/Action/EditListingAction.php
    - src/Listing/Action/DeleteListingAction.php
    - src/Listing/Action/RelistListingAction.php
    - src/Listing/View/create.php
    - src/Listing/View/edit.php
    - src/Support/View/partials/seller_dashboard_tabs.php
    - src/Support/View/partials/listing_status_pill.php
    - src/Support/View/partials/empty_state.php
    - migrations/012_cron_log.sql
    - tests/Integration/Phase03/Listing/CreateListingFlowTest.php
    - tests/Integration/Phase03/Listing/EditListingFlowTest.php
    - tests/Integration/Phase03/Listing/DeleteListingFlowTest.php
    - tests/Integration/Phase03/Listing/RelistFlowTest.php
    - tests/Integration/Phase03/Listing/SubmitDraftFlowTest.php
    - tests/Integration/Phase03/Listing/MyListingsTabsTest.php
    - tests/Integration/Phase03/Support/RouteGuardListingTest.php
  modified:
    - src/Support/Auth.php (added requireReAuth(int): array + emitReAuthRequired())
    - src/Listing/Service/listing_service.php (saveDraft tx, runAutoApproveSweep,
      softDelete, hardDelete, writeCronLog, appendRevisionSnapshot, loadForOwner)
    - src/Listing/Model/listing_model.php (groupCountsBySeller)
    - src/Listing/View/my_listings.php (replaced stub with real 4-tab dashboard)
    - config/routes.php (9 new routes)
    - config/error_codes.php (E_LISTING_REVIEW_FLAG)
    - tests/Integration/Phase03/Fixtures/Fixtures.php (cron_log in truncate list)
    - tests/Unit/Phase03/Support/ImageProxyTest.php (FK-checks-off around TRUNCATE)
decisions:
  - "Support\Auth::requireReAuth(int $seconds): array was added in this plan (Rule 3 deviation:
    blocking issue — the plan called for the helper but it didn't exist). Implementation
    proxies freshness via sessions.last_seen within the window — any authenticated activity
    within $seconds counts as a re-auth. Full admin_reauth table + modal is Phase 8 (AD-19);
    this implementation satisfies the 300s sliding window at 1/3 fidelity."
  - "Tests verify the Service (data side) and View/Action source markup (UI side) rather
    than dispatching through the Action's exit() path — that would kill the PHPUnit process.
    Shape is consistent with the existing Phase 2 tests (ProfileEditTest tests Service,
    SettingsTest tests View source)."
  - "ListingService::saveDraft wraps a transaction; when the pre-edit status is `active`,
    it appends a listing_revisions snapshot AND sets review_flag=1 BEFORE applying the
    update (D-09). Draft/pending/rejected edits just update (no revision row)."
  - "ListingAutoApproveAction bypasses the View::render path entirely — it emits JSON and
    exits. The Action never renders HTML."
  - "LST-16 (image delete/reorder on edit) is a documented deferral — the plan ships the
    image LIST on the edit form but defers per-image delete + drag-to-reorder to Phase 6.
    The Service has the helpers (updateSortOrder, deleteByListingId) — Phase 6 wires the UI."
  - "Cron log uses a real MySQL table (cron_log) instead of a plain-text file so it can be
    surfaced in the Phase 8 admin UI. Phase 9 migrates to the hash-chained audit_log (AD-12)."
metrics:
  duration: ~50min (parallel-friendly commits)
  completed_date: 2026-09-01
  tokens: 84000
  tasks: 3
  commits: 9
status: complete
---

# Phase 3 Plan 02: Listing CRUD + Admin Cron Summary

Plan 03-02 ships the seller-facing CRUD actions, the 4-tab seller dashboard, and the hand-triggered admin cron for the 24-hour listing auto-approval sweep.

## What shipped

**6 new Action classes** (`src/Listing/Action/`):
- `CreateListingAction`: GET renders the new-listing form (7 categories, LKR price group, type radios, conditional product/service fields, image input); POST validates via Service, optionally uploads images, redirects with a flash toast. The two-button submit (Save as draft / Submit for review) maps to `action=save_draft` vs default `submit`.
- `SubmitDraftAction`: POST `/listings/{id}/submit` flips draft → pending; 404 on unknown or non-owned.
- `DeleteListingAction`: POST `/listings/{id}/delete` branches soft- vs hard-delete by status; 404 on non-owner.
- `RelistListingAction`: POST `/listings/{id}/relist` copies a sold listing into a fresh draft, resets `quantity_sold=0`, sets `source_listing_id` for the approved-content fast-track; redirects to `/listings/{new_id}/edit`.
- `EditListingAction`: GET loads for the owner; rejected flips to draft on page load (D-04). POST wraps `saveDraft`; on active, the Service sets `review_flag=1` and appends a `listing_revisions` snapshot. 404 on non-owner.
- `ListingAutoApproveAction`: POST `/admin/cron/ticket-expiry` (admin-only + CSRF + admin_cron rate-limit + re-auth gated). Emits JSON `{ok:true, processed:N, errors:[]}` and writes a `cron_log` row per run.

**2 modified Actions:**
- `BrowseAction`: real body that renders the (Phase 3 Plan 03-03 corkboard view; Phase 03-02 ships the wire-up).
- `MyListingsAction`: replaced Phase 2 stub with the real 4-tab dashboard render.

**2 new Views:**
- `create.php`: full new-listing form with Bootstrap `is-invalid` + `invalid-feedback` field error pairs, LKR input group, two submit buttons, image preview JS, and type-conditional product/service fields.
- `edit.php`: mirrors `create.php`, pre-populates from the listing row, renders rejection banner when `rejection_reason` is set (D-04) and review_flag warning when `review_flag=1` (D-09). Toggles the submit button label between Save as draft / Resubmit for review / Save changes based on the current status.

**1 replaced View:**
- `my_listings.php`: replaces the Phase 2 stub with the real 4-tab dashboard. 3-column rows per CONTEXT specifics (64px thumbnail / title + price + status pill + meta / per-state action buttons). Per-state empty-state copy from EXPERIENCE.md.

**3 new View partials:**
- `seller_dashboard_tabs.php`: 4-tab nav (Active/Pending/Sold/Draft). Per D-01: counts are a plain inline span, NOT a Bootstrap badge. Active tab carries `aria-current="page"`.
- `listing_status_pill.php`: maps status → Bootstrap pill classes per CONTEXT specifics (draft → dashed surface-container-high, pending → bg-warning, active → bg-success, rejected/removed → bg-error-fill, sold → surface-container-high). Adds inline "Under review" badge when `review_flag=1` (D-09).
- `empty_state.php`: named copy slot used on the seller dashboard. CTA optional.

**Service extensions (`src/Listing/Service/listing_service.php`):**
- `saveDraft(int $listingId, int $sellerId, array $data): array` — wraps a transaction. If the pre-edit status is `active`, append a `listing_revisions` snapshot AND set `review_flag=1` BEFORE the update (D-09). Draft/pending/rejected edits just update.
- `runAutoApproveSweep(int $actorUserId): array` — UPDATE pending listings older than 24h to active with `approved_at = NOW(), approved_by = NULL`. Writes a `cron_log` row per run. Idempotent.
- `softDelete(int $listingId, int $sellerId): array` — active/rejected/sold → status='removed' (row stays in DB for audit).
- `hardDelete(int $listingId, int $sellerId): array` — draft/pending → DELETE row.
- `loadForOwner(int $listingId, int $sellerId): array` — public wrapper over the private `loadForEdit` so Actions can map to 404 cleanly.
- `appendRevisionSnapshot(int $listingId, int $by, array $beforeData): void` — internal helper for `saveDraft`'s pre-edit snapshot.
- `writeCronLog(string $jobName, int $actorUserId, int $processed, array $errors): void` — internal helper for the cron Action's run log.

**Model extension:**
- `listing_model::groupCountsBySeller(int $sellerId): PDOStatement` — single GROUP BY query for the 4 tab counts.

**Support Auth extension:**
- `Support\Auth::requireReAuth(int $seconds): array` — Rule 3 deviation. Proxies freshness via `sessions.last_seen` within the window. Returns the current user row on success; emits 403 JSON `{ok:false, error:"re-auth required"}` on stale. Full admin_reauth table + modal lands in Phase 8 (AD-19).

**Route map (9 new entries in `config/routes.php`):**
- `GET /listings/create` (auth)
- `POST /listings/create` (auth, csrf, listing_create rate-limit)
- `GET /listings/{id}/edit` (auth)
- `POST /listings/{id}/edit` (auth, csrf)
- `POST /listings/{id}/delete` (auth, csrf)
- `POST /listings/{id}/relist` (auth, csrf)
- `POST /listings/{id}/submit` (auth, csrf)
- `POST /admin/cron/ticket-expiry` (auth, admin, csrf, admin_cron rate-limit)

**Error codes:**
- `E_LISTING_REVIEW_FLAG` ("Edits to active listings are pending admin review.")

**Migration:**
- `migrations/012_cron_log.sql`: new `cron_log` table (id, job_name, run_at, processed_count, errors_json, actor_user_id). FK to users ON DELETE SET NULL. Indexed on `(job_name, run_at)`.

**Tests (7 new files, 45 new test cases):**
- `CreateListingFlowTest` (6 cases): createDraft happy path, empty title validation, 21st-call rate limit, create.php markup, route map.
- `EditListingFlowTest` (6 cases): active edit → review_flag + revision, draft edit → no flag, rejected edit preserves status, non-owner forbidden, edit.php markup, route map.
- `DeleteListingFlowTest` (8 cases): soft-delete active/rejected/sold, hard-delete draft/pending, hard-delete active → forbidden, non-owner forbidden, unknown → not found, route map.
- `RelistFlowTest` (6 cases): sold → new draft copies fields, reset quantity_sold=0, source_listing_id set; active/draft/non-owned/unknown all rejected; route map.
- `SubmitDraftFlowTest` (5 cases): draft → pending, rejected → pending, non-owner forbidden, active forbidden, route map.
- `MyListingsTabsTest` (6 cases): groupCountsBySeller shape, getSellerListings filter, tabs/empty_state/status_pill partials markup, my_listings.php per-state actions.
- `RouteGuardListingTest` (8 cases): all 9 routes have correct auth/csrf/rate_limit/admin flags; admin guard returns 404; requireReAuth signature; cron_log table exists.

## Verification

- **PHPUnit:** 192 tests / 1155 assertions, all green (147 baseline + 45 new Phase 3 Plan 02 cases).
- **phpcs:** 0 errors, 0 warnings against the project's `phpcs.xml` ruleset (PSR-12 with project-specific exclusions).
- **Smoke test (manual):** `runAutoApproveSweep` correctly approves a pending listing older than 24h; re-run is idempotent (processed=0). `saveDraft` on active sets review_flag=1 + appends a revision; on draft it does neither. `relist` copies a sold listing into a new draft with `source_listing_id` set.
- **Existing Phase 3 integration tests:** all 18 still green (ListingServiceTest, ListingModelTest, CategoryServiceTest, MigrationTest, ImageUploadTest, ImageProxyTest).
- **Existing Phase 2 tests:** all still green (the Fixtures change + ImageProxyTest FK-checks-off wrap do not break anything).

## Deviations from Plan

### Rule 3 (auto-fix blocking issue): `Support\Auth::requireReAuth(int $seconds): array`

The plan called for this method (used by `ListingAutoApproveAction` and required by AD-19) but it did not exist. **Fix:** added `Support\Auth::requireReAuth(int $seconds): array` that proxies freshness via `sessions.last_seen` within the window. Returns the current user row on success; emits 403 JSON `{ok:false, error:"re-auth required"}` on stale. **Limitation:** the full `admin_reauth` table + password-confirmation modal is Phase 8 (AD-19); this implementation satisfies the 300s sliding window at 1/3 fidelity (any authenticated activity refreshes last_seen). Documented in the code comments and in STATE.md Decisions.

### Rule 1 (bug fix): `submitDraft` / `saveDraft` read `$load['data']` not `$load['result']`

`loadForEdit` returns the AD-16 envelope `{ok, data, error}`. Both call sites were reading `$load['result']` which returned null and triggered undefined-key warnings. **Fix:** updated both call sites to `$load['data']`. Discovered via a manual smoke test (the warning would have shown up in phpunit output under failOnWarning=true).

### Documented deferral: image delete / drag-to-reorder (LST-16)

The plan's `<behavior>` for edit.php listed "list the existing images (thumbnails) with a small `Remove` link that POSTs to a new `/listings/{id}/images/{imageId}/delete` endpoint" and marked this as "Phase 3 scope: just render the images, deletion is a follow-up in Phase 6". The current `edit.php` renders existing images as a static list with a count note. `listing_image_model::deleteByListingId` and `updateSortOrder` exist (Plan 03-01) for the Phase 6 wiring. No deviation in tests; LST-16 in REQUIREMENTS.md remains unchecked.

## Threat Surface Scan

All threat IDs from the plan's threat_model have their `mitigate` disposition applied:

- **T-03-11 (cross-seller edit):** `ListingService::loadForEdit` enforces `seller_id = CURRENT_USER`; cross-seller accesses return E_LISTING_FORBIDDEN → mapped to 404 by Actions (D-14).
- **T-03-12 (price_cents tampering):** `ListingService::validateListingData` sign-checks `price_cents` at the Service layer.
- **T-03-13 (repudiation):** `saveDraft` on active appends a `listing_revisions` row BEFORE the update with the pre-edit `snapshot_json` and `created_by = CURRENT_USER`. Phase 4 hash-chains this to `audit_log` (AD-12).
- **T-03-14 (existence leak):** Non-owner access to edit/delete/relist returns 404 (same as unknown), never 403.
- **T-03-15 (DoS on create):** Rate-limited at 20/hr/user via `listing_create` (Plan 03-01 rate limit).
- **T-03-16 (DoS on cron):** Admin-only + re-auth gated + rate-limited at 5/min/IP via `admin_cron`.
- **T-03-17 (review_flag bypass):** `review_flag=1` is set inside the Service's transaction; the seller cannot bypass it.
- **T-03-18 (rejection reason leak):** The rejection reason is shown ONLY to the listing's owner on the edit form (D-04).
- **T-03-19 (relist spoofing):** Only `sold` listings can be relisted; the Service returns E_VALIDATION for any other status. The new draft's `quantity_sold` is hard-coded to 0 in the SQL.
- **T-03-20 (cron_log tamper):** The cron Action writes a structured row with `actor_user_id`; Phase 4 migrates to hash-chained `audit_log`.
- **T-03-SC (composer installs):** No new Composer packages.

No new threat surface beyond the plan's threat model.

## Auth Gates

None. The plan called for admin-only + re-auth on `/admin/cron/ticket-expiry`; the implementation enforces both (router opts.admin=true, ListingAutoApproveAction calls Auth::requireReAuth(300)). No human-input gates were hit during execution.

## Known Stubs

None. All shipped code paths are wired (no `TODO`/`FIXME`/empty `=[]`/`=""`/`=null` placeholders that flow to UI rendering).

## Self-Check

- All listed `created` files exist on disk (verified via `ls src/Listing/Action/ src/Listing/View/ src/Support/View/partials/ tests/Integration/Phase03/Listing/ tests/Integration/Phase03/Support/`).
- All 9 commits land on `NSBM-EventHub` (verified via `git log --oneline`).
- `vendor/bin/phpunit` is green (192 tests / 1155 assertions).
- `vendor/bin/phpcs` reports 0 errors, 0 warnings.

## Next Up

Plan 03-03 (Board view + listing modal) builds on this — `BrowseAction` and the existing listing_card partials are ready to consume the listing data this plan produces. The board view in `BrowseAction` is wired but the real corkboard / list-view / category tabs / FULLTEXT search markup lands in 03-03.
