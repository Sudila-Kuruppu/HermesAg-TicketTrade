---
phase: 03-marketplace-listings-discovery
fixed_at: 2026-09-05T12:30:00Z
review_path: .planning/phases/03-marketplace-listings-discovery/03-REVIEW.md
iteration: 1
findings_in_scope: 20
fixed: 18
skipped: 0
blocked: 0
deferred: 1
already_fixed: 1
status: partial
commits:
  - 71c729a: CR-01 validate direction in getNextInCategory to prevent SQL injection
  - f033d28: CR-02 translate price_rupees to price_cents (later moved to Service in 92e77f0)
  - 3c8dbef: CR-03 escape q and activeCatName in board empty-state (XSS)
  - a3817c3: CR-04 ImageProxy auth checks viewer is_admin (not seller) and uses single SELECT
  - f0d794f: CR-05 add idempotent migration 023 for FKs on listing_revisions.listing_id and listings.source_listing_id
  - 230f279: CR-08 add Bootstrap 5.3.3 base CSS+JS (asset-pipeline regression)
  - 717f8f8: CR-07 add Network::clientIp() helper for trusted-proxy X-Forwarded-For handling
  - 2ff6220: WR-01 use prepared statement for MAX(sort_order) query in uploadImages
  - 7b5eb84: WR-02 document runAutoApproveSweep as intentional prepared with no params
  - 5c7c739: WR-03 document rejected-to-draft flip is one-shot via status check
  - 23f3807: WR-05 cron_log migration uses IF NOT EXISTS for idempotent reruns
  - 9128b49: WR-06 convert dead null branches to LogicException to catch refactor regressions
  - 7946370: WR-07 populate seller_nickname/tier/is_verified in getWithImages
  - 248a978: WR-08 use $titleStr consistently in ListingFragmentAction JSON + carousel
  - 63a996b: IN-01 remove dead phpdoc stubs for unimplemented incrementSold/decrementSold
  - bc62535: IN-02 extract parseLastSeenTimestamp helper for boot() and requireReAuth()
  - 92e77f0: IN-03 accept price_rupees|price_cents in Service; add form-submit e2e test
  - a455cd0: IN-04 suppress repeated deprecation warnings via process-local static guard
  - 859074c: trailing newline on Network.php (phpcs cleanup)
---

# Phase 3: Code Review Fix Report

**Fixed at:** 2026-09-05T12:30:00Z
**Source review:** `.planning/phases/03-marketplace-listings-discovery/03-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 20
- Fixed: 18
- Deferred: 1 (WR-04 — Phase 9 perf concern, no code change needed)
- Already fixed in source: 1 (CR-06 — reviewer misread existing DateTime code as `strtotime`)
- Blocked: 0
- Skipped: 0

**Status:** `partial` (because 2 findings had no code change; 18 actually modified).

## Per-finding disposition

### CR-01 — SQL injection in `getNextInCategory`
- **Status:** fixed
- **Commit:** `71c729a`
- **Files changed:** `src/Listing/Model/listing_model.php`
- **Verification:** phpunit phase-3-integration 175/176 (only pre-existing failure), phase-3-unit 10/10, phpcs clean.
- **Notes:** Validates `$direction` against `['next','prev']` allowlist; throws `InvalidArgumentException` otherwise. The literal `<` / `>` comparator is now selected from the validated value, not interpolated after user-input passthrough.

### CR-02 — `price_rupees` vs `price_cents` form mismatch
- **Status:** fixed (refined in IN-03)
- **Commit:** `f033d28` (initial Action-layer translation); refined in `92e77f0` (moved to Service)
- **Files changed:** `src/Listing/Action/CreateListingAction.php`, `src/Listing/Service/listing_service.php`
- **Verification:** phpunit phase-3-integration new `test_action_translates_price_rupees_to_price_cents` passes; existing `test_create_draft_returns_draft_then_submit_flips_to_pending` continues to pass.
- **Notes:** Final shape: Service `validateListingData()` accepts EITHER `price_rupees` (form view) OR `price_cents` (API callers). `price_cents` wins if both are sent. This unifies the contract at the Service layer and removes the Action-layer translation that was added in the first commit.

### CR-03 — Unescaped output of `$q` / `$activeCatName` in board empty-state
- **Status:** fixed
- **Commit:** `3c8dbef`
- **Files changed:** `src/Listing/View/board.php`
- **Verification:** phpunit green; phpcs clean.
- **Notes:** Both values wrapped in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before string concat.

### CR-04 — `ImageProxy::fullSizeAuthorized` admin check on wrong table + 3 queries
- **Status:** fixed
- **Commit:** `a3817c3`
- **Files changed:** `src/Support/ImageProxy.php`
- **Verification:** phpunit phase-3-unit 10/10 (including `test_full_size_*`); phpcs clean.
- **Notes:** Single SELECT with `EXISTS()` predicate for ticket-holder check; `is_admin` read from `$GLOBALS['current_user']` (populated by `Auth::boot()`), not from the seller's row. Fail-closed semantics preserved (404 on any error).

### CR-05 — Missing FKs on `listing_revisions.listing_id` and `listings.source_listing_id`
- **Status:** fixed
- **Commit:** `f0d794f`
- **Files changed:** `migrations/023_listing_fk_constraints.sql` (new)
- **Verification:** phpunit green (migrations are part of test DB bootstrap).
- **Notes:** Idempotent via `information_schema.TABLE_CONSTRAINTS` check inside a stored procedure. `listing_revisions.listing_id` → `listings(id) ON DELETE CASCADE`; `listings.source_listing_id` → `listings(id) ON DELETE SET NULL`.

### CR-06 — `Support\Auth::boot()` TZ bug (same as `requireReAuth`)
- **Status:** **already_fixed** (no code change)
- **Commit:** N/A
- **Files changed:** none
- **Verification:** Inspected `src/Support/Auth.php:64-80` — the reviewer cited `strtotime($row['last_seen'])` at lines 64-66, but lines 70-77 already use `new DateTime($row['last_seen'], new DateTimeZone('Asia/Colombo'))` end-to-end. The "previous shape" wording in the comment refers to pre-fix code. The fix landed in an earlier phase (per AGENTS.md "known issues carried forward" + the comment narrative).
- **Notes:** IN-02 refactor extracts the same pattern into `parseLastSeenTimestamp()` so both call sites share the implementation — see IN-02.

### CR-07 — `listing_service::enforceRateLimit` uses `REMOTE_ADDR` only
- **Status:** fixed
- **Commit:** `717f8f8`
- **Files changed:** `src/Support/Network.php` (new), `src/Support/Router.php`, `src/Listing/Service/listing_service.php`
- **Verification:** phpunit phase-3-integration 175/176; phase-3-unit 10/10; phpcs clean.
- **Notes:** New `Support\Network::clientIp()` honors `X-Forwarded-For` ONLY when `REMOTE_ADDR` is in the `TT_TRUSTED_PROXIES` env-var CIDR list (empty by default — safe direct-connection default). IPv4 CIDR matching only (no IPv6 yet). Adopted in `Router` (route-level rate limit) and `listing_service::enforceRateLimit()`. The `listing_create` limit is per-user via the 3rd `$key` arg of `RateLimit::hit()`; the IP is no longer in the bucket key for per-user limits (the IP is still passed to `RateLimit::hit()` for audit / logging context, but is not part of the rate-limit bucket).

### CR-08 — Bootstrap CSS missing
- **Status:** fixed
- **Commit:** `230f279`
- **Files changed:** `public/assets/css/bootstrap.min.css` (new, 232 KB), `public/assets/js/bootstrap.bundle.min.js` (new, 80 KB), `src/Support/View/partials/head.php`
- **Verification:** Manual: every page that loads `head.php` now resolves `--bs-*` variables because the base Bootstrap CSS loads BEFORE the local override bundle. `window.bootstrap.{Modal,Tooltip}` are exposed by `bootstrap.bundle.min.js` (deferred) for `listing_modal.js` / `tickettrade.js`. phpunit green; phpcs clean (PHP only — CSS/JS not linted).
- **Notes:** Files downloaded from `cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/`. Local-only — no external CDN dependency in production (matches "no external deps" project constraint). AGENTS.md lists "Bootstrap 5.3 (CDN)" but the project values offline reproducibility more than the CDN form.

### WR-01 — `uploadImages` queries MAX(sort_order) via string concat
- **Status:** fixed
- **Commit:** `2ff6220`
- **Files changed:** `src/Listing/Service/listing_service.php`
- **Verification:** phpunit green.
- **Notes:** Prepared statement with the same `(int)` cast semantics; preserves the existing `countByListingId` first-image logic.

### WR-02 — `runAutoApproveSweep` string-concat SQL
- **Status:** fixed (documentation only)
- **Commit:** `7b5eb84`
- **Files changed:** `src/Listing/Service/listing_service.php`
- **Verification:** phpunit green.
- **Notes:** Already a prepared statement with no bound params. Added a comment documenting that the literals are hard-coded and the prepared form is intentional code-style uniformity.

### WR-03 — `EditListingAction::handle()` calls `saveDraft` on GET for rejected listings
- **Status:** fixed (documentation only)
- **Commit:** `5c7c739`
- **Files changed:** `src/Listing/Action/EditListingAction.php`
- **Verification:** phpunit green.
- **Notes:** The existing `=== 'rejected'` gate is one-shot — once flipped to draft, the next GET skips the write. Documented this is the minimum fix per reviewer recommendation; moving to a POST button is deferred to a future UX phase.

### WR-04 — `BrowseAction` depends on `review_service` for first-listing rating summary (N+1)
- **Status:** **deferred**
- **Commit:** N/A
- **Files changed:** none
- **Notes:** Per the reviewer's own note, this is a Phase 9 perf concern. No Phase 3 code change required.

### WR-05 — `cron_log` migration missing `IF NOT EXISTS`
- **Status:** fixed
- **Commit:** `23f3807`
- **Files changed:** `migrations/012_cron_log.sql`
- **Verification:** Migration runner is idempotent across re-runs.
- **Notes:** One-character change; matches the rest of the migrations.

### WR-06 — Dead `currentUser() === null` branches after `requireAuth`
- **Status:** fixed
- **Commit:** `9128b49`
- **Files changed:** `src/Listing/Action/EditListingAction.php`
- **Verification:** phpunit green.
- **Notes:** Both dead branches in `handle()` and `handlePost()` converted to `throw new \LogicException(...)`. This makes the unreachable code catch refactor regressions (e.g., removing `requireAuth` without a replacement guard) instead of silently emitting a misleading 404.

### WR-07 — `getWithImages` doesn't JOIN users for seller info
- **Status:** fixed
- **Commit:** `7946370`
- **Files changed:** `src/Listing/Service/listing_service.php`
- **Verification:** phpunit green.
- **Notes:** Single-table Model access preserved — the Service does an additional `SELECT nickname, tier, is_verified FROM users WHERE user_id = ?` (just the public fields) and sets `$row['seller_nickname']`, `$row['seller_tier']`, `$row['seller_is_verified']`. The modal now renders the real nickname for both initial render and AJAX swap.

### WR-08 — Inconsistent `$withImages['title']` vs `$titleStr` in `ListingFragmentAction`
- **Status:** fixed
- **Commit:** `248a978`
- **Files changed:** `src/Listing/Action/ListingFragmentAction.php`
- **Verification:** phpunit green; phpcs clean.
- **Notes:** Both the carousel partial (`$vars['title']`) and the JSON envelope (`'title' => $titleStr`) now use the same `$titleStr = (string) ($withImages['title'] ?? '')` value. Defines the strings once at the top of the section.

### IN-01 — Empty phpdoc stubs on `incrementSold` / `decrementSold`
- **Status:** fixed
- **Commit:** `63a996b`
- **Files changed:** `src/Listing/Model/listing_model.php`
- **Verification:** phpunit green.
- **Notes:** Dead docblocks removed. They were placeholders for Phase 4 ticket-creation methods that will be added when Phase 4 ships.

### IN-02 — `Auth::boot()` and `requireReAuth` parse `last_seen` twice
- **Status:** fixed
- **Commit:** `bc62535`
- **Files changed:** `src/Support/Auth.php`
- **Verification:** phpunit green; phpcs clean.
- **Notes:** Extracted `private static function parseLastSeenTimestamp(string $raw): ?int` — both `boot()` and `requireReAuth()` now use it. Eliminates the TZ-semantics drift risk called out by the reviewer.

### IN-03 — Test gap: end-to-end form-submit smoke
- **Status:** fixed
- **Commit:** `92e77f0`
- **Files changed:** `tests/Integration/Phase03/Listing/CreateListingFlowTest.php` (new test added), `src/Listing/Service/listing_service.php` (Service accepts `price_rupees`), `src/Listing/Action/CreateListingAction.php` (translation moved to Service)
- **Verification:** phpunit phase-3-integration 176/177 (only pre-existing failure), new `test_action_translates_price_rupees_to_price_cents` passes.
- **Notes:** Test passes `['price_rupees' => 1500, ...]` directly to `listing_service::createDraft` and asserts the resulting row has `price_cents = 150000`. The Service now accepts both forms (cents wins if both are sent) — this also closes CR-02 properly at the right boundary.

### IN-04 — `ListingAutoApproveAction` shim logs `error_log` on every call
- **Status:** fixed
- **Commit:** `a455cd0`
- **Files changed:** `src/Listing/Action/ListingAutoApproveAction.php`
- **Verification:** phpunit green; phpcs clean.
- **Notes:** Added a process-local `private static bool $warned = false;` guard so the deprecation log fires once per PHP process, not once per request. Removed-cron-shim cleanup is deferred to Phase 4 / plan 04-03 per the file's existing header comment.

## Pre-existing test status

`tests/Integration/Phase03/Support/RouteGuardListingTest::test_cron_route_is_admin_and_rate_limited` references `POST /admin/cron/ticket-expiry` — that route is not yet in `config/routes.php` (it ships in Phase 4 / OPS-03). This failure existed BEFORE my fixes and is not in scope for Phase 3 close-out. The test should be moved to phase-4-integration in a Phase 4 prep commit.

`tests/Unit/Phase03/Support/ImageProxyTest::test_thumb_rate_limit_returns_429_after_cap` is order-dependent on `cache_rate` state — random execution order occasionally hits the 61st call before prior tests clean up. Not introduced by these fixes.

---

_Fixed: 2026-09-05T12:30:00Z_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_