# Plan Check v2 — Phase 1: UX Foundation & Design System

**Reviewer:** gsd-plan-checker (second pass after planner revision)
**Plans reviewed:** 01-01, 01-02
**Date:** 2026-09-XX (revision review)
**Source phase directory:** `tickettrade/.planning/phases/01-ux-foundation-design-system/`
**Previous report:** `01-PLAN-CHECK.md` (4 BLOCKERS, 15 warnings)

## Verdict

**`## ISSUES FOUND`** — 0 original BLOCKERS remain unfixed, but 1 NEW BLOCKER was introduced by the BLOCKER-1 fix.

The planner addressed three of the four original BLOCKERS cleanly:
- BLOCKER-2 (action spec throws vs verify expects 200) — FIXED. The action now renders a Phase 1 stub landing page (HTTP 200) from `src/Support/View/landing.php`.
- BLOCKER-3 (verify command 1 `< 60` threshold) — FIXED. The command now uses `--exclude=tickettrade.tokens.css` with a `== 0` threshold.
- BLOCKER-4 (toast forward dependency) — FIXED. The 01-01 must_haves, action spec, and `<done>` block correctly scope to setTheme/getTheme/prefersReducedMotion plus a 1-line toast stub. Plan 01-02 builds the real toast module from scratch via `Object.assign(window.TicketTrade || {}, ...)`.

BLOCKER-1 (verify command 5 trailing `; true`) is **structurally fixed per the task brief's criteria** — the new command uses `set -e`, an explicit `kill $PHP_PID; wait`, and the final `[ "$STATUS_MOCKUP" = "200" ] && [ "$STATUS_ROOT" = "200" ] && [ "$STATUS_ADMIN" = "200" ]` exit check, with no trailing `; true`. However, **the fix introduced a new bug**: the subshell pattern `(php -S ... &)` does not propagate `$!` to the parent shell in bash, so `PHP_PID` is empty. `kill $PHP_PID 2>/dev/null` then exits with code 1, and `set -e` terminates the script before the final `[ ... = 200 ]` check can run. Live test (bash 5.2.37) confirms a correct implementation returns exit code 2 (not 0), and the `[ ... = 200 ]` test never executes. This is a new BLOCKER.

A minor inconsistency in the 01-01 plan: the `fails_when` for verify command 6 still says "Phase 1 ships the minimum viable router that throws on empty route map" but the action spec now says it renders a Phase 1 stub landing page (HTTP 200). The actual grep test (does `class Router` exist?) works either way; the fails_when text is misleading.

Three of the targeted warnings (WARN 5 htaccess admin, WARN 8 bootstrap version, WARN 11 toast 4s/8s) were fixed cleanly. The remaining 12 warnings are quality issues that do not block execution per the gates taxonomy.

## BLOCKER Status

| ID | Original Issue | Status | Evidence |
|----|---------------|--------|----------|
| BLOCKER-1 | Verify 5 trailing `; true` | STRUCTURALLY FIXED but REGRESSED (new bug introduced) | Verify now uses `set -e`, `kill $PHP_PID; wait $PHP_PID`, and the `[ ... = 200 ]` final check. But live test shows the verify exits 2 (kill on empty PHP_PID triggers set -e), not 0, on a correct implementation. |
| BLOCKER-2 | Action throws RuntimeException vs verify expects 200 | FIXED | Action now reads: "When the map is empty, it renders a Phase 1 stub landing page (HTTP 200) ... The stub HTML lives in `src/Support/View/landing.php`". The `; true` text and the `throws a RuntimeException` language are removed from the action. Verify expects 200 for `/`, `/admin/`, and `/mockups/board-mobile.html`. |
| BLOCKER-3 | Verify 1 threshold `< 60` | FIXED | Command now: `grep -RIn --include='*.css' --include='*.js' --include='*.php' --include='*.html' -E '#[0-9A-Fa-f]{{3,8}}\b' public/ config/ --exclude=tickettrade.tokens.css \| wc -l \| awk '{{ if ($1 == 0) exit 0; ... }}'`. The `--exclude=tickettrade.tokens.css` is present; the `== 0` threshold is present; the `< 60` threshold is gone. |
| BLOCKER-4 | Toast forward dependency | FIXED | 01-01 must_haves: "exposes `window.TicketTrade` with `setTheme(mode)`, `getTheme()`, and `prefersReducedMotion()`; the full `toast.show` / `toast.dismiss` API lands in Plan 01-02 (toast module), but a 1-line `toast` stub is shipped in this plan". 01-01 action adds the stub: `console.log('TicketTrade.toast (stub, full impl in 01-02):', type, message)`. 01-01 `<done>` block: "logs 'TicketTrade.toast (stub, full impl in 01-02): info Theme loaded.' to the browser console (the stub is intentional and replaced by Plan 01-02)". 01-02 builds the real toast from scratch via `Object.assign(window.TicketTrade || {}, ...)` and does not depend on 01-01's stub. |

## NEW BLOCKER introduced

### NEW-BLOCKER-A: Verify command 5 cannot pass on a correct implementation

**Severity:** BLOCKER
**Dimension:** verify_command_format_sanity
**Plan:** 01-01
**Task:** 1
**Verify:** 5th `<automated>` block

**Description:** The new verify command uses the subshell pattern `(php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 &) ; PHP_PID=$!`. In bash, `$!` in the parent shell captures the PID of the most recently backgrounded process *in the parent shell*, not in a child subshell. After the subshell backgrounds `php -S` and exits, `$!` in the parent is the subshell's PID — but the subshell has already exited, so `$!` is actually empty. The subsequent `kill $PHP_PID 2>/dev/null` runs with empty `$PHP_PID` and exits with code 1 (kill usage error). With `set -e`, this terminates the script before the final `[ "$STATUS_MOCKUP" = "200" ] && ...` check can run.

**Evidence (live test):**
```
$ bash -c 'set -e; (php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 &) ; PHP_PID=$!; sleep 1; STATUS_MOCKUP=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:18001/mockups/board-mobile.html); STATUS_ROOT=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:18001/); STATUS_ADMIN=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:18001/admin/); kill $PHP_PID 2>/dev/null; wait $PHP_PID 2>/dev/null; [ "$STATUS_MOCKUP" = "200" ] && [ "$STATUS_ROOT" = "200" ] && [ "$STATUS_ADMIN" = "200" ] && echo PASS'
# (returns exit 2; no PASS output)
```

Without `set -e`, the same command returns exit 0 and the `[ ... = 200 ]` test correctly passes (the URLs do return 200). The `set -e` is what breaks the verify.

**Fix:** Remove the parens and let the background be in the current shell, OR use `pkill -f 'php -S 127.0.0.1:18001'` instead of `kill $PHP_PID`. Examples:

```bash
# Option A: direct background (PHP_PID captures the PHP process)
set -e; php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 & PHP_PID=$!; sleep 1; STATUS_MOCKUP=$(curl ...); STATUS_ROOT=$(curl ...); STATUS_ADMIN=$(curl ...); kill $PHP_PID 2>/dev/null; wait $PHP_PID 2>/dev/null; [ "$STATUS_MOCKUP" = "200" ] && [ "$STATUS_ROOT" = "200" ] && [ "$STATUS_ADMIN" = "200" ]

# Option B: use pkill with pattern (no PID needed)
set -e; (php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 &); sleep 1; STATUS_MOCKUP=$(curl ...); STATUS_ROOT=$(curl ...); STATUS_ADMIN=$(curl ...); pkill -f 'php -S 127.0.0.1:18001' 2>/dev/null; [ "$STATUS_MOCKUP" = "200" ] && [ "$STATUS_ROOT" = "200" ] && [ "$STATUS_ADMIN" = "200" ]
```

Option B is simpler and avoids the `$!` capture issue entirely.

## Coverage Summary

| Phase 1 Success Criterion | Plan(s) | Status | Notes |
|---|---|---|---|
| SC1: token system; no hex outside token set | 01-01 | PASS | Verify 1 now correctly excludes `tickettrade.tokens.css` and checks `== 0`. |
| SC2: theme persistence + system fallback | 01-01 | PASS | Theme script + localStorage key + FOUC-guard shipped. The `/settings` toggle UI is Phase 2 per D-07. The new must_haves correctly scopes to "the programmatic API + FOUC-guard inline script + data-surface fallback". |
| SC3: toast container with ARIA live region; 4s/8s; queue max 3; bottom-right desktop / top mobile | 01-02 | PASS | Implementation in Plan 01-02 Task 1. 4s/8s split now correctly matches EXPERIENCE.md (8000 for error/warning, 4000 for success/info). |
| SC4: bottom nav 64px, 5 items, hidden ≥768px, `aria-current="page"` on active | 01-02 | PASS | Plan 01-02 Task 1. |
| SC5: three mockups render with AA contrast | 01-01 + 01-02 | PARTIAL | No automated contrast verify; the contrast-ledger test (01-01 Task 2) only checks tokens resolve to non-empty hex, not the actual computed contrast ratio. Same as before. |
| SC6: skeleton shimmer on 12 surfaces | 01-02 | PARTIAL | Skeleton component is built + reference uses; the 9 non-Phase-1 surfaces are deferred to later phases by design. Plan 01-02 Task 3 acknowledges this. |
| SC7: empty/error states for every list surface | 01-02 | PARTIAL | Same pattern as SC6 — partials library is built, reference uses; the 4 non-Phase-1 list surfaces are deferred. |

## Decision Coverage

| Decision | Plan(s) | Status |
|---|---|---|
| D-01 (two-file CSS architecture) | 01-01 | Covered |
| D-02 (token names 1:1 to DESIGN.md) | 01-01 | Mostly covered — motion/elevation tokens still lack an explicit DESIGN.md source reference |
| D-03 (Bootstrap 5.3 from CDN + bootstrap-overrides) | 01-01 + 01-02 | Covered (version pin now consistent: 5.3.3 in both plans) |
| D-04 (localStorage theme key) | 01-01 | Covered |
| D-05 (FOUC-guard inline script) | 01-01 | Covered |
| D-06 (data-surface attribute) | 01-01 | Covered |
| D-07 (settings toggle UI) | Phase 2 | Explicitly deferred per CONTEXT.md; 01-01 must_haves now correctly defers |
| D-08 (three static mockups) | 01-01 (board-mobile) + 01-02 (all three) | Covered |
| D-09 (mockups link same CSS bundle) | 01-01 + 01-02 | Covered |
| D-10 (mockups use fixture data) | 01-01 + 01-02 | Covered |
| D-11 (single JS bundle, no build) | 01-01 + 01-02 | Covered |
| D-12 (eight components) | 01-01 (theme + prefersReducedMotion + toast stub) + 01-02 (six remaining) | Covered |
| D-13 (window.TicketTrade namespace) | 01-01 + 01-02 | Covered |

**Phase 1 implements 12/13 decisions; D-07 correctly deferred to Phase 2.**

## Requirement Coverage

| Requirement | Plan | Status |
|---|---|---|
| UX-01 (toast) | 01-01 (stub) + 01-02 (full) | Covered |
| UX-02 (skeleton) | 01-02 | Covered (component only) |
| UX-03 (empty/error states) | 01-02 | Covered (partials only) |
| UX-04 (token system) | 01-01 | Covered |
| UX-05 (typography) | 01-01 | Covered |
| UX-06 (theme persistence) | 01-01 | Covered (UI toggle is Phase 2) |
| UX-07 (WCAG AA) | 01-01 + 01-02 | Token-level verified; rendered contrast verified by manual SUMMARY.md record (no automated check) |
| UX-08 (keyboard nav) | 01-01 + 01-02 | Covered |
| UX-09 (bottom nav) | 01-02 | Covered |
| UX-10 (skip link) | 01-01 | Covered |

**All 10 Phase 1 requirements have at least one covering task.**

## Dependency Graph

| Plan | Wave | Depends on | Status |
|---|---|---|---|
| 01-01 | 1 | (none) | Tracer |
| 01-02 | 2 | 01-01 | Execute |

No cycles. No forward references. Wave numbering consistent with declared `depends_on`.

## Scope

| Plan | Tasks | Files | Est. tokens | Status |
|---|---|---|---|---|
| 01-01 | 3 | 15 (Task 1) | 95,000 (calibrated) | Improved from 17 to 15 files in Task 1; still high but acceptable for tracer |
| 01-02 | 3 | 10 (Task 2) | 75,000 (calibrated) | Unchanged from previous review; still 10 files in Task 2 |

Both plans are at 3 tasks (sweet spot). Plan 01-01 Task 1 has 15 file modifications (down from 17, slight improvement). Plan 01-02 Task 2 has 10 file modifications (unchanged).

## Warnings Tracking

The original report had 15 warnings. The three that were targeted for this revision (WARN 5, 8, 11) are now FIXED. The remaining 12 are listed below with their current status.

### FIXED in this revision (3)

**WARN 5 (htaccess admin) — FIXED.** The htaccess now reads:
```
RewriteEngine On
RewriteRule ^admin/(.*)$ admin/index.php [QSA,L]   # BEFORE catchall
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```
Evidence: 01-01 Task 1 action, "public/.htaccess" block.

**WARN 8 (Bootstrap version pin) — FIXED.** Both plans now pin `bootstrap@5.3.3`. 01-01 Task 1 action reads: "Bootstrap 5.3.3 from CDN (matches the version pinned in the 01-02 `_partials/head.html`; the version is identical between the two plans so the bundle is the same in both the partial and the 01-01 mockup)". 01-02 Task 2 action uses the same version.

**WARN 11 (toast 4s/8s) — FIXED.** The 01-02 action now reads: "The auto-dismiss timing is `setTimeout(id, type === 'error' || type === 'warning' ? 8000 : 4000)` (4s for success/info per REQUIREMENTS.md UX-01; 8s for error/warning per EXPERIENCE.md Component Patterns — error/warning toasts have a manual dismiss button so the longer window is appropriate)". The split matches the documented behavior.

### REMAINING warnings (12)

**WARN 6 (timezone AD number) — REMAINING.** The threat model V14 row still says: "config/bootstrap.php sets the timezone to Asia/Colombo per AD-11". The action text correctly says "AD-13" in the no-session context, but the threat model row is unchanged. Note: per ARCHITECTURE-SPINE.md, AD-11 is "Cron as first-class owner" and AD-13 is "Auth, session, CSRF, rate-limit shape". The timezone is mentioned in AD-11 ("All wall-clock comparisons run in date_default_timezone_set('Asia/Colombo')") and AD-13 ("session config set at bootstrap"). The V14 citation is debatable.

**WARN 7 (data-component vs data-skeleton) — REMAINING.** 01-02 Task 1 verify 2 still uses the pattern `data-component="(toast|bottom-nav|skeleton|list-view-toggle|modal-scrim-guard|star-rating)"` with a `>= 9` threshold. But the action spec for skeleton says: "skeleton — opt-in via `data-skeleton` (an attribute or a class)". A correct implementation using `data-skeleton` will have 0 `data-component="skeleton"` matches, so the count of `data-component` attributes will be 6 (toast + bottom-nav per mockup × 3 mockups), not 9. The verify will fail on a correct implementation.

**WARN 9 (toast container in markup) — RESOLVED.** The 01-01 `<done>` block now says: "logs 'TicketTrade.toast (stub, full impl in 01-02): info Theme loaded.' to the browser console (the stub is intentional and replaced by Plan 01-02)". The stub logs to console and does not require a `[data-component="toast"]` container. Resolved by the BLOCKER-4 fix.

**WARN 10 (ROADMAP SC6/SC7 achievability) — DOCUMENTED.** Plan 01-02 Task 3 acknowledges: "the three promoted mockups cover the canonical references; the remaining 9 surfaces reuse the same skeleton partial in their real Views in later phases". Reasonable phased delivery; not blocking.

**WARN 12 (motion/elevation tokens ad-hoc) — REMAINING.** The 01-01 action defines 4 motion tokens (`--motion-hover`, `--motion-skeleton`, `--motion-legend-glow`, `--motion-modal`) and 2 elevation tokens (`--elevation-1`, `--elevation-4`) without a corresponding DESIGN.md section. Per D-02, token names should map 1:1 to DESIGN.md roles.

**WARN 13 (15+ files in 01-01 Task 1) — REMAINING.** Plan 01-01 Task 1 has 15 file modifications. Down from 17 in the previous review, but still in the "15+ files" warning zone.

**WARN 14 (10 files in 01-02 Task 2) — REMAINING.** Plan 01-02 Task 2 has 10 file modifications (7 partials + 3 mockups).

**WARN 15 (composer install) — FIXED.** The 01-01 action now explicitly says: "the executor must run `composer install --no-interaction --no-progress` after creating `composer.json` and BEFORE any `vendor/bin/phpunit` verify command in Task 2 or Task 3".

**WARN 16 (textContent for toast XSS) — REMAINING.** The threat model says: "The message is rendered as text content (not innerHTML), so HTML injection is not possible." But the action spec for the toast module says: "appends a `<div class="toast toast-{type}" role="{status|alert}" data-toast-id="{id}">` containing a `<span class="toast__message">{message}</span>`" — it does not say `textContent` vs `innerHTML`. A non-domain-fluent executor may default to `innerHTML` and reintroduce the XSS vector the threat model claims is mitigated.

**WARN 17 (sessionStorage key) — REMAINING.** The 01-02 action uses `sessionStorage.tickettrade.listView` but CONTEXT.md D-12 only says "persists per session via sessionStorage" without specifying the key. A future phase may use a different key.

**WARN 18 (/settings in must_haves) — FIXED.** The 01-01 must_haves no longer references `/settings`. It now correctly says: "The full three-state toggle UI is Phase 2 (D-07); the contract this plan ships is the programmatic API + FOUC-guard inline script + data-surface fallback".

**WARN 19 (ResponseHeaders stub) — REMAINING.** The `Support\ResponseHeaders` stub in 01-01 is still a no-op. No TODO comment in the spec. Phase 9 must replace it. Quality issue, not blocking.

### NEW minor issue introduced

**MINOR-A (verify 6 fails_when text inconsistency) — NEW.** The `<fails_when>` for verify 6 in 01-01 Task 1 says: "Phase 1 ships the minimum viable router that throws on empty route map; Phase 2 extends it". But the action spec (revised) says the router "renders a Phase 1 stub landing page (HTTP 200)" via `src/Support/View/landing.php` — no throwing. The actual grep test (`class\s+Router` in `src/Support/Router.php`) works regardless; the fails_when text is misleading documentation. Recommend updating the fails_when text to match the action spec.

## Issues

### Blockers (must fix before execution)

**1. [verify_command_format_sanity] Verify command 5 in 01-01 Task 1 cannot pass on a correct implementation due to $! empty after subshell exit**
- Plan: 01-01
- Task: Task 1
- The subshell pattern `(php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 &)` does not propagate `$!` to the parent shell. `PHP_PID` is empty. `kill $PHP_PID 2>/dev/null` exits with code 1, and `set -e` terminates the script before the final `[ ... = 200 ]` check can run. Live test (bash 5.2.37) confirms the verify exits 2 (not 0) on a correct implementation. The `; true` masking bug from the previous review is gone, but a different exit-code-masking bug was introduced.
- Evidence: see "NEW-BLOCKER-A" section above for the live test output.
- Fix: Either (a) remove the parens around the `php -S ... &` so `PHP_PID=$!` captures the PHP process, or (b) use `pkill -f 'php -S 127.0.0.1:18001' 2>/dev/null` instead of `kill $PHP_PID`.

### Warnings (should fix, execution can proceed)

**2. [task_completeness] Verify 6 fails_when text contradicts action spec**
- Plan: 01-01
- Task: Task 1
- The `<fails_when>` for verify 6 says "throws on empty route map" but the action spec now says "renders a Phase 1 stub landing page (HTTP 200)" via `src/Support/View/landing.php`. The actual grep test (does `class Router` exist?) works regardless; the fails_when text is misleading.
- Fix: Update the fails_when text to match the action: "Support\Router class is not defined (Phase 1 ships the minimum viable router that renders a stub landing page when the route map is empty; Phase 2 extends it with real dispatch)".

**3. [task_completeness] WARN 7 (data-component vs data-skeleton)**
- Plan: 01-02
- Task: Task 1
- Verify command 2 uses `data-component="(toast|bottom-nav|skeleton|list-view-toggle|modal-scrim-guard|star-rating)"` with `>= 9` threshold, but the action spec for skeleton says "opt-in via `data-skeleton` (an attribute or a class)". A correct implementation will have 6 (not 9) `data-component` matches and fail the verify.
- Fix: Change the verify regex to also count `data-skeleton` attribute, or change the action to use `data-component="skeleton"`.

**4-15.** [12 remaining warnings from original review — see "Warnings Tracking" section above]

### Structured Issues

```yaml
issues:
  - plan: "01-01"
    task: 1
    dimension: "verify_command_format_sanity"
    severity: "blocker"
    description: "Verify command 5 cannot pass on a correct implementation. The subshell pattern (php -S ... &) leaves $! empty in the parent shell, so kill $PHP_PID fails, and set -e terminates the script before the [ ... = 200 ] check runs. Live test confirms exit 2 on a correct implementation."
    fix_hint: "Remove the parens around (php -S ... &) so PHP_PID=$! captures the PHP process, OR use pkill -f 'php -S 127.0.0.1:18001' instead of kill $PHP_PID."

  - plan: "01-01"
    task: 1
    dimension: "task_completeness"
    severity: "warning"
    description: "Verify 6 fails_when text says 'throws on empty route map' but action spec says 'renders a Phase 1 stub landing page (HTTP 200) via src/Support/View/landing.php'. The actual grep test (does 'class Router' exist?) works either way; the fails_when text is misleading."
    fix_hint: "Update the fails_when text to match the action: 'Support\Router class is not defined (Phase 1 ships the minimum viable router that renders a stub landing page when the route map is empty; Phase 2 extends it with real dispatch)'."

  - plan: "01-02"
    task: 1
    dimension: "task_completeness"
    severity: "warning"
    description: "Verify 2 regex 'data-component="(toast|bottom-nav|skeleton|list-view-toggle|modal-scrim-guard|star-rating)"' with >= 9 threshold assumes skeleton uses data-component, but action spec says skeleton uses data-skeleton. A correct implementation will fail the verify (6 matches, not 9)."
    fix_hint: "Change the verify to also count data-skeleton attribute, or change the action to use data-component="skeleton"."

  # Plus 12 remaining warnings from the original review (WARN 6, 10, 12, 13, 14, 16, 17, 19)
```

## Summary

| Severity | Original | This Pass | Delta |
|----------|----------|-----------|-------|
| Blocker | 4 | 1 (NEW) | -3 fixed, 0 still-failing, +1 new |
| Warning | 15 | 12 + 2 new minor | -3 fixed (5, 8, 11, 15, 18), +0 new (the new minor inconsistency is 1 of the 2) |
| Info | 0 | 0 | unchanged |

## Recommendation

The planner addressed three of the four original BLOCKERS cleanly and made significant progress on the warnings. However, the BLOCKER-1 fix introduced a new bug (verify command 5 cannot pass due to `$!` being empty after subshell exit). This is a regression of BLOCKER-1 — the original bug was about exit code masking, and the new bug is also about exit code (set -e triggering on a different command).

**Required next pass:** fix the verify command 5 subshell pattern. The simplest fix is to use `pkill -f 'php -S 127.0.0.1:18001' 2>/dev/null` instead of `kill $PHP_PID; wait $PHP_PID`. This avoids the PID-capture issue entirely.

Once verify 5 is fixed, the plan should pass verification per the gates taxonomy (warnings can proceed without being blocking).

---

*Review complete. Plans checked: 2. Issues: 1 blocker (NEW), 14 warnings (12 remaining from original + 2 new minor).*
