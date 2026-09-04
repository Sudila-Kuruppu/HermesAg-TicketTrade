---
phase: "04"
slug: "purchases-tickets-lifecycle"
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-09-04"
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Reconstructed from `04-01-SUMMARY.md`, `04-02-SUMMARY.md`, `04-03-SUMMARY.md` (State B — no prior `*-VALIDATION.md`).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.56 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `APP_ENV=test vendor/bin/phpunit --testsuite=phase-4-unit` (~1.4s) |
| **Full suite command** | `APP_ENV=test vendor/bin/phpunit --testsuite=phase-4-integration` (~108s) |
| **Estimated runtime** | ~110 seconds (combined unit + integration) |

Notes:
- Test DB bootstrap: `bin/dev-setup.sh` creates `tickettrade_test`; `bin/test` rebuilds schema when `data/.test-schema-fingerprint` changes.
- The `[audit] write failed: ... 'action' at row 1` line in test output is the expected D-04 invariant — `Support\Audit::log()` NEVER throws on logging failure, returns `0` + emits `error_log`. It is not a test failure.

---

## Sampling Rate

- **After every task commit:** `APP_ENV=test vendor/bin/phpunit --testsuite=phase-4-unit`
- **After every plan wave:** `APP_ENV=test vendor/bin/phpunit --testsuite=phase-4-integration`
- **Before `/gsd-verify-work`:** Full suite (`vendor/bin/phpunit`) must be green
- **Max feedback latency:** ~110 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 04-01-T1 | 01 | 1 | TKT-01..05,08..12, BUY-01, REL-01,02,04,05,06 | T-04-01..04 | atomic UPDATE with rowCount===0 invalid branch | integration | `vendor/bin/phpunit --testsuite=phase-4-integration --filter=TicketCreation` | ✅ | ✅ |
| 04-01-T1 | 01 | 1 | TKT-08, FR-PTS-007,010 | — | sole-writer of `points_log` per AD-10 | integration | `vendor/bin/phpunit --filter=AwardTransaction` | ✅ | ✅ |
| 04-01-T1 | 01 | 1 | TKT-04, TKT-09 | — | dashed-form code `TK-XXXX-XXXX-XXXX-XXXX-XXXX`; retry on UNIQUE | integration | `vendor/bin/phpunit --filter=TicketCodeGenerator` | ✅ | ✅ |
| 04-01-T1 | 01 | 1 | AD-12, D-04 | — | `Audit::log()` never throws; forward-compat with Phase 8 hash chain | unit + integration | `vendor/bin/phpunit --filter=AuditStub` | ✅ | ✅ |
| 04-01-T1 | 01 | 1 | NFR-SEC-007, D-08 | — | `RateLimit::hit()` bucket key includes `$key` when non-empty | unit | `vendor/bin/phpunit --filter=RateLimitPerTicket` | ✅ | ✅ |
| 04-01-T1 | 01 | 1 | MIGRATION-04 | — | 4 new tables + `users.redeemed_count`; idempotent rerun | integration | `vendor/bin/phpunit --filter=MigrationTest` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | BUY-01,02, TKT-01..12, REL-* | T-04-18,20 | View escapes all dynamic values; queries scope by buyer_id/seller_id | integration | `vendor/bin/phpunit --filter=MyTicketsView\|PurchaseHistory\|BuyNowFlow` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | TKT-08,09,10, FR-PTS-007,010 | T-04-21 | cross-seller redemption blocked; rowCount===0 → E_TICKET_INVALID_STATE | integration | `vendor/bin/phpunit --filter=RedemptionFlow` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | TKT-09,10 | — | intermediate session no points; final session auto-redeems | integration | `vendor/bin/phpunit --filter=SessionConfirmFlow` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | TKT-05,11,12, D-03 | — | dispute sets `dispute_status='pending'`; flips status only if old=active | integration | `vendor/bin/phpunit --filter=DisputeFlow` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | TKT-06,07, D-05 | — | per-listing-group placement; redemption input at top | integration | `vendor/bin/phpunit --filter=SalesView` | ✅ | ✅ |
| 04-02-T1 | 02 | 2 | REL-* | T-04-24 | guest → 302 `/login?next=<path>` for ticket surfaces | integration | `vendor/bin/phpunit --filter=RouteGuardTicket` | ✅ | ✅ |
| 04-03-T1 | 03 | 3 | TKT-06..10, REL-01,02,04,05,06 | T-04-26,27,28 | admin re-auth + rate-limit + single guarded UPDATE per sweep | integration | `vendor/bin/phpunit --filter=CronSweep` | ✅ | ✅ |
| 04-03-T1 | 03 | 3 | NFR-REL-002, TKT-08, D-07 | T-04-27 | re-running 5× produces same end state | integration | `vendor/bin/phpunit --filter=Idempotency` | ✅ | ✅ |
| 04-03-T1 | 03 | 3 | TKT-08, D-07 | — | pre-dispute status restored (active→active, redeemed→redeemed); created_at NEVER touched | integration | `vendor/bin/phpunit --filter=DisputeAutoDismiss` | ✅ | ✅ |
| 04-03-T1 | 03 | 3 | TKT-06,07,09, AD-7 | T-04-29 | product decrement 1; service decrement `total_sessions - (session_number-1)`; sold→active restore | integration | `vendor/bin/phpunit --filter=TicketExpiry` | ✅ | ✅ |
| 04-03-T1 | 03 | 3 | NFR-PER-004 | T-04-29 | 10k tickets < 30s | integration | `vendor/bin/phpunit --filter=Performance` | ✅ | ✅ |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `phpunit.xml` — testsuites `phase-4-unit` (7 tests) and `phase-4-integration` (106 tests) registered
- [x] `tests/Integration/Phase04/Fixtures/Fixtures.php` — shared seeders (users, listings, tickets, auth dispatch)
- [x] `bin/dev-setup.sh` + `bin/test` — DB bootstrap + suite entry point
- [x] PHP framework: `phpunit/phpunit` (in `require-dev` per `composer.json`); no further install needed

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual ticket-code-block mask/reveal/copy/WhatsApp UX (CSS amber on near-black, letter-spacing 0.04em) | TKT-02, DESIGN.md | CSS-rendering / JS behavior — covered by partial markup in View tests but not visual | Open `/my-tickets` in browser; confirm masked default; click reveal; click copy (clipboard toast); confirm WhatsApp link `https://wa.me/?text=...` opens with prefilled code |
| Toast-on-redirect auto-focus on new ticket card (D-02 inline script) | BUY-01, D-02 | JS focus timing — partial markup in MyTicketsViewTest, runtime focus requires browser | Buy a listing; confirm `?new=<id>` redirect; confirm focus ring on freshly-rendered card |
| `data-scrim-guard="2"` backdrop suppression on dispute modal | TKT-11, EXPERIENCE.md L157 | JS backdrop click timing — markup assertion only | Open dispute modal; click backdrop within 2s; modal stays open; click after 2s; modal closes |
| WhatsApp share disabled-state tooltip when seller has no WhatsApp | EXPERIENCE.md L146 | Browser tooltip UX | View ticket where seller's `users.whatsapp` is NULL; hover share button; confirm tooltip "Seller has not shared WhatsApp" |

*Core business logic (atomic UPDATE guards, points halving, idempotency, audit row appends) is fully covered by integration tests. Visual/JS behaviors are the only manual-only verifications and follow from the markup contracts verified by View tests.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (existing infra: phpunit + dev-setup + Fixtures)
- [x] No watch-mode flags
- [x] Feedback latency ~110s for full phase-4 suite (under reasonable threshold)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-09-04

---

## Validation Audit 2026-09-04

| Metric | Count |
|--------|-------|
| Test files | 23 (5 cron + 8 ticket-flow/views + 1 migration + 1 fixtures + 1 points + 1 audit-integration + 2 cron perf/idemp + 2 unit) |
| Tests | 113 (7 unit + 106 integration) |
| Assertions | 1437 |
| Gaps found | 0 |
| Resolved | n/a |
| Escalated to manual-only | 4 (visual/JS UX — see table) |
| Phase 4 full-suite result | OK (113 tests, 1437 assertions) |

State B reconstruction from SUMMARY artifacts confirmed all must-haves map to existing automated tests; no new test files generated.
