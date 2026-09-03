# Plan Check — Phase 1: UX Foundation & Design System

**Reviewer:** gsd-plan-checker (independent adversarial review)
**Plans reviewed:** 01-01, 01-02
**Date:** 2026-08-30
**Source phase directory:** `tickettrade/.planning/phases/01-ux-foundation-design-system/`

## Verdict

**`## ISSUES FOUND`** — 4 blockers, 15 warnings.

The two plans cover the Phase 1 surface area with the right structural choices (Tracer → Execute pattern, Wave 1 → Wave 2 sequencing, dependency declared correctly, decision coverage 12/13 with D-07 explicitly deferred to Phase 2). However, three of the seven automated verify commands in Plan 01-01 are constructed in a way that either cannot fail, or will fail on a correct implementation, or tests a different outcome than the action spec produces. Plan 01-02 inherits one of the same verify-command bugs. The most consequential blocker is that Plan 01-01's `Support\Router::dispatch` is specified to throw on empty route maps, while the same plan's verify command expects HTTP 200 for `/` and `/admin/` — these are mutually exclusive.

A further blocker is the cross-plan forward dependency: Plan 01-01's `must_haves` and `Task 1 <done>` block require the toast component (`TicketTrade.toast.show`), but the toast module ships in Plan 01-02. A non-domain-fluent executor will guess at how to reconcile the two plans (drop the call, stub the function, or move it forward).

## Coverage Summary

| Phase 1 Success Criterion | Plan(s) | Status | Notes |
|---|---|---|---|
| SC1: token system; no hex outside token set | 01-01 | Partial | Verify command 1 is misnamed/mis-scoped; threshold of 60 is too tight (will fail on the ~80–110 hex lines a complete token set produces) |
| SC2: theme persistence + system fallback | 01-01 | Partial | Theme script + localStorage key + FOUC-guard shipped. The `/settings` toggle UI is Phase 2 per D-07. `must_haves` claim references a `/settings` mockup that does not exist in Plan 01-01 |
| SC3: toast container with ARIA live region; 4s/8s; queue max 3; bottom-right desktop / top mobile | 01-02 | Mostly covered | Implementation in Plan 01-02 Task 1. 4s/8s ambiguity not resolved at the plan level (CONTEXT.md says '4s/8s'; EXPERIENCE.md says 8s for error/warning; REQUIREMENTS.md says 4s for all; action spec uses 4000ms for all) |
| SC4: bottom nav 64px, 5 items, hidden ≥768px, `aria-current="page"` on active | 01-02 | Covered | Plan 01-02 Task 1 + partials library |
| SC5: three mockups render with AA contrast | 01-01 + 01-02 | Partial (soft) | No automated contrast verify; the contrast-ledger test (01-01 Task 2) only checks tokens resolve to non-empty hex, not the actual computed contrast ratio |
| SC6: skeleton shimmer on 12 surfaces | 01-02 | Component-level only | Skeleton component is built + 3 reference uses; the 9 non-Phase-1 surfaces are deferred to later phases by design. ROADMAP.md wording implies full coverage |
| SC7: empty/error states for every list surface | 01-02 | Component-level only | Same pattern as SC6 — partials library is built, 3 reference uses; the 4 non-Phase-1 list surfaces are deferred |

## Decision Coverage

| Decision | Plan(s) | Status |
|---|---|---|
| D-01 (two-file CSS architecture) | 01-01 | Covered |
| D-02 (token names 1:1 to DESIGN.md) | 01-01 | Mostly covered — motion/elevation tokens introduced without a corresponding DESIGN.md section |
| D-03 (Bootstrap 5.3 from CDN + bootstrap-overrides) | 01-01 | Covered (with version pin inconsistency between 01-01 and 01-02) |
| D-04 (localStorage theme key) | 01-01 | Covered |
| D-05 (FOUC-guard inline script) | 01-01 | Covered |
| D-06 (data-surface attribute) | 01-01 | Covered |
| D-07 (settings toggle UI) | Phase 2 | Explicitly deferred per CONTEXT.md; not a Phase 1 deliverable |
| D-08 (three static mockups) | 01-01 (board-mobile) + 01-02 (all three) | Covered |
| D-09 (mockups link same CSS bundle) | 01-01 + 01-02 | Covered |
| D-10 (mockups use fixture data) | 01-01 + 01-02 | Covered |
| D-11 (single JS bundle, no build) | 01-01 + 01-02 | Covered |
| D-12 (eight components) | 01-01 (theme + prefersReducedMotion) + 01-02 (six remaining) | Covered |
| D-13 (window.TicketTrade namespace) | 01-01 + 01-02 | Covered (with caveat that 01-01's must_haves overstates the 01-01 surface) |

**Phase 1 implements 12/13 decisions; D-07 correctly deferred to Phase 2.**

## Requirement Coverage

| Requirement | Plan | Status |
|---|---|---|
| UX-01 (toast) | 01-02 (full) | must_haves in 01-01 overstates (see ISSUE-04) |
| UX-02 (skeleton) | 01-02 | Covered (component only; 9 of 12 surfaces deferred) |
| UX-03 (empty/error states) | 01-02 | Covered (partials only; 4 of 7 surfaces deferred) |
| UX-04 (token system) | 01-01 | Covered |
| UX-05 (typography) | 01-01 | Covered |
| UX-06 (theme persistence) | 01-01 | Covered (UI toggle is Phase 2) |
| UX-07 (WCAG AA) | 01-01 + 01-02 | Token-level verified; rendered contrast verified by manual SUMMARY.md record (no automated check) |
| UX-08 (keyboard nav) | 01-01 + 01-02 | Covered (skip link in 01-01; focus management in 01-02) |
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
| 01-01 | 3 | 19 | 95,000 (calibrated) | Borderline — single task with 17 file modifications |
| 01-02 | 3 | 17 | 75,000 (calibrated) | Borderline — single task with 10 file modifications |

Both plans are at 3 tasks (sweet spot). Both touch 17+ files; the threshold for a warning is 10+ files per task. **Both Plan 01-01 Task 1 and Plan 01-02 Task 2 are single tasks that touch 10+ files** — flagged as quality-degradation risk in the plan-checker spec.

## Issues

### Blockers (must fix before execution)

**1. [verify_command_format_sanity] Verify command 5 in 01-01 Task 1 always exits 0**
- Plan: 01-01
- Task: Task 1
- The verify ends with `; pkill -f 'php -S 127.0.0.1:18001' 2>/dev/null; true`. The trailing `true` masks any non-zero exit from the curl chain. A return of 500 from any URL is printed but does not fail the verify. Plan 01-02 Task 3 verify command 9 has the same `; true` bug.
- Evidence: `(timeout 3 php -S 127.0.0.1:18001 -t public public/router.php >/tmp/gsd-srv.log 2>&1 &) && sleep 1 && curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18001/mockups/board-mobile.html && echo '' && curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18001/admin/ && echo '' && curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18001/ ; pkill -f 'php -S 127.0.0.1:18001' 2>/dev/null; true`
- Fix: Drop the trailing `; true` and the preceding `;` (use `&&` instead) so the curl exit code propagates, or wrap in `set -o pipefail` and check curl exit codes explicitly.

**2. [dependency_correctness / key_links_planned] Verify command 5 in 01-01 Task 1 expects HTTP 200 for `/` and `/admin/`, but the action spec says `Support\Router::dispatch` throws on empty route map**
- Plan: 01-01
- Task: Task 1
- A correct implementation of the action spec (empty maps → throw RuntimeException) will produce HTTP 500 from the dev server for `/` and `/admin/`. The verify command expects 200, so a correct implementation fails the verify. The plan's own Task 1 `<action>` says: 'the test target is that empty maps produce the runtime exception, not a 200' — so the verify is checking the wrong outcome.
- Evidence: Action spec: 'throws a RuntimeException with the literal message `Route map is empty — no routes registered.` if the array is empty'. Verify: `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18001/`.
- Fix: Either (a) add a Phase-1 stub in `public/index.php` and `public/admin/index.php` that catches the exception and renders a simple 200 HTML landing page ('Phase 1 — UX Foundation complete'); or (b) change the verify to expect 500 (or check that the exception is logged).

**3. [verify_command_format_sanity] Verify command 1 in 01-01 Task 1 has a threshold of 60 that will fail on a correct implementation**
- Plan: 01-01
- Task: Task 1
- The action spec requires implementing ~94 unique design tokens across `:root[data-theme="light"]` and `:root[data-theme="dark"]` blocks (per DESIGN.md frontmatter). A complete `tickettrade.tokens.css` will contain ~80–110 lines with a hex literal, all of which are within the file the verify is supposed to be guarding. The verify's `awk` exits 0 only if the count is < 60, so a correct implementation will fail.
- Evidence: Command: `grep -RIn --include='*.css' --include='*.php' --include='*.js' --include='*.html' -E '#[0-9A-Fa-f]{{3,8}}\b' public/assets/css/tickettrade.tokens.css public/ config/ | wc -l | awk '{{ if ($1 < 60) exit 0; else {{ print "FAIL: " $1 " hex matches outside tokens.css"; exit 1 }} }}'`
- Fix: Either (a) raise the threshold to ~250 to allow for the full token set, or (b) rewrite the grep to exclude `tickettrade.tokens.css` and check that other files have zero hex literals: `grep -RIn --include='*.css' --include='*.js' --include='*.php' --include='*.html' -E '#[0-9A-Fa-f]{{3,8}}\b' public/ --exclude=tickettrade.tokens.css | wc -l | awk '{{ if ($1 == 0) exit 0; else exit 1 }}'`. The current command's `fails_when` text also does not match the command's actual behavior.

**4. [key_links_planned] Plan 01-01 must_haves and <done> claim `TicketTrade.toast.show` is exposed, but the toast module ships in 01-02**
- Plan: 01-01 + 01-02
- Task: 01-01 Task 1
- The Plan 01-01 `must_haves` says: 'The `tickettrade.js` bundle exposes `window.TicketTrade` with `toast.show`, `toast.dismiss`, `setTheme`, `getTheme`, and `prefersReducedMotion`'. The 01-01 Task 1 action says: 'Required components in this plan (the rest are deferred to Plan 01-02): `prefersReducedMotion`, `themeController`'. The 01-01 Task 1 action also tells the executor to put `TicketTrade.toast.show('Theme loaded.', 'info')` at the bottom of `board-mobile.html`. A non-domain-fluent executor will either silently drop the call (toast unproven in 01-01) or stub the function (creating dead code that 01-02 must replace). The 01-01 must_haves also claims the `/settings` toggle works in the mockup, but `/settings` is Phase 2.
- Evidence: 01-01 must_haves: 'The `tickettrade.js` bundle exposes `window.TicketTrade` with `toast.show`, `toast.dismiss`, `setTheme`, `getTheme`, and `prefersReducedMotion`'. 01-01 action: 'Required components in this plan (the rest are deferred to Plan 01-02): `prefersReducedMotion`, `themeController`'. 01-01 <done>: '`board-mobile.html` renders without console errors and announces "Theme loaded." via the toast'.
- Fix: Either (a) move the toast placeholder to Plan 01-02 (where the container is created); or (b) add a minimal stub in 01-01 (`toast: {{ show(m,t){{ console.log('toast:',t,m); }} }}`) and document the contract for 01-02 to replace; or (c) rewrite the must_haves to match the actual 01-01 surface (theme + prefersReducedMotion only).

### Warnings (should fix, execution can proceed)

**5. [scope_sanity] `.htaccess` does not handle `/admin/*`**
- Plan: 01-01
- Task: Task 1
- The action creates `public/.htaccess` with only `RewriteRule ^(.*)$ index.php [QSA,L]`. On Apache, `/admin/*` will not reach `public/admin/index.php` because the rule rewrites to `index.php` (singular). The dev server's `public/router.php` handles admin correctly, but production Apache deployment is broken. ARCHITECTURE-SPINE.md AD-3 + AD-17 require admin routes to dispatch via `public/admin/index.php`.
- Evidence: Action: '`public/.htaccess`: `RewriteEngine On`, `RewriteCond %{{REQUEST_FILENAME}} !-f`, `RewriteCond %{{REQUEST_FILENAME}} !-d`, `RewriteRule ^(.*)$ index.php [QSA,L]`'.
- Fix: Add a second `RewriteRule ^admin/(.*)$ admin/index.php [QSA,L]` BEFORE the catchall, or add a `RewriteCond %{{REQUEST_URI}} !^/admin/` to the catchall.

**6. [task_completeness] Threat model cites wrong AD number for timezone config**
- Plan: 01-01
- Task: Threat model (V14 row)
- The plan's V14 row says `config/bootstrap.php sets the timezone to Asia/Colombo per AD-11`, but timezone is part of AD-13 (Auth, session, CSRF, rate-limit shape), not AD-11 (Cron as first-class owner).
- Evidence: Threat model table V14 row. ARCHITECTURE-SPINE.md AD-11 = cron; AD-13 = bootstrap timezone.
- Fix: Replace `AD-11` with `AD-13` in the V14 row.

**7. [task_completeness] Verify commands count `data-component="skeleton"`, but action uses `data-skeleton`**
- Plan: 01-01 Task 3 + 01-02 Task 1
- The verify regex `data-component="(toast|bottom-nav|skeleton|list-view-toggle|modal-scrim-guard|star-rating)"` does not match the action spec, which says 'skeleton — opt-in via `data-skeleton`' (a separate attribute, not a `data-component` value).
- Evidence: 01-02 action: 'skeleton — shimmer 1s, surface-container-high fill, opt-in via `data-skeleton`'. Verify: `grep -cE 'data-component="(toast|bottom-nav|skeleton|...)"'`.
- Fix: Either change the verify to `(data-component="(toast|bottom-nav)"|data-skeleton)`, or change the action to use `data-component="skeleton"`.

**8. [task_completeness] Bootstrap version pin inconsistent between 01-01 and 01-02**
- Plan: 01-01 + 01-02
- 01-01 references `Bootstrap 5.3 CDN` (no version). 01-02 head.html partial pins `bootstrap@5.3.3`. A future minor-version bump will affect the two plans independently.
- Evidence: 01-01: 'Bootstrap 5.3 CDN <link>'. 01-02 head.html: `<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">`.
- Fix: Pin to 5.3.3 in both plans, or leave both unpinned. Document in CONTEXT.md.

**9. [task_completeness] `board-mobile.html` calls `TicketTrade.toast.show` but no `[data-component="toast"]` container is in the markup**
- Plan: 01-01
- Task: Task 1
- The action tells the executor to call `TicketTrade.toast.show('Theme loaded.', 'info')` after `window.load`. But the toast container `<div data-component="toast" role="status" ...>` lands in 01-02 Task 2 partials. Without the container, `show()` will fail to find the parent and either throw or no-op.
- Evidence: 01-01 action (board-mobile.html): 'A <script> at the bottom of the body calls TicketTrade.toast.show("Theme loaded.", "info") after window.addEventListener("load") to prove the toast container is mounted.' 01-02 partial toast-container.html: `<div data-component="toast" role="status" aria-live="polite" aria-atomic="true"></div>`.
- Fix: Either add an inline `<div data-component="toast" ...>` in the 01-01 board-mobile.html action, or move the `show()` call to 01-02.

**10. [verification_derivation] ROADMAP success criteria SC6/SC7 are not literally achievable in Phase 1**
- Plan: 01-01 + 01-02
- Task: Both
- SC6 (skeleton on 12 surfaces) and SC7 (empty/error on 7 surfaces) reference surfaces that don't exist in Phase 1 (Sales, Profile, My Listings, Purchase History, Leaderboards, etc.). The plan delivers the component + 3 reference uses and acknowledges that the other surfaces will reuse the partials in later phases. This is a reasonable phased delivery, but the ROADMAP wording is ambiguous and the plan doesn't acknowledge the partial achievement.
- Evidence: 01-02 Task 3 action: 'Verify the 3 mockups collectively cover the 12-skeleton surface list from UX-02: ... (the three promoted mockups cover the canonical references; the remaining 9 surfaces reuse the same skeleton partial in their real Views in later phases).'
- Fix: Either amend the ROADMAP success criteria to say 'component shipped, 3 reference uses demonstrated; full surface coverage lands in Phases 2–8', or extend Phase 1 to render the missing surfaces (significant scope expansion).

**11. [task_completeness] Toast auto-dismiss timing (4s vs 8s) is inconsistent across documents**
- Plan: 01-01 + 01-02
- CONTEXT.md D-12: '4s/8s dismiss'. EXPERIENCE.md State Patterns: 'Auto-dismiss 8s instead of 4s' for error/warning. REQUIREMENTS.md UX-01: 'auto-dismiss 4s' (no override). Plan 01-02 action: 'The auto-dismiss is a `setTimeout(id, 4000)`' (4s for all). No plan-level verify checks the 8s behavior.
- Evidence: Multiple — see CONTEXT.md, EXPERIENCE.md, REQUIREMENTS.md, Plan 01-02.
- Fix: Resolve in CONTEXT.md or DESIGN.md. If 8s for error/warning, update the 01-02 action to use 8000ms when type is 'error' or 'warning' and add a verify.

**12. [task_completeness] Motion and elevation tokens are introduced ad-hoc without a DESIGN.md source**
- Plan: 01-01
- Task: Task 1
- Plan 01-01 defines 4 motion tokens (`--motion-hover`, `--motion-skeleton`, `--motion-legend-glow`, `--motion-modal`) and 2 elevation tokens (`--elevation-1`, `--elevation-4`). DESIGN.md does not declare a `motion` or `elevation` token group. Per D-02 ('token names map 1:1 to roles in DESIGN.md'), the source is unclear. Motion is partially specified inline (e.g., `legend-glow 2.4s ease-in-out infinite` in `components.rank-badge-s.animation`).
- Evidence: 01-01 action: motion + elevation tokens listed. DESIGN.md frontmatter has no `motion` or `elevation` keys.
- Fix: Add `motion` and `elevation` sections to DESIGN.md, or cite the inline source for each token in the action spec.

**13. [scope_sanity] Plan 01-01 Task 1 is a single task that creates 17+ files**
- Plan: 01-01
- Task: Task 1
- The action touches composer.json, 4 PHP files, 2 CSS source files + bundle, a JS bundle, and a mockup HTML. Single-task > 15 files is a quality-degradation risk. The plan is structured as a 'tracer' to avoid splitting, but a non-domain-fluent executor may struggle to keep 19 files consistent in one pass.
- Evidence: Plan 01-01 Task 1 `<files>` block: 17 explicit files (config, public, assets, mockups).
- Fix: Consider splitting into 1A (scaffolding: composer + 6 PHP files + .htaccess + data/) and 1B (token system + JS bundle + mockup). Alternatively, accept the tracer rationale and document the risk in <done>.

**14. [scope_sanity] Plan 01-02 Task 2 is a single task that creates 10 files**
- Plan: 01-02
- Task: Task 2
- 7 partials + 2 new mockups + 1 mockup refactor = 10 files. The action is dense.
- Evidence: Plan 01-02 Task 2 `<files>` block.
- Fix: Same as ISSUE-13: consider splitting, or accept the risk.

**15. [task_completeness] Plan does not include `composer install` step**
- Plan: 01-01
- Task: Task 1 / Task 2
- Plan 01-01 creates `composer.json` (which declares `phpunit` and `phpcs` as dev deps). The verify commands in Task 2 and Task 3 invoke `vendor/bin/phpunit`, which requires `composer install` to have run. The plan does not include this step. A non-domain-fluent executor may run the tests before install and get 'command not found'.
- Evidence: 01-01 Task 1 creates composer.json. No `composer install` step. Task 2/3 verify: `vendor/bin/phpunit ...`.
- Fix: Add a one-time `composer install --no-interaction --no-progress` step to the beginning of Plan 01-01 Task 1 (or as a separate scaffold step).

**16. [task_completeness] Threat T-01-2-02 (XSS in toast) is not enforced by the implementation spec or verify**
- Plan: 01-02
- Task: Task 1 / threat model
- The threat model says 'The message is rendered as text content (not innerHTML), so HTML injection is not possible.' But the action spec for the toast module does not explicitly say `textContent` vs `innerHTML`. A non-domain-fluent executor may default to `innerHTML` and reintroduce the very issue the threat claims is mitigated. The verify (toast.test.php) does not check this.
- Evidence: 01-02 action: 'show(message, type) appends a `<div class="toast ...">` containing a `<span class="toast__message">{message}</span>`'. The `{message}` placeholder is ambiguous.
- Fix: Update the action spec to use `element.textContent = message` (or equivalent), and add a verify assertion in `toast.test.php` that checks the JS source uses `textContent` (not `innerHTML`).

**17. [task_completeness] List-view toggle `sessionStorage` key invented by the plan, not specified in CONTEXT.md**
- Plan: 01-02
- Task: Task 1
- Plan 01-02 writes to `sessionStorage.tickettrade.listView`. CONTEXT.md D-12 only says 'persists per session via sessionStorage' without specifying the key. A future phase may use a different key.
- Evidence: Plan 01-02 action: 'writes `sessionStorage.tickettrade.listView` to the new value'. CONTEXT.md D-12: 'listViewToggle — `aria-pressed`, persists per session via sessionStorage'.
- Fix: Add the key name to CONTEXT.md D-12, or document the choice in the plan.

**18. [verification_derivation] Plan 01-01 must_haves references `/settings` mockup that does not exist in 01-01**
- Plan: 01-01
- Task: Must_haves
- 01-01 must_haves says: 'Toggling the theme control on the mockup's `/settings` surface (mockup-provided control) writes `localStorage.tickettrade.theme`'. But the plan's actions create only `board-mobile.html`. The settings page is explicitly deferred to Phase 2 per CONTEXT.md D-07.
- Evidence: 01-01 must_haves (above). CONTEXT.md D-07: '/settings toggle (a Phase 2 surface)'. 01-01 action: only creates `public/mockups/board-mobile.html`.
- Fix: Either add a minimal `public/mockups/settings.html` to 01-01 with a theme toggle, or rewrite the must_haves to say '`TicketTrade.setTheme(mode)` from the browser console writes localStorage.tickettrade.theme and the next paint renders the chosen theme'.

**19. [scope_sanity] `Support\ResponseHeaders` stub is silently a no-op**
- Plan: 01-01
- Task: Task 1
- The action defines `Support\ResponseHeaders` as 'a no-op stub that defines the class with an empty `boot()` method'. Per AD-13, the real headers are set at front-controller boot. A non-domain-fluent executor may not realize the stub must be called, or may delete the call thinking it is unused. Phase 9 must replace this stub; the plan does not flag it as a TODO.
- Evidence: 01-01 action: 'Support\ResponseHeaders reference is a no-op stub that defines the class with an empty `boot()` method; Phase 9 wires the real headers per AD-13'. ARCHITECTURE-SPINE.md AD-13: 'No Action may override these headers.'
- Fix: Add a TODO comment to the stub, or document in the plan <done> block that the stub is intentionally empty and Phase 9 must replace it. No code change required.

## Summary

| Severity | Count |
|---|---|
| Blocker | 4 |
| Warning | 15 |
| Info | 0 |

**Phase goal will not be achieved without fixing the 4 blockers before execution.** The blockers are concentrated in Plan 01-01's verify commands (construct/assertion defects that will silently pass on broken implementations or fail on correct ones) and the cross-plan forward dependency on the toast module. The 15 warnings are quality/maintainability issues that should be addressed in the next revision but do not block execution.

## Recommendation

Return to the planner with the four blockers. Most importantly:

1. **Fix verify command 5 in 01-01** (the `; true` bug + the wrong HTTP-code expectation) — the executor cannot self-detect a broken implementation.
2. **Fix verify command 1 in 01-01** (the `< 60` threshold will fail on a correct token set; the command does not match its `fails_when` text).
3. **Resolve the cross-plan toast contract** — either stub `toast.show` in 01-01 or move the call to 01-02.
4. **Decide the Phase 1 landing behavior for empty routes** — either a stub HTML page (so `/` returns 200) or change the verify to expect the documented exception.

The warnings can be addressed in a follow-up revision without blocking `/gsd-execute-phase`, though ISSUE-05 (`.htaccess` admin routing), ISSUE-08 (Bootstrap version pin), and ISSUE-10 (ROADMAP success criteria wording) are the highest-value to fix in the same pass.

---

*Review complete. Plans checked: 2. Issues: 4 blocker(s), 15 warning(s).*