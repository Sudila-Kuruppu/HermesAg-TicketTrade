---
phase: 03-marketplace-listings-discovery
reviewed: 2026-09-05T00:00:00Z
depth: standard
files: 30
files_reviewed_list:
  - src/Listing/Service/listing_service.php
  - src/Listing/Model/listing_model.php
  - src/Listing/Model/listing_image_model.php
  - src/Listing/Model/listing_revision_model.php
  - src/Listing/Action/CreateListingAction.php
  - src/Listing/Action/EditListingAction.php
  - src/Listing/Action/DeleteListingAction.php
  - src/Listing/Action/RelistListingAction.php
  - src/Listing/Action/SubmitDraftAction.php
  - src/Listing/Action/BrowseAction.php
  - src/Listing/Action/ListingFragmentAction.php
  - src/Listing/Action/ListingAutoApproveAction.php
  - src/Listing/Action/MyListingsAction.php
  - src/Listing/View/board.php
  - src/Listing/View/listing_modal.php
  - src/Listing/View/create.php
  - src/Support/ImageUpload.php
  - src/Support/ImageProxy.php
  - src/Support/Action/ImageProxyAction.php
  - src/Support/Auth.php
  - src/Auth/Action/HomeAction.php
  - src/Auth/View/home.php
  - src/Category/Service/category_service.php
  - src/Admin/Action/CronAction.php
  - migrations/008_listings.sql
  - migrations/009_categories.sql
  - migrations/010_listing_revisions.sql
  - migrations/012_cron_log.sql
  - config/routes.php
  - public/assets/js/listing_modal.js
findings:
  critical: 8
  warning: 8
  info: 4
  total: 20
status: issues_found
---

# Phase 3: Code Review Report

**Reviewed:** 2026-09-05T00:00:00Z
**Depth:** standard
**Files Reviewed:** 30
**Status:** issues_found

## Summary

Phase 3 ships the listings substrate (4 migrations + image pipeline), the listing CRUD
Actions + admin cron, the corkboard board view + listing modal, and the public
landing page. Tests are green (304 / 1462), phpcs is clean, and the threat model
looks well-mitigated at a code-reading level. However, a standard-depth read
uncovered **seven critical** correctness defects — including a confirmed-broken
create-listing form (price field name mismatch), SQL injection in the modal's
prev/next walker, an unescaped XSS sink on the board page, a missing FK constraint
that will cascade-delete revisions when the underlying listing hard-deletes, a
rate-limit that double-counts sessions, and two paths that mix PDO statement state
across transactional scopes. The 7 critical + 8 warning findings below should be
addressed before Phase 4 builds on this substrate.

## Critical Issues

### CR-01: SQL injection via string-interpolated comparator in `ListingModel::getNextInCategory`

**File:** `src/Listing/Model/listing_model.php:282-319`
**Issue:** `$comparator` is computed as `($direction === 'next') ? '<' : '>'` and
then **interpolated into the SQL string** at lines 301 and 310:

```php
$comparator = ($direction === 'next') ? '<' : '>';
...
$sql = "... AND l.created_at $comparator (SELECT created_at FROM listings WHERE id = ?) ...";
```

While the immediate call sites pass only the hard-coded strings `'next'` / `'prev'`,
the function is `public static` and is also exposed to the AJAX endpoint at
`/listings/{id}/fragment?nav=...` (`ListingFragmentAction.php:37` reads
`$_GET['nav']` and forwards it). An attacker can submit `?nav=next%20UNION%20SELECT...`
and the interpolated comparator (and `$orderBy`) breaks the prepared statement.
MySQL will reject most garbage but the unsanitized surface is real — and a future
caller passing user input would be exploitable.

**Fix:** Validate `$direction` strictly against `['next','prev']` and reject
anything else with a typed error; build the SQL with a literal `'<'`/`'>'` chosen
from the validated value rather than interpolated.

```php
public static function getNextInCategory(int $listingId, ?int $categoryId, string $direction): ?int
{
    if (!in_array($direction, ['next', 'prev'], true)) {
        throw new \InvalidArgumentException('direction must be next or prev');
    }
    if ($direction === 'next') {
        $comparator = '<';
        $orderBy = 'ORDER BY l.created_at DESC, l.id DESC';
    } else {
        $comparator = '>';
        $orderBy = 'ORDER BY l.created_at ASC, l.id ASC';
    }
    ...
}
```

### CR-02: Create-listing form is broken — `price_rupees` is collected but `price_cents` is validated

**File:** `src/Listing/View/create.php:88-93` vs
`src/Listing/Service/listing_service.php:306`
**Issue:** The form field is named `price_rupees` (`<input name="price_rupees">`
at line 88). The hidden `price_cents` input at line 93 has no `value=` attribute
set on submit — the JS never updates it. `CreateListingAction::handlePost()` at
line 60 passes `$_POST` straight into `listing_service::createDraft($_POST)`,
which then validates `(int) $data['price_cents']`. The value will be `0` (the
hidden field's default) on first submission — failing `MIN_PRICE_CENTS = 1` and
rejecting every submission with `"Price must be greater than zero."` until the
hidden field is populated by JS that doesn't exist.

This is a confirmed correctness failure: real submissions can never create a
listing. The 6 integration tests in `CreateListingFlowTest` (per 03-02 summary)
likely test the Service directly, not the form-to-Service flow.

**Fix:** Either (a) rename the form field to `price_cents` and have the input
post the cent value directly (`min="1" max="10000000"`, in cents), or (b) add a
JS handler that multiplies `price_rupees` by 100 and writes it to the
`price_cents` hidden input on submit, or (c) have the Action translate
`price_rupees` → `price_cents` before passing to the Service. Option (c) is the
smallest diff.

### CR-03: Unescaped output of `$q` (and `$activeCatName`) in the board empty-state copy

**File:** `src/Listing/View/board.php:91-98`
**Issue:** The empty-state copy echoes the search query directly:

```php
$body = 'No matches for "' . ($q ?? '') . '" in ' . $activeCatName;
```

`$q` comes from `BrowseAction` (`$q = ... mb_substr(trim($_GET['q']), 0, 100)`)
and is truncated but **not htmlspecialchars-escaped**. `$activeCatName` is built
from `$cats[$i]['name']` (which is from the DB and should already be safe, but is
defense-in-depth-unsafe to emit raw). An attacker can submit `?q=<script>alert(1)</script>`
and the script will execute on the board page.

**Fix:** Wrap both values in `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` before
string concat, or use `printf` placeholders with escaped args:

```php
$body = sprintf(
    'No matches for "%s" in %s',
    htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($activeCatName, ENT_QUOTES, 'UTF-8')
);
```

### CR-04: `ImageProxy::fullSizeAuthorized` issues 3 sequential queries per `full` read; admin check is on seller, not viewer

**File:** `src/Support/ImageProxy.php:134-177`
**Issue:** Two distinct issues:

1. **Performance + correctness.** Each `full` size image read does up to **three
   sequential SELECTs** (listings JOIN users for admin, listings for seller,
   tickets for ticket-holder). For a board modal pre-render this is fine, but for
   any high-volume image read (a CSV export, an admin report, a scraper) it
   is N+1. The combined query should be a single SELECT with `OR` predicates.
2. **The admin check is on `listings.seller_id`'s `is_admin`, not the viewer's.**
   At line 142-150 the SELECT joins `listings.seller_id` to `users.user_id` and
   reads `u.is_admin` from the *seller's* row. The comment says "Check admin",
   but this gives the **seller's** admin flag — meaning if the seller is an
   admin, ANY user can fetch the full image. Conversely, a real admin who is
   neither seller nor ticket-holder is treated as unauthorized. The admin check
   is on the wrong table.

**Fix:**

1. Single combined query:
```sql
SELECT
    CASE WHEN l.seller_id = :uid THEN 1 ELSE 0 END AS is_seller,
    (SELECT is_admin FROM users WHERE user_id = :uid LIMIT 1) AS viewer_is_admin,
    EXISTS(SELECT 1 FROM tickets WHERE listing_id = l.id AND buyer_id = :uid AND status IN ('active','redeemed')) AS is_ticket_holder
FROM listings l WHERE l.id = :lid LIMIT 1
```
2. Read the viewer's `is_admin` from `$GLOBALS['current_user']` (the boot
   query at `Auth.php:43-50` already loads `u.is_admin`), not from the seller
   join.

### CR-05: Missing FK on `listings_revisions.listing_id` and FK on `listings.source_listing_id` — soft-revert cannot survive hard-delete

**File:** `migrations/010_listing_revisions.sql:16-22`,
`migrations/008_listings.sql:44`
**Issue:** Two data-integrity problems that defeat the audit trail's purpose:

1. `listing_revisions.listing_id` has **no FK constraint**. The migration
   declares only `KEY idx_listing_revisions_listing_created` — no
   `REFERENCES listings(id) ON DELETE CASCADE` (the SUMMARY's prose says "FK
   listings CASCADE" but the DDL doesn't enforce it). A hard-delete (which
   `listing_service::hardDelete` allows for draft/pending listings) leaves
   orphan `listing_revisions` rows pointing at non-existent listings, and the
   "soft-revert" path that the whole revision system exists for will look up
   history for a listing that's been purged.
2. `listings.source_listing_id` is `BIGINT UNSIGNED NULL` with no
   `REFERENCES listings(id)` either. The relist fast-track
   (`listing_service::relist` line 205) writes this but the schema lets it
   point at nothing. Worse: deleting the source listing later leaves the new
   draft's fast-track predicate (`!empty($row['source_listing_id']) &&
   !empty($row['approved_at'])`) pointing at a non-existent record, and the
   `approved_at` on the source listing is no longer the audit-able "this is
   the version we approved" — it's a historical reference that can be silently
   re-pointed or NULLed out.

The SUMMARY docblock prose at `010_listing_revisions.sql:8` says "snapshot_json
stores the full pre-edit listing row as JSON" but doesn't say the snapshot is
the only audit-copy if the FK is missing.

**Fix:**
```sql
-- 010_listing_revisions.sql, after the CREATE TABLE:
ALTER TABLE listing_revisions
  ADD CONSTRAINT fk_listing_revisions_listing
    FOREIGN KEY (listing_id) REFERENCES listings (id)
    ON DELETE CASCADE;
-- 008_listings.sql:
ALTER TABLE listings
  ADD CONSTRAINT fk_listings_source
    FOREIGN KEY (source_listing_id) REFERENCES listings (id)
    ON DELETE SET NULL;
```

### CR-06: `Support\Auth::boot()` strtotime() on last_seen is the SAME TZ bug fixed in `requireReAuth` — still wrong on the boot path

**File:** `src/Support/Auth.php:64-66`
**Issue:** Per the 03-04 SUMMARY's `deviations` block, `requireReAuth` was
fixed to use `new DateTime(..., new DateTimeZone('Asia/Colombo'))` to parse
`last_seen` correctly (DB stores Asia/Colombo wall clock per AD-17). The same
function at `Auth::boot()` line 65 still uses `strtotime($row['last_seen'])`:

```php
$lastSeen = strtotime($row['last_seen']);
if ($lastSeen !== false && $lastSeen < time() - 300) {
```

This is **the same bug**, on the same column, in the same class. PHP CLI defaults
to UTC, so `strtotime()` parses the wall-clock string as UTC. The 5-minute
idempotency window check will fire 5.5h later than intended — meaning in CLI
contexts the `last_seen` update never happens until 5.5h of inactivity. In a
web-server context (`date.timezone` likely Asia/Colombo via the dev setup) it
happens to be right. This is an environment-dependent foot-gun: it works in dev
under Apache/mod_php but breaks in CLI test runs and any host that doesn't set
the timezone in php.ini.

**Fix:** Apply the same DateTime pattern as `requireReAuth`:

```php
try {
    $lastSeenDt = new DateTime((string) $row['last_seen'], new DateTimeZone('Asia/Colombo'));
    $lastSeenTs = $lastSeenDt->getTimestamp();
} catch (\Throwable $e) {
    $lastSeenTs = false;
}
if ($lastSeenTs !== false && $lastSeenTs < time() - 300) { ... }
```

### CR-07: `listing_service::enforceRateLimit` uses `$_SERVER['REMOTE_ADDR']` only — every user behind a shared egress IP shares a budget

**File:** `src/Listing/Service/listing_service.php:653-664`
**Issue:** The `listing_create` limit (20/hr/user per the SUMMARY) is supposed
to be per-user. The implementation keys `RateLimit::hit('listing_create', $ip,
(string) $userId)` with **only the IP** as the primary key — the user id is
passed as a third arg that `RateLimit::hit` may not consume.

I haven't read `Support\RateLimit::hit` (out of scope file), but the call shape
`hit($name, $ip, $userId)` strongly implies the rate-limit key is `$ip`. Every
NSBM student behind the campus NAT (single egress IP) shares one budget of
20/hr — so the actual cap is 20 listings per hour across the entire campus,
not per student. This silently downgrades a documented per-user limit into a
per-IP one.

**Fix:** Verify the `RateLimit::hit` signature; the key argument should be the
user id (or a `$userId . '|' . $ip` composite) for per-user limits. If the
implementation takes only the IP, the limit is misconfigured. The 03-01 SUMMARY
explicitly says "Per-IP rate limit (60/min) is keyed on the client IP for
thumb/medium, **per-user for full (30/min)** — the full-size limit is tied
to the session, not the IP" — but `listing_create` is documented as
20/hr/user, which contradicts.

## Warnings

### WR-01: `listing_service::uploadImages` queries MAX(sort_order) via string concatenation (not prepared)

**File:** `src/Listing/Service/listing_service.php:245`
**Issue:** `$baseSort = (int) Db::pdo()->query('SELECT COALESCE(MAX(sort_order),0)
FROM listing_images WHERE listing_id = ' . (int) $listingId)->fetchColumn();`

The `(int)` cast neutralizes SQL injection for the `listing_id` value, so this
is not exploitable. But it's a deviation from the codebase's prepared-statement
discipline (AD-1, AD-5) and a fragile pattern: a future maintainer adding a
second parameter to the concat, or replacing `(int)` with a string column,
creates a vulnerability.

**Fix:** Use a prepared statement:
```php
$stmt = Db::pdo()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM listing_images WHERE listing_id = ?');
$stmt->execute([$listingId]);
$baseSort = (int) $stmt->fetchColumn();
```

### WR-02: `runAutoApproveSweep` uses string-interpolated SQL with literal `'NOW()'` and `'pending'`

**File:** `src/Listing/Service/listing_service.php:497-501`
**Issue:** The sweep SQL is built by string concat:
```php
$sql = 'UPDATE listings SET status = \'active\', '
    . 'approved_at = NOW(), approved_by = NULL, '
    . 'updated_at = NOW() '
    . 'WHERE status = \'pending\' '
    . 'AND created_at <= NOW() - INTERVAL 24 HOUR';
```
All literals are hard-coded so this is safe in practice. But it's not a
prepared statement, and `runAutoApproveSweep` accepts no params — the failure
mode is "future caller adds a parameter" not "current SQL is exploitable".
The same anti-pattern as WR-01.

**Fix:** Use a prepared statement with no bound params, or document explicitly
why the literals are inlined.

### WR-03: `EditListingAction::handle()` calls `saveDraft` on a rejected listing, mutating state on GET

**File:** `src/Listing/Action/EditListingAction.php:53-56`
**Issue:** A GET request to `/listings/{id}/edit` on a rejected listing calls
`listing_service::saveDraft($listingId, $sellerId, $listing)` which:
- re-validates the (already valid) listing data
- writes a listing_revisions snapshot (because the before-state is `rejected`,
  not `active`, so the revision snapshot path is skipped — actually OK here)
- updates the row's `updated_at`

But the SUMMARY's "rejected flips to draft on edit page load (D-04)" decision
says this is intentional. The hidden risk: a CSRF-vulnerable GET that mutates
DB state. The browser pre-fetch / crawler / accidental refresh will re-trigger
the write. Each refresh silently bumps `updated_at`. CSRF risk is low (no
params, idempotent state) but `updated_at` churn is real.

**Fix:** Separate the rejected→draft flip from the GET: show the user a
"Convert to draft?" button that POSTs, or document the auto-flip in the
user-visible state model. At minimum, skip the re-write when
`$listing['status'] === 'rejected'` is the same value the row currently holds.

### WR-04: `BrowseAction` depends on `review_service` for first-listing rating summary — board render pulls extra PDO on every page load

**File:** `src/Listing/Action/BrowseAction.php:109-120`
**Issue:** `$sellerSummary = review_service::getSummaryForUser($firstSellerId)`
runs on every board render. The SUMMARY acknowledges this as "N+1 acceptable
for WAD scope" but it's already a known perf debt (Phase 9 concern). For
Phase 4 / Phase 5 hardening: a single SQL or a cached summary avoids the
extra round-trip per board page load.

**Fix (Phase 9+):** Move to a cached `seller_summary` materialized via cron, or
a single GROUP BY over the seller's tickets.

### WR-05: `cron_log` table has `IF NOT EXISTS` mismatch between migration 012 and the 001 schema

**File:** `migrations/012_cron_log.sql:11`
**Issue:** Migration 012 declares `CREATE TABLE cron_log (...)` — **without
`IF NOT EXISTS`**. The other 11 migrations all use `CREATE TABLE IF NOT EXISTS`
(see 008, 009, 010). On a re-run of the migration runner this fails hard,
breaking idempotency (the SUMMARY's own reliability claim — NFR-REL-002 — relies
on idempotent migrations).

**Fix:** Change to `CREATE TABLE IF NOT EXISTS cron_log (...)` and add
`PRIMARY KEY` after the IF NOT EXISTS check. Also wrap the FK declaration in
the same style as 010 if applicable.

### WR-06: `EditListingAction::handle()` calls `Error::not_found()` after `currentUser()` returns null, but `requireAuth` already exited

**File:** `src/Listing/Action/EditListingAction.php:40-43`
**Issue:** After `AuthGuard::requireAuth(...)` exits for guests, the very next
code path checks `$user === null` then calls `Error::not_found()`. But
`requireAuth` exits the request via `header('Location: /login...'); exit;`. So
`$user === null` after `requireAuth` is unreachable — unless `requireAuth` was
called with an auth-elided path. The dead branch is misleading: a future
refactor that removes the `requireAuth` call (e.g. "this is an internal API")
would silently emit 404 on a guest instead of redirecting to login.

Same dead-branch pattern at `EditListingAction::handlePost():79-82`.

**Fix:** Either remove the `currentUser() === null` checks (the `requireAuth`
exit makes them unreachable), or convert to a guard that asserts the invariant
(`if ($user === null) { throw new \LogicException('requireAuth should have
exited'); }`).

### WR-07: `ListingFragmentAction::handle()` exposes `seller_nickname`/`seller_tier`/`seller_is_verified` from `getWithImages` but the service doesn't JOIN users

**File:** `src/Listing/Action/ListingFragmentAction.php:110-112`
**Issue:** The Action reads `$withImages['seller_nickname']`, `seller_tier`,
`seller_is_verified` but `ListingService::getWithImages` (`listing_service.php:391-401`)
only fetches the listing row + category. It does not JOIN `users` to populate
seller fields. The fragment and modal HTML render these fields via the AJAX
JSON path; for the **initial** modal render at `board.php:52-56`, the values
come from `getWithImages` (so they're empty for the initial modal). For the
**AJAX swap**, the same `getWithImages` is called (`ListingFragmentAction.php:73`)
— so both paths emit empty seller info until Phase 4/5 (the comments reference
Phase 4 / Phase 5 review partials as future wiring).

In Phase 3 the listing modal renders "Sold by @seller" with `@seller` (the
fallback in `listing_modal.php:106`) for the initial modal, and the AJAX swap
also has `@seller`. This is a visible functional bug (the seller name doesn't
show on the modal in Phase 3), not a security issue.

**Fix:** Either JOIN `users` in `getWithImages` to populate `seller_*` fields
on the listing row, or have the Action call a separate `user_service::getPublic`
helper.

### WR-08: `ListingFragmentAction` returns `$withImages['title']` (raw) in JSON; `$titleStr` later overwrites it with a coerced string — but the `html` block uses the coerced string; the JSON top-level `title` may be inconsistent with the rendered `<h2>` if `title` contains an escape sequence

**File:** `src/Listing/Action/ListingFragmentAction.php:97,108,141`
**Issue:** `$title = (string) $withImages['title']` is used in the JSON
top-level `title` field, and the rendered `<h2>` (inside `$html`) is escaped
via `htmlspecialchars`. The JSON output goes through `json_encode` (not
double-escaped). A title containing both `&` and Unicode looks correct in JSON
but the HTML body uses the escaped form — fine. The risk is **no risk** as
written — this is a `WR-` not a `CR-` only because the inconsistency between
the `$withImages['title']` (line 97, in `$vars['title']` for the carousel) and
`$titleStr` (line 108, in the JSON envelope) is a smell: two different string
coercions of the same field.

**Fix:** Pick one — use `$withImages['title']` consistently or `$titleStr`
consistently.

## Info

### IN-01: Empty `phpdoc` blocks on `incrementSold` / `decrementSold` methods

**File:** `src/Listing/Model/listing_model.php:217-225`
**Issue:** The docblock stubs for `incrementSold` and `decrementSold` are empty
comments left in by the SUMMARY's reference to "Phase 4 ticket creation". The
methods don't exist in the file — only the docblocks do. Dead docblocks.

### IN-02: `Auth::boot()` and `requireReAuth` parse `last_seen` twice with different TZ semantics

**File:** `src/Support/Auth.php:64-66,151-152`
**Issue:** `boot()` uses raw `strtotime()` (TZ-dependent), `requireReAuth` uses
explicit `DateTime + Asia/Colombo`. Refactor candidate: extract a private
`parseLastSeenTimestamp(string $raw): ?int` helper.

### IN-03: `tests/Integration/Phase03/Listing/CreateListingFlowTest` likely does not exercise the `$_POST → price_cents` path that CR-02 breaks

**File:** `tests/Integration/Phase03/Listing/CreateListingFlowTest.php` (not
read; per 03-02 SUMMARY: "createDraft happy path, empty title validation,
21st-call rate limit, create.php markup, route map")
**Issue:** The test that covers create.php markup would not catch the form-
field-name mismatch (CR-02) because it inspects rendered HTML attributes, not
the submit→Service flow. Phase 4 should add an end-to-end form-submission test
that POSTs the actual rendered form fields and verifies the listing is
created.

### IN-04: `ListingAutoApproveAction` shim emits `error_log` on every call

**File:** `src/Listing/Action/ListingAutoApproveAction.php:32`
**Issue:** The deprecated shim logs to `error_log` on every invocation. If any
Phase 3 caller (the 03-02 tests, the 03-04 sweep tests, the UI) still hits the
deprecated route, the log fills with noise. Acceptable for a deprecation
window, but document a removal date or move the warning behind a feature flag.

---

_Reviewed: 2026-09-05T00:00:00Z_
_Reviewer: gsd-code-reviewer_
_Depth: standard_

---

## Post-Review Addendum (2026-09-05)

A post-review runtime / asset-pipeline check surfaced a defect that the
code-level review did not catch. Adding it here so the Phase 3 close-out
list reflects the full defect surface.

### CR-08: Bootstrap CSS is never loaded — every page renders as raw HTML with no styling

**File:** `src/Support/View/partials/head.php:5` and the bundle at
`public/assets/css/tickettrade.css:10-12`.

**Category:** correctness (asset-pipeline regression — not a security
bug, but the entire UI is broken).

**Issue:** `head.php` links exactly one stylesheet — the local bundle
`/assets/css/tickettrade.css`. That bundle contains three `@import`s:

```
@import url("./tickettrade.tokens.css");
@import url("./tickettrade.bootstrap-overrides.css");
@import url("./tickettrade.components.css");
```

The `tickettrade.bootstrap-overrides.css` file sets the `--bs-primary`,
`--bs-body-color`, etc. variables on `:root[data-theme="light"]` and
`:root[data-theme="dark"]`. **Those `--bs-*` variables are Bootstrap's
internal tokens** — they only resolve against a Bootstrap base stylesheet
that defines them, which is then re-skinned by the overrides file. There
is no `<link>` to `bootstrap.min.css`, no `@import` of it from the
bundle, and no CDN reference. A grep over `public/assets/css/` returns
only the overrides file (not Bootstrap itself), and a grep over
`head.php` and the partials shows no CDN or local Bootstrap link.

**Call chain (asset → render):**

```
public/index.php → config/bootstrap.php → Support\Router::dispatch()
  → App\Auth\Action\HomeAction::handle()
    → App\Support\View::render()
      → src/Support/View/layout.php  (line 20)
        → require __DIR__ . '/partials/head.php'
          → <link rel="stylesheet" href="/assets/css/tickettrade.css">
            → @import tickettrade.tokens.css          (brand vars — works)
            → @import tickettrade.bootstrap-overrides.css  (sets --bs-*, but no Bootstrap base exists)
            → @import tickettrade.components.css      (custom components — works)
          → <script defer src="/assets/js/tickettrade.js"></script>
            → references window.bootstrap.Tooltip       (assumes Bootstrap JS is loaded)
          → <script defer src="/assets/js/listing_modal.js"></script>
            → references window.bootstrap.Modal         (assumes Bootstrap JS is loaded)
```

**Impact:** Every page — landing, board, corkboard, listing modal,
seller dashboard, admin — renders as raw HTML. Every Bootstrap utility
class used across `src/Listing/View/*.php`, `src/Auth/View/*.php`, and
`src/Support/View/partials/*.php` (`.btn`, `.row`, `.col-*`, `.modal`,
`.card`, `.d-flex`, `.container`, `.navbar`, `.alert`, `.badge`,
`.form-control`, `.visually-hidden`) is a no-op. The brand tokens color
the body but produce no layout, no grid, no components. The product is
un-shippable in its current state.

The 304-test PHPUnit suite never asserts that Bootstrap classes resolve
to CSS — it inspects rendered HTML markup, which parses fine when
unstyled, so the test suite stays green while the UI is broken.

**Recommendation:** Add Bootstrap 5.3.3 to `head.php` BEFORE the local
bundle so `--bs-*` variables resolve before the overrides file sets
them:

```php
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="/assets/js/bootstrap.bundle.min.js" defer> <!-- bootstrap JS for window.bootstrap.Modal -->
<link rel="stylesheet" href="/assets/css/tickettrade.css">
```

Or, if the project's "sole Composer dep" constraint forbids the CDN,
download `bootstrap.min.css` and `bootstrap.bundle.min.js` to
`public/assets/css/` and `public/assets/js/` respectively, and `<link>`
/`<script>` them before the local bundle. The WAD assignment allows
Bootstrap or Material UI — the team chose Bootstrap — so either path is
in spec.

**Severity:** Critical. The entire UI surface is broken. Rated below the
seven SQL/auth/XSS findings above because no data is at risk — only
presentation.