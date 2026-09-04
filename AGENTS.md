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

**Repository layout (learned 2026-09-04):** The local monorepo at `/home/user/hermesag` holds many sub-projects under numbered dirs (`001/`, `002/`, `003/`, `004/tickettrade/`, ...). The **public GitHub repo** (`https://github.com/Sudila-Kuruppu/HermesAg-TicketTrade`) is the **tickettrade-only** repo, not the monorepo. The local trunk is the `NSBM-EventHub` branch; the GitHub repo's trunk is `main`. Never conflate them. The two repos have **completely different histories** — the GitHub repo has only what was subtree-split and pushed from the monorepo; the monorepo's `004/tickettrade/` is always ahead of GitHub `main` (it has every phase). The per-phase branch workflow below is what reconciles this.

**Branch + push workflow:**
- Local dev happens on `NSBM-EventHub` (or a feature branch off it). Commit with **explicit paths** to `git add` — never `git add -A` from the monorepo root (`git add 004/tickettrade/<file>` is the safe form).
- Pushes to GitHub go to a **per-phase branch** named `phase-<NN>-<slug>` (e.g. `phase-03-validation`, `phase-04-purchases-tickets`). The branch is **force-pushed from the monorepo's tickettrade subtree** and then opened as a PR against GitHub `main`. See `<!-- GSD:env-quirks-end -->` → "GitHub push via subtree split" below for the exact procedure.
- **Never push to GitHub `main` directly** — PRs only, one approval required.
- One approval per PR. Conventional commit messages (`feat:`, `fix:`, `docs:`, `chore:`).
- Phase work lands via GSD phases; small fixes via `/gsd-quick`.

**Per-phase branches (added 2026-09-04):** Each phase's GitHub branch is a **self-contained snapshot of the full tickettrade history at the moment the phase is done**, not just the new phase commits. This is how the monorepo→GitHub split works: the per-phase branch IS the entire repo, ready to PR into `main`. Branch names that exist as of 2026-09-04: `phase-01-validation`, `phase-03-validation`. Convention: `phase-<NN>-<slug>` where `<slug>` is a short label (e.g. `validation`, `purchases-tickets`, `reviews-ratings`). The corresponding monorepo commits live on `NSBM-EventHub`; the `.planning/phases/<NN>-<slug>/` directory naming matches the branch slug.

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

> **Workspace-level runtime notes** (opencode subagent protocol, monorepo git root, MariaDB
> socket, Composer isolation, GitHub auth) live in `004/AGENTS.md`. This section adds the
> **tickettrade-specific** runtime bits on top: the test DB fingerprint flow, the
> `bin/dev-setup.sh` bootstrap, and the per-phase GitHub subtree split procedure.

**Git root is the monorepo, not tickettrade.** `git rev-parse --show-toplevel` returns `/home/user/hermesag` (the IDX workspace root), not `004/tickettrade`. tickettrade is a subdirectory. Implications:
- Worktree isolation targets the parent repo and pulls in unrelated dirty state (Archon, cole-medin-knowledge-base, .idx/, .planning artifacts). Avoid worktrees in this layout — set `workflow.use_worktrees=false` in `.planning/config.json` before dispatching executors.
- `git add -A` from the parent root is destructive. Executors must stage only paths under `004/tickettrade/` that belong to their plan's `files_modified` list. Use explicit paths.
- Pre-existing untracked files at the parent root (e.g. `004/tickettrade/ARCHITECTURE-SPINE.md`, `004/tickettrade/.planning/milestone.lock`, `004/tickettrade/config/db.php`, `004/tickettrade/public/uploads/listings/...`) are NOT orphans to clean up — leave them alone.

**MySQL is reachable from the executor's bash tool, but credentials are not always on the default `mysql -u root` path.** Before a subagent burns time on a `vendor/bin/phpunit` failure that smells like "no test DB", check:
- `config/db.test.php` for the test DSN + credentials.
- `config/db.php` for the dev DSN.
- If PHPUnit integration tests fail with `SQLSTATE[HY000] [2002]` or `Access denied`, the issue is usually DSN/credentials, not code. Read the test bootstrap (`tests/bootstrap.php` or `phpunit.xml` `<php><env>`) before debugging source.

**Test DB + run flow:** `bin/dev-setup.sh` is the one-shot env bootstrap (creates dev + test DBs, writes `config/db.php` and `config/db.test.php` from a socket probe, runs `composer install`, applies migrations if the DB is empty). Re-run it any time. `bin/test <args>` is the entry point for phpunit — it calls `bin/dev-setup.sh` first, then rebuilds the test DB **only when the schema fingerprint has changed** (md5 of sorted `*.sql` filenames, cached at `data/.test-schema-fingerprint`). Subsequent runs skip the drop+remigrate and go straight to phpunit. To force a clean rebuild: `rm data/.test-schema-fingerprint`. Direct `vendor/bin/phpunit` invocations still work from the tickettrade project root — they don't drop the DB, so accumulated state from prior runs can cause UNIQUE-constraint flakes on tests that use random values (see `tests/Unit/Phase03/Support/ImageProxyTest::seedCategory()` which uses a monotonic counter seeded from `MAX(sort_order)` for that reason).

**MariaDB socket:** the local MariaDB listens on `/tmp/mysql.sock`. Both `config/db.php` and `config/db.test.php` default to that socket; `bin/dev-setup.sh` writes them on first run. Override at invocation time with `DB_DSN` (e.g. `DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=tickettrade_test;charset=utf8mb4'`).

**Composer is local to tickettrade.** Run `composer install` from `/home/user/hermesag/004/tickettrade`, not the parent. `vendor/bin/phpunit` and `vendor/bin/phpcs` only exist there.

**GitHub push via subtree split (learned 2026-09-04 from validate-phase work):** Because the GitHub repo is tickettrade-only and local work happens inside the monorepo at `004/tickettrade/`, you cannot `git push` directly — the local repo's root is `/home/user/hermesag`, not the tickettrade subdir. Procedure (verified working 2026-09-04 pushing `phase-03-validation`):

1. **Commit on the local monorepo branch** (`NSBM-EventHub` or a feature branch off it) with **explicit paths** to `git add` — never `git add -A` from the monorepo root (it would sweep in unrelated dirty state from sibling projects). `git add 004/tickettrade/<file>` is the safe form. Conventional commit messages: `feat:`, `fix:`, `docs:`, `chore:`.
2. **Subtree split** from the monorepo root `/home/user/hermesag`: `git subtree split --prefix=004/tickettrade -b tt-push HEAD`. This produces a local branch whose tree is **only the tickettrade contents, preserving the full history of every phase** (148+ commits as of 2026-09-04).
3. **Push to a per-phase branch on GitHub**: `git push -f https://github.com/Sudila-Kuruppu/HermesAg-TicketTrade.git tt-push:phase-<NN>-<slug>`. The `-f` is required because the per-phase branch is a self-contained snapshot, not a delta on top of GitHub `main`. The first push to a new branch does not need `-f`, but subsequent re-pushes to the same branch do.
4. **Open a PR** at https://github.com/Sudila-Kuruppu/HermesAg-TicketTrade/pull/new/phase-<NN>-<slug>. One approval required.
5. **Delete the local `tt-push` branch** immediately: `git branch -D tt-push`. Do NOT keep it as a long-lived local branch — it diverges from the source-of-truth (`NSBM-EventHub`) on every new commit.
6. **Verify**: `git ls-remote https://github.com/Sudila-Kuruppu/HermesAg-TicketTrade` should show your new branch in the output. The push's stdout ends with `* [new branch]      tt-push -> phase-XX-<slug>` on success.

**Auth:** GitHub credentials are handled by `gh auth git-credential` (Nix package at `/nix/store/.../gh-*/bin/gh`). If the credential helper path in `.gitconfig` points at a stale Nix path, the push will print warnings but still succeed (the helper only runs on credential-required operations; the push uses the cached PAT). If a push hangs on `Username for 'https://github.com':`, run `gh auth status` and re-auth.

**Do NOT push the local `NSBM-EventHub` branch directly.** It contains every sub-project (`001/`, `002/`, ...), worktrees, and dirty state that has nothing to do with tickettrade. Pushing it once (in 2026-09-03 validate-phase work) created a branch on GitHub with the wrong content and had to be deleted.

**Do NOT merge GitHub `main` into `tt-push` before pushing.** The per-phase branch is intentionally a self-contained snapshot. If you need GitHub `main`'s history in your branch, fetch it and use it as the base of a separate PR instead.

**The `HermesAg-TicketTrade` remote** is the public repo. The `origin` remote in the monorepo points to `/home/user/hermesag` (a local path) and is unrelated. Add the GitHub remote on-demand with `git remote add upstream https://github.com/Sudila-Kuruppu/HermesAg-TicketTrade.git` if you need it persistently; the procedure above uses the full URL inline so you don't need to.

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
