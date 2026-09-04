---
phase: "03"
slug: "marketplace-listings-discovery"
status: validated
nyquist_compliant: true
wave_0_complete: false
created: "2026-09-04"
---

# Phase 03 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.56 |
| **Config file** | `phpunit.xml` (testsuites: `phase-3-integration` -> `tests/Integration/Phase03/`, `phase-3-unit` -> `tests/Unit/Phase03/`) |
| **Quick run command** | `cd /home/user/hermesag/004/tickettrade && DB_DSN='mysql:unix_socket=/tmp/mysql.sock;dbname=tickettrade_test;charset=utf8mb4' DB_USER=user APP_ENV=test vendor/bin/phpunit --testsuite=phase-3-unit` |
| **Full suite command** | `cd /home/user/hermesag/004/tickettrade && DB_DSN='mysql:unix_socket=/tmp/mysql.sock;dbname=tickettrade_test;charset=utf8mb4' DB_USER=user APP_ENV=test vendor/bin/phpunit --testsuite=phase-3-integration --testsuite=phase-3-unit` |
| **Estimated runtime** | ~100s (unit ~2s + integration ~95s) |

**Test DB setup (required first time on a fresh host):**
1. `truncate -s 0 migrations/.applied`
2. `DB_DSN='mysql:unix_socket=/tmp/mysql.sock;dbname=tickettrade_test;charset=utf8mb4' DB_USER=user APP_ENV=test php migrate.php` (applies 16 migrations)

The default `config/db.test.php` DSN points at `/home/user/hermesag/004/db/mariadb.sock`; the local MariaDB on this host listens at `/tmp/mysql.sock` so the DSN env override is required.

---

## Sampling Rate

- **After every task commit:** Run `vendor/bin/phpunit --testsuite=phase-3-unit` (fast feedback, no DB reads)
- **After every plan wave:** Run the full phase-3 suite
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** ~100 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | LST-08, LST-10, LST-11, LST-12, PER-02, PER-03, SEC-03 | T-03-01..T-03-09 | Migrations idempotent; 4-layer image pipeline; auth-gated proxy; FULLTEXT search | unit + integration | `vendor/bin/phpunit --testsuite=phase-3-unit` + `phase-3-integration` | ✅ | ✅ green |
| 03-01-02 | 01 | 1 | SEC-03 (rate limit) | T-03-04, T-03-05 | Rate limit E_RATE_LIMIT after 20 listings/hr; ImageProxy 429+Retry-After | integration | `vendor/bin/phpunit --testsuite=phase-3-integration --filter test_21st_call_returns_rate_limit` | ✅ | ✅ green |
| 03-02-01 | 02 | 2 | LST-01, LST-02, LST-07, LST-09, LST-13, LST-14, LST-15, LST-16, SEC-04 | T-03-01, T-03-07 | CRUD Actions; state machine draft->pending->active; review_flag on edit; relist fast-track | integration | `vendor/bin/phpunit --testsuite=phase-3-integration --filter 'CreateListingFlow\|EditListingFlow\|DeleteListingFlow\|RelistFlow\|SubmitDraftFlow\|MyListingsTabs'` | ✅ | ✅ green |
| 03-02-02 | 02 | 2 | LST-16 (image delete/reorder) | T-03-07 | Image delete/reorder on edit UI | integration (partial) | Deferred to Phase 6 per `03-VERIFICATION.md` deferred-items | partial | ⚠️ deferred |
| 03-03-01 | 03 | 3 | LST-03, LST-04, LST-05, LST-06, LND-07, LND-08, PER-02 | T-03-09 | Corkboard board view; +/-2 deg rotation; modal with carousel/keys/swipe; guest vs buy-now gate; 50/page | integration | `vendor/bin/phpunit --testsuite=phase-3-integration --filter 'BrowseBoard\|Search\|Pagination\|GuestBrowse\|ModalRender\|EdgeCases'` | ✅ | ✅ green |
| 03-04-01 | 04 | 4 | LND-01, LND-02, LND-03, LND-04, LND-05, LND-06 | — | Landing page; team section; auto-approve cron (hand-triggered) | integration | `vendor/bin/phpunit --testsuite=phase-3-integration --filter 'HomeLanding\|TeamSection\|ListingAutoApproveSweep'` | ✅ | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky / deferred*

---

## Wave 0 Requirements

- [x] `tests/Integration/Phase03/` — 175 integration tests across Listing, Category, Migration, Landing, Support subdirs
- [x] `tests/Unit/Phase03/Support/` — 10 unit tests for ImageUpload (10) + ImageProxy (10)
- [x] `tests/Integration/Phase03/Fixtures/Fixtures.php` — shared fixtures (user + listing + category seeders)
- [x] `phpunit.xml` — `phase-3-integration` + `phase-3-unit` testsuites registered

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Corkboard paper-card surface visual (paper color `#FFF8E7`, +/-2 deg rotation, pushpin graphic) | LST-04, LND-08 | Pixel-level visual review; design system contract | Compare `public/mockups/board-mobile.html` to `src/Listing/View/board.php` output. Confirm `aria-hidden` on rotation/pin. |
| Landing page visual hierarchy (Hero "Every Trade Ends With Proof", Vision & Mission, 5-step How It Works, 6 team cards, Footer) | LND-01..LND-06 | Visual hierarchy + named copy + screenshot-ready state | `curl -s http://127.0.0.1:8000/` and check named-content presence. Open `/` in browser, screenshot for WAD video. |
| `prefers-reduced-motion: reduce` removes hover-lift transform and modal slide | LND-08, LST-04 | OS-level preference; not exercisable in CI | Enable Reduce Motion in OS, hover over a cork card — confirm no translateY. Open modal, swipe — confirm cross-fade. |
| Live `POST /admin/cron/ticket-expiry` flips a pending>24h listing to active with `approved_at=NOW(), approved_by=NULL` | LST-07, D-28/D-29 | Needs a listing aged 25h; live DB inspection | `mysql --socket=/tmp/mysql.sock -u user tickettrade_test -e "INSERT INTO listings (..., created_at) VALUES (..., NOW() - INTERVAL 25 HOUR)"` then POST the endpoint as admin with fresh re-auth, verify status flipped. |
| Image storage at `<root>/<sha256>_<size>.webp` (filename is SHA256 of original bytes, NOT user's filename) | LST-10, D-12 | Path-traversal mitigation is the design intent; CI proves layout exists, not the SHA256 invariant specifically | `uploadImages` with a real JPEG, then `ls public/uploads/listings/` — confirm the filename is the SHA256 hex, not the user's `IMG_1234.jpg`. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (none — 0 gaps)
- [x] No watch-mode flags
- [x] Feedback latency < 100s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-09-04 (gap analysis: 0 gaps; 185/185 tests green)

## Validation Audit 2026-09-04

| Metric | Count |
|--------|-------|
| Gaps found | 0 |
| Resolved | 0 |
| Escalated | 0 |

| Source | Count |
|--------|-------|
| Plans | 4 (03-01..03-04) |
| SUMMARYs | 4 |
| Tests (integration) | 175 |
| Tests (unit) | 10 |
| Tests (total) | 185 |
| Assertions (full suite) | 725 |
| Requirements (LST-*, LND-*, PER-*, SEC-*) | 23 (LST-01..16, LND-01..08 excluding LND-02 already covered, PER-02, PER-03, SEC-03, SEC-04) |

State B path: no `*-VALIDATION.md` existed; this document was reconstructed from PLAN + SUMMARY artifacts, cross-referenced against the on-disk PHPUnit suite, and confirmed green via `vendor/bin/phpunit --testsuite=phase-3-integration --testsuite=phase-3-unit`.
