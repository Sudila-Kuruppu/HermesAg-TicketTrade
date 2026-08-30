# Phase 1: UX Foundation & Design System - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-30
**Phase:** 1-UX Foundation & Design System
**Areas discussed:** Token system shape, Theme persistence mechanism, Mockup-driven surface scope, Bootstrap 5 integration surface

---

## Token system shape

| Option | Description | Selected |
|--------|-------------|----------|
| A — Granular flat | One CSS file with all roles named after DESIGN.md tokens; one HTTP request; easy to grep | |
| B — Layered (3 files) | tokens / semantic / components chain; most extensible; hardest to grep | |
| C — Two files (tokens + Bootstrap override) | tokens.css (~150 vars, light + dark) + bootstrap-overrides.css (--bs-* mapped to design tokens); bundled via tickettrade.css @imports | ✓ |

**User's choice:** C
**Notes:** The two-file structure is the right separation: brand layer (tokens) vs. integration layer (Bootstrap mapping). The `tickettrade.css` bundle that the success criteria names is preserved as the user-facing entry point, with `@import` composing the two source files. Single HTTP request from the page, two maintainable source files.

## Theme persistence mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| A — Inline FOUC + localStorage | Synchronous inline <script> in <head> (~200 bytes), localStorage('tickettrade.theme'), per-surface system default | ✓ |
| B — localStorage + cookie mirror | Same as A but also writes a cookie so server can SSR the right theme | |
| C — @media (prefers-color-scheme) first paint | CSS uses media query for first paint, JS hydrates | |

**User's choice:** A
**Notes:** DESIGN.md and EXPERIENCE.md both require the three-state light/dark/system toggle. The cookie mirror in B adds a cookie to the security baseline for no functional gain — the inline script handles the FOUC. C is out because DESIGN.md says "prefers-color-scheme is the first-visit fallback only" (a flicker every time a returning user visits is unacceptable).

## Mockup-driven surface scope

| Option | Description | Selected |
|--------|-------------|----------|
| A — Three static HTML mockups | public/mockups/{board-mobile,my-tickets,admin-dashboard}.html, link tickettrade.css, no PHP | ✓ |
| B — Three thin PHP Views | Each as stub Action → Service → View with hardcoded fixture data; exercises full FrontController path | |
| C — Hybrid | One static + two PHP views | |

**User's choice:** A
**Notes:** Phase 1 is a design foundation phase, not a routing foundation phase. Auth, route guards, and session middleware all land in Phase 2 — building PHP views in Phase 1 means reworking them when the auth layer lands. Static mockups serve as design references AND as the verification harness for the contrast and responsive criteria (success criteria #5 and #7). Conversion to PHP views in Phase 2/3 is mechanical.

## Bootstrap 5 integration surface

| Option | Description | Selected |
|--------|-------------|----------|
| A — Per-component JS files | toast.js, bottom-nav.js, skeleton.js, etc., one <script> per component | |
| B — Single tickettrade.js bundle | Self-registering on data-* attributes, ~300 LOC, window.TicketTrade namespace | ✓ |
| C — ES modules | tickettrade.mjs + per-component modules, modern but adds loading complexity | |

**User's choice:** B
**Notes:** No framework, no build step, no bundler — assignment constraint. The single bundle is small enough to ship as a static file. Self-registration on data-* attributes means Views add behavior by adding markup, not by adding script blocks. The `window.TicketTrade` namespace covers the server-rendered flash-toast use case (every page loads with `<div data-flash-toast="...">` containing messages set by the server).

---

## the agent's Discretion

None — every area was explicitly decided by the user.

## Deferred Ideas

- **Empty/error state named copy (UX-DR-34)** — Authoring the per-surface copy strings. Ships with each surface in Phase 2/3.
- **axe-core in test plan** — Automated a11y scanning. Defer to test infrastructure phase (Phase 2 or 9).
- **Layout template + FrontController** — The `<head>`/`<body>` convention starts in Phase 1 (mockups include the same head block), but the actual layout template and FrontController dispatch land in Phase 2 with auth.
- **Settings page** — `/settings` is the entry point for theme toggle UI. Ships in Phase 2.
