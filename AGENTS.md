<!-- GSD:project-start source:PROJECT.md -->

## Project

**TicketTrade (NSBM Marketplace)**

TicketTrade is a campus-only peer-to-peer marketplace for NSBM Green University students to buy and sell products and services. Every purchase produces a confirmable digital ticket, a 6-tier gamified rank system rewards repeat trading, and seller ratings make trust visible — so nobody trades blind. Form factor is a responsive web app (desktop + mobile browsers, Bootstrap 5 grid). Stack is PHP 8+ with MySQL 8+, HTML/CSS/vanilla JS, sole Composer dependency `ramsey/uuid`. Purchases are simulated — no payment gateway, no real money (assignment requirement).

**Core Value:** Every trade ends with proof: each purchase produces a confirmable digital ticket that both parties verify, so nobody trades blind on campus. Verified identity plus lightweight reputation plus simulated ticket confirmation plus seller ratings equals sufficient trust for campus-scale peer trade without escrow or algorithmic reputation.

### Constraints

- **Tech stack (assignment-mandated)**: PHP 8+ / MySQL 8+ / HTML/CSS/vanilla JS — no frameworks, no ORM, no regex routing. Sole Composer dependency: `ramsey/uuid`. Dev: `phpcs`, `phpunit`.
- **Timeline**: MVP due 2026-09-02 (~3-week sprint, 6-person team, Batch 26.1 WAD coursework)
- **Code style**: PSR-12 — `vendor/bin/phpcs --standard=PSR12 src/`
- **Security baseline**: bcrypt cost ≥ 12, PDO prepared statements everywhere, CSRF tokens, uploaded files re-encoded to WebP behind validation, Sri Lankan mobile regex for WhatsApp, layered rate limits, hardened session cookies, security headers
- **Performance**: < 2 s pages, ≤ 50 listings/page, thumbnails generated at upload (200/600/1200 px WebP 80% quality), cron jobs complete < 30 s for 10k tickets
- **Reliability**: atomic UPDATE redemption (naturally idempotent), cron idempotent + `flock()`-guarded + Asia/Colombo timezone + replay-safe (`TRUNCATE cron_log` → rerun = identical result), manual trigger endpoint, points log `UNIQUE KEY uniq_event (event_uuid)` closes the duplicate-NULL hole
- **Compliance (assumption-backed)**: PDPA 2022 not yet in force → minimal data; Computer Crimes Act §26 intermediary exemption via reactive moderation (every listing enters 24h review window by default); clear "simulation only" labeling
- **Operational**: dev server `php -S localhost:8000 -t public`; migrations `php migrate.php` (idempotent, versioned); Git never push to main; PRs only, one approval required; admin/sensitive actions write audit_log row
- **Cut order (pre-agreed for week-2 crunch)**: leaderboards → bulk admin actions → login streaks → draft/relist — the core loop (list, approve, ticket, redeem, expire, dispute) is never cut

<!-- GSD:project-end -->

<!-- GSD:stack-start source:STACK.md -->

## Technology Stack

Technology stack not yet documented. Will populate after codebase mapping or first phase.
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

Conventions not yet established. Will populate as patterns emerge during development.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

Architecture not yet mapped. Follow existing patterns found in the codebase.
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
