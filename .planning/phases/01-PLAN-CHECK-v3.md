# Plan Check v3 — Phase 1: UX Foundation & Design System

**Reviewer:** gsd-plan-checker (third pass, focused verification)
**Plans reviewed:** 01-01, 01-02
**Date:** 2026-09-XX (post-revision re-verification)
**Source phase directory:** `tickettrade/.planning/phases/01-ux-foundation-design-system/`
**Previous reports:** `01-PLAN-CHECK.md` (v1, 4 BLOCKERS), `01-PLAN-CHECK-v2.md` (v2, 0 original BLOCKERS + 1 NEW BLOCKER-A)

## Verdict

**`## VERIFICATION PASSED`** — the NEW BLOCKER-A from v2 is fixed. Both minor issues from v2 are also fixed. No new BLOCKERS are introduced.

The planner addressed the regression in the BLOCKER-1 fix by removing the subshell pattern around `php -S ... &`, adding a `trap "kill $PHP_PID 2>/dev/null || true" EXIT` cleanup safety net, and keeping the final `[ ... = 200 ] && [ ... = 200 ] && [ ... = 200 ]` exit check. Live test confirms the new command returns exit 0 on a correct implementation and exit 1 on a broken implementation; the old v2 pattern (subshell + empty `$!` + `set -e`) returns exit 2 on a correct implementation, reproducing the original bug.

## Targeted Issue Verification

### BLOCKER-A (NEW from v2): Verify command 5 subshell `$!` propagation bug — FIXED

**Severity at flag:** BLOCKER
**Dimension:** verify_command_format_sanity
**Plan:** 01-01
**Task:** 1
**Verify:** 5th `<automated>` block

**Static checks (all PASS):**

| Criterion | Status | Evidence |
|---|---|---|
| NOT wrap `php -S ... &` in parens | PASS | Block 5 starts with `set -e; php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 & PHP_PID=$!;` — no `(php -S` and no `) ; PHP_PID` pattern. |
| Use `trap "kill $PHP_PID 2>/dev/null \|\| true" EXIT` | PASS | The literal trap string is present in the block. |
| Use `kill $PHP_PID 2>/dev/null; wait $PHP_PID 2>/dev/null \|\| true` for the foreground kill | PASS | Both `kill $PHP_PID 2>/dev/null` and `wait $PHP_PID 2>/dev/null \|\| true` are present. |
| End with `[ "$STATUS_MOCKUP" = "200" ] && [ "$STATUS_ROOT" = "200" ] && [ "$STATUS_ADMIN" = "200" ]` | PASS | The literal final check is the last command in the block. |

**Live test (regression + fix proof):**

Executed in a sandboxed `tmpdir/public` with a minimal `router.php` that dispatches `/` and `/admin/` to their respective `index.php` stubs (both returning 200) and lets `php -S` serve `/mockups/board-mobile.html` as a static file (returning 200). Three scenarios:

| Pattern | Server impl | Expected | Returncode | Result |
|---|---|---|---|---|
| OLD v2 pattern (`(php ... &)` subshell, no trap) | Correct (3x 200) | FAIL (bug) | **2** | Confirms the v2 bug — script terminated by `set -e` after `kill` with empty `PHP_PID`; the `[ ... = 200 ]` test never ran. |
| NEW v3 pattern (no parens, `trap ... EXIT`) | Correct (3x 200) | PASS | **0** | Fix works — the verify correctly returns 0. |
| NEW v3 pattern (no parens, `trap ... EXIT`) | Broken (3x 404) | FAIL | **1** | Verify correctly fails on a broken implementation. |

The v2 BLOCKER-A fix is **complete and correct**.

### WARN 2 (from v2): Verify 6 fails_when text inconsistency — FIXED

**Dimension:** task_completeness
**Plan:** 01-01
**Task:** 1
**Verify:** 6th `<automated>` block

The `<fails_when>` text for the `class\s+Router` grep test now reads:

> `Support\Router` class is not defined (Phase 1 ships the minimum viable router that renders a stub landing page when the route map is empty via `src/Support/View/landing.php`; Phase 2 extends it with real dispatch).

| Sub-criterion | Status | Evidence |
|---|---|---|
| Says "renders a stub landing page" | PASS | Present in the new fails_when text. |
| Does NOT say "throws on empty route map" | PASS | Removed; the previous stale text is gone. |

The fails_when text now matches the action spec's description of the router behavior (HTTP 200 stub landing page).

### WARN 3 (from v2): 01-02 verify 2 data-skeleton vs data-component — FIXED

**Dimension:** task_completeness
**Plan:** 01-02
**Task:** 1
**Verify:** 2nd `<automated>` block

The new verify regex:

```bash
grep -cE 'data-component="(toast|bottom-nav|list-view-toggle|modal-scrim-guard|star-rating)"|data-skeleton' public/mockups/board-mobile.html public/mockups/my-tickets.html public/mockups/admin-dashboard.html | awk -F: '{ sum += $2 } END { if (sum >= 9) exit 0; else { print "FAIL: data-component + data-skeleton total = " sum " (need >= 9 across the 3 mockups)"; exit 1 } }'
```

| Sub-criterion | Status | Evidence |
|---|---|---|
| Regex includes `data-skeleton` | PASS | The alternation `'data-component="..."|data-skeleton'` matches `data-skeleton` attributes. |
| Regex still includes `data-component` | PASS | The five named components are still enumerated. |
| Threshold logic still gates at `>= 9` | PASS | `awk` `sum >= 9` check is present. |

A correct implementation that uses `data-skeleton` (per the action spec) for the skeleton component, plus the 5 component attributes per mockup (toast + bottom-nav = 2 from partials × 3 mockups = 6, plus `data-skeleton` on 3 mockups = 9 total), now passes the verify. The action spec also explicitly states "the other components land in later phases and are referenced from the partials but not in Phase 1 mockups", so the 9 bar is correctly scoped.

## New BLOCKER Scan

Re-ran the BLOCKER anti-pattern scan across every `<automated>` block in both plans (per Dimension "Verify Command Format Sanity"):

| Anti-pattern | Plans scanned | Hits |
|---|---|---|
| `pnpm ls ... | grep -E '^package'` tree-anchor | 01-01, 01-02 | 0 |
| `VAR=$(cmd 2>/dev/null \|\| echo "0"); [ "$VAR" = ... ]` swallowed error feeding comparison | 01-01, 01-02 | 0 |
| `\|\| true` as right-hand side of assignment feeding a `[ ... = ... ]` comparison | 01-01, 01-02 | 0 |

All 11 verify commands in 01-01 and all 11 verify commands in 01-02 pass the BLOCKER scan. No new BLOCKERS are introduced.

Note: the `01-01 Task 3 verify #10` and `01-02 Task 3 verify #10` use a different pattern (`timeout ... & sleep 1 && curl ... ; pkill -f 'php -S 127.0.0.1:NNNN' 2>/dev/null; true`) that does not rely on PID capture and was not flagged in v2. The `pkill -f` pattern is immune to the `$!` empty-subshell issue because it matches by command name, not PID.

## Status of Remaining (Untargeted) Items from v2

The v2 report listed 12 remaining warnings (WARN 6, 10, 12, 13, 14, 16, 17, 19, plus several others). Per the v3 task brief, those warnings were NOT in scope for this re-verification pass. None of them are BLOCKERS per the gates taxonomy, and the brief asked for confirmation that no NEW BLOCKERS are introduced. Confirmed: no new BLOCKERS.

## Decision

The previous pass flagged 1 BLOCKER (NEW BLOCKER-A) and 2 minor warnings. All three are now fixed. No new BLOCKERS are introduced.

**Status:** `## VERIFICATION PASSED`

---

*Review complete. Plans checked: 2. New BLOCKER-A: FIXED (live-tested). Minor issues WARN 2 and WARN 3: FIXED. No new BLOCKERS introduced.*
