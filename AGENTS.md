<!-- GSD:project-start source:PROJECT.md -->

## Project

**TicketTrade (NSBM Marketplace)**

TicketTrade is a campus-only peer-to-peer marketplace for NSBM Green University students to buy and sell products and services. Every purchase produces a confirmable digital ticket, a 6-tier gamified rank system rewards repeat trading, and seller ratings make trust visible - so nobody trades blind. Form factor is a responsive web app (desktop + mobile browsers, Bootstrap 5 grid). Stack is PHP 8+ with MySQL 8+, HTML/CSS/vanilla JS, sole Composer dependency `ramsey/uuid`. Purchases are simulated - no payment gateway, no real money (assignment requirement).

**Core Value:** Every trade ends with proof: each purchase produces a confirmable digital ticket that both parties verify, so nobody trades blind on campus. Verified identity plus lightweight reputation plus simulated ticket confirmation plus seller ratings equals sufficient trust for campus-scale peer trade without escrow or algorithmic reputation.

## WAD Assignment Context

This is the Batch 26.1 **Web and Mobile Application Development (WAD)** final project at NSBM Green University, Faculty of Computing. Topic 4: "NSBM Marketplace - Student Business and Service Platform." Full brief: `WAD_Batch26.md`. Topic 4 explicitly encourages "additional innovative features beyond the minimum requirements" - the gamification, digital ticket, dispute, and review systems are those innovations.

### Rubric (WAD_Batch26.md sec 5, total 100%)

| Weight | Line | Owner function |
|--------|------|----------------|
| 20% | UI Design and Frontend | Frontend Lead |
| 20% | Backend and Database Integration | Backend Lead + Database Engineer |
| 15% | Admin Panel Functionality | Backend Lead |
| 15% | Student/User Panel Functionality | Frontend Member |
| 15% | Project Report, Screenshots & Drive Links | QA/Docs Lead |
| 15% | Video Demonstration & Teamwork | All 6 (each member describes their contribution) |

### Deliverables (WAD_Batch26.md sec 4)

1. **Project Report** (PDF/Word) - title, team leader + contact, all 6 member names + roles, intro, objectives, system description, screenshots (admin + student), Google Drive link to source code (public), Google Drive link to demo video.
2. **Project Source Code** - complete folder on Google Drive, public access, link in report.
3. **Video Demonstration** - one screen recording covering: login, admin panel, student panel, CRUD ops, database functionality, main features, per-member contribution description.
4. **Submission** - team leader only, via LMS. Deadline: tentative 2026-09-02.

### Team (Batch 26.1, 6 students)

Source of truth: `config/team.php` (consumed by the landing-page Team section per Phase 3 D-26). Update that file with real names + leader assignment - the landing page re-renders automatically.

| # | Function | Role | Owner of |
|---|----------|------|----------|
| 1 | Backend | Backend Lead | `Auth/Service`, `Support\Auth`, AD-18 bcrypt sniff |
| 2 | Backend | Backend Member | `Listing/Service`, `Ticket/Service`, migrations |
| 3 | Frontend | Frontend Lead | design tokens, layout template, listing modal |
| 4 | Frontend | Frontend Member | board view, My Tickets, profile pages |
| 5 | Database | Database Engineer | schema design, FULLTEXT indexes, leaderboard summaries |
| 6 | QA/Docs | QA + Docs Lead | PHPUnit suites, phpcs, project report, video edit |

### Constraints

- **Tech stack (assignment-mandated)**: PHP 8+ / MySQL 8+ / HTML/CSS/vanilla JS - no frameworks, no ORM, no regex routing. Sole Composer dependency: `ramsey/uuid`. Dev: `phpcs`, `phpunit`.
- **Timeline**: MVP due 2026-09-02 (~3-week sprint, 6-person team, Batch 26.1 WAD coursework)
- **Code style**: PSR-12 - `vendor/bin/phpcs --standard=PSR12 src/`
- **Security baseline**: bcrypt cost >= 12, PDO prepared statements everywhere, CSRF tokens, uploaded files re-encoded to WebP behind validation, Sri Lankan mobile regex for WhatsApp, layered rate limits, hardened session cookies, security headers
- **Performance**: < 2 s pages, <= 50 listings/page, thumbnails generated at upload (200/600/1200 px WebP 80% quality), cron jobs complete < 30 s for 10k tickets
- **Reliability**: atomic UPDATE redemption (naturally idempotent), cron idempotent + `flock()`-guarded + Asia/Colombo timezone + replay-safe (`TRUNCATE cron_log` -> rerun = identical result), manual trigger endpoint, points log `UNIQUE KEY uniq_event (event_uuid)` closes the duplicate-NULL hole
- **Compliance (assumption-backed)**: PDPA 2022 not yet in force -> minimal data; Computer Crimes Act sec 26 intermediary exemption via reactive moderation (every listing enters 24h review window by default); clear "simulation only" labeling
- **Operational**: dev server `php -S localhost:8000 -t public`; migrations `php migrate.php` (idempotent, versioned); Git never push to main; PRs only, one approval required; admin/sensitive actions write audit_log row
- **Cut order (pre-agreed for week-2 crunch)**: leaderboards -> bulk admin actions -> login streaks -> draft/relist - the core loop (list, approve, ticket, redeem, expire, dispute) is never cut

<!-- GSD:project-end -->

<!-- GSD:stack-start source:STACK.md -->

## Technology Stack

| Layer | Choice | Notes |
|-------|--------|-------|
| Backend language | PHP 8+ | WAD mandate; no other language permitted |
| Database | MySQL 8+ | WAD mandate; InnoDB, utf8mb4 |
| Frontend framework | Bootstrap 5.3 (CDN) | WAD allows Bootstrap or Material UI; team chose Bootstrap |
| Build tooling | None | No transpiler, no bundler - matches assignment constraint |
| Sole Composer dep | `ramsey/uuid ^4.7` | UUID v7 generation for ticket codes + event_uuid |
| Dev tooling | `phpcs` (PSR-12), `phpunit` | in `require-dev` only |
| Server | `php -S localhost:8000 -t public` | dev; nginx + PHP-FPM in prod |
| Timezone | Asia/Colombo | set in `config/bootstrap.php` |

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

### Naming
- **Files**: `snake_case.php` (e.g., `auth_service.php`, `user_model.php`).
- **Classes**: `PascalCase`, namespaced `App\<Context>\<Layer>` (e.g., `App\Auth\Service\auth_service`, `App\User\Model\user_model`).
- **DB identifiers**: `snake_case` (tables, columns, indexes). Per ARCHITECTURE-SPINE.md conventions table.
- **Routes**: `METHOD /path/{param}` format in `config/routes.php`.
- **Tokens**: `--color-primary`, `--space-4`, `--shape-md` (semantic, not visual). Traced 1:1 to DESIGN.md contrast ledger rows.
- **Error codes**: `E_<DOMAIN>_<REASON>` (e.g., `E_AUTH_INVALID`, `E_LISTING_NOT_FOUND`, `E_RATE_LIMIT`, `E_CSRF`). UI switches on code, not message text.

### Per-context owner (AD-2 + AD-10)
- `Auth/Service/auth_service.php` is the sole writer of `password_hash` / `password_verify` (AD-18). phpcs `Custom\Sniffs\NoRawHash` enforces this.
- `Points/Service/points_service.php` is the sole writer of `points_log` + `users.points` + `users.tier` (AD-10).
- `Listing/Service/listing_service.php` is the sole writer of `listings` + `listing_images` + `listing_revisions`.
- `Support\Audit` is the sole writer of `audit_log` (Phase 4+).

### Code organization (AD-1)
- Action -> Service -> Model dependency arrow is strictly downward. Cross-context work goes through Services only.
- Bootstrap -> FrontController -> Action -> Service -> Model -> PDO. Models never import Actions; Services never import Controllers; Contexts never import another Context's Model.

### Git workflow
- Branch from `NSBM-EventHub` (current dev branch); never push to `main`.
- One approval per PR. Conventional commit messages (`feat:`, `fix:`, `docs:`, `chore:`).
- Phase work lands via GSD phases; small fixes via `/gsd-quick`.

<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

Layered Modular Monolith. Dependency arrow: Bootstrap -> FrontController -> Action -> Service -> Model -> PDO. Webroot at `public/`; `src/` outside webroot. Sole Composer dep: `ramsey/uuid`.

```
Bootstrap (config/bootstrap.php)
  -> FrontController (public/index.php, public/admin/index.php)
    -> Router (config/routes.php)
      -> Action (src/<Context>/Action/*_action.php)  [thin: validate -> call Service -> render View]
        -> Service (src/<Context>/Service/*_service.php)  [business rules; sole writer per AD]
          -> Model (src/<Context>/Model/*_model.php)  [single-table data access]
            -> PDO (src/Support/Db.php)
```

AD-1..AD-20 are the load-bearing decisions. Read `ARCHITECTURE-SPINE.md` for the full list. Critical for Phase 3 onward:
- AD-13: session config, CSRF per session, rate limits (login 5/5min/IP, listing_create 20/hr/user, purchase 10/hr/user, redemption 5/hr/ticket, points 150/day/user).
- AD-14: image storage outside webroot; 4-layer validation pipeline (finfo -> getimagesize -> magic bytes -> GD re-encode); three WebP thumbnails (200/600/1200); proxy-mediated serving.
- AD-15: review/dispute gates on ticket state (`status IN ('redeemed','expired') AND dispute_status='none'` for reviews; `status='active' AND dispute_status='none'` for disputes).
- AD-16: failure envelope `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`.
- AD-18: bcrypt-only at cost >= 12, sole owner `Auth/Service/auth_service.php`.
- AD-19: admin re-auth 300s sliding window for sensitive destructive Actions.
- AD-20: cohort isolation gate at S2 retro, not Phase 3.

<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Phase work order (current): 1 done -> 2 done -> 3 (Marketplace Listings) -> 4 (Purchases + Tickets) -> 5 (Reviews) -> 6 (Points + Ranks) -> 7 (Reports + Disputes) -> 8 (Admin Console) -> 9 (Operational Substrate). MVP must ship 2026-09-02.

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.

<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->

<!-- GSD:env-quirks-start source:learned 2026-09-03 from phase 5 execution -->

## IDX / opencode Runtime Quirks (learned in Phase 5)

**Git root is the monorepo, not tickettrade.** `git rev-parse --show-toplevel` returns `/home/user/hermesag` (the IDX workspace root), not `004/tickettrade`. tickettrade is a subdirectory. Implications:
- Worktree isolation targets the parent repo and pulls in unrelated dirty state (Archon, cole-medin-knowledge-base, .idx/, .planning artifacts). Avoid worktrees in this layout — set `workflow.use_worktrees=false` in `.planning/config.json` before dispatching executors.
- `git add -A` from the parent root is destructive. Executors must stage only paths under `004/tickettrade/` that belong to their plan's `files_modified` list. Use explicit paths.
- Pre-existing untracked files at the parent root (e.g. `004/tickettrade/ARCHITECTURE-SPINE.md`, `004/tickettrade/.planning/milestone.lock`, `004/tickettrade/config/db.php`, `004/tickettrade/public/uploads/listings/...`) are NOT orphans to clean up — leave them alone.

**MySQL is reachable from the executor's bash tool, but credentials are not always on the default `mysql -u root` path.** Before a subagent burns time on a `vendor/bin/phpunit` failure that smells like "no test DB", check:
- `config/db.test.php` for the test DSN + credentials.
- `config/db.php` for the dev DSN.
- If PHPUnit integration tests fail with `SQLSTATE[HY000] [2002]` or `Access denied`, the issue is usually DSN/credentials, not code. Read the test bootstrap (`tests/bootstrap.php` or `phpunit.xml` `<php><env>`) before debugging source.

**Test DB location:** `config/db.test.php` is the source of truth. `vendor/bin/phpunit` runs from the tickettrade project root (`/home/user/hermesag/004/tickettrade`).

**Composer is local to tickettrade.** Run `composer install` from `/home/user/hermesag/004/tickettrade`, not the parent. `vendor/bin/phpunit` and `vendor/bin/phpcs` only exist there.

## Subagent session-resumption protocol (IDX/opencode)

The opencode `task` tool can spawn a subagent and resume it via `task_id`. Two failure modes are common here and have known recovery:

1. **Subagent gets aborted mid-run (Ctrl-C, network blip, user stop).** The orchestrator may have lost the `task_id` if the user aborted before the spawn returned. Recovery: spawn a *throwaway* probe (`prompt: "hi"`, any `gsd-*` agent) — the returned `task_id` confirms the runtime can spawn. Then **send an empty prompt `[continue]` to the original task_id** if it was captured. The child resumes its prior context (files read, decisions made) and picks up. Do NOT re-dispatch the full plan — the child has the state.

2. **Subagent returns an empty `task_result`.** This is a network issue, not a logic failure. The child likely finished the work and just lost the return envelope. Recovery: check the filesystem for the expected outputs (SUMMARY.md, commits, files). If they exist, the work is done — proceed. If they don't, send `[continue]` to the same task_id; the child will either finish or report the actual blocker.

3. **Spawning a real run before getting the id is wasteful** because if the user aborts you can't reach the child. Standard order: probe (`hi`) → capture `task_id` → run real prompt on that same `task_id` (resume). The probe session is fresh but cheap; resuming on it lets the user reach into it if they need to.

**Naming convention for `task` descriptions:** prefix with `<N> GSD-<Role> <Plan-Id> <Stage>` so the manager's spot-check grep is human-readable. Example: `"1 GSD-Executor 05-02 Task 3 + SUMMARY"`, `"2 GSD-Verifier 05 phase"`. The `<N>` is the wave position (1, 2, ...); `<Role>` is `Executor` / `Verifier` / `Planner`; `<Plan-Id>` is `05-01` / `05-02`; `<Stage>` is the task name.

## Known issues carried forward from prior phases

- **Phase 5 left a `01-PLAN-CHECK-v2.md` / `-v3.md` artifact pattern** at `.planning/phases/01-ux-foundation-design-system/`. Don't panic about these; they're from plan-checker iteration. The canonical plan is `01-PLAN.md`.
- **`/home/user/hermesag/004/.planning/async-jobs/` does not exist.** Plan 05-02 resume guidance references this path; it is not used in this project — resume via `task_id` only.
- **`.planning/STATE.md` and `state.json` can drift from each other** if updated ad-hoc. After every phase, run `node /home/user/.claude/gsd-core/bin/gsd-tools.cjs state complete-phase --phase <N>` from the tickettrade project root — this is the canonical write path and updates both.
- **`milestone.lock` at `/home/user/hermesag/004/tickettrade/.planning/milestone.lock`** is a runtime lockfile (untracked). Do not commit it.
<!-- GSD:env-quirks-end -->
