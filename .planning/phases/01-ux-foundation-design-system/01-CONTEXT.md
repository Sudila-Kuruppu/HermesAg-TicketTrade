# Phase 1: UX Foundation & Design System - Context

**Gathered:** 2026-08-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Ship the design token system, theme persistence with FOUC-free first paint, accessibility floor (WCAG 2.1 AA, keyboard nav, ARIA), toast container, bottom nav, skeleton shimmer, and three static mockup surfaces (board-mobile, my-tickets, admin-dashboard) so every later screen inherits identical look, feel, and behavior. Phase 1 does NOT include route guards, session middleware, or real PHP views — those land in Phase 2 (Auth) and Phase 3 (Listings).

</domain>

<decisions>
## Implementation Decisions

### Token system shape (Area 1)
- **D-01:** Two-file CSS architecture under `public/assets/css/`. Source files: `tickettrade.tokens.css` (~150 design tokens, light + dark sections under `:root[data-theme="light"]` and `:root[data-theme="dark"]`) and `tickettrade.bootstrap-overrides.css` (Bootstrap variable overrides only). User-facing bundle: `tickettrade.css` is the entry point that `@import`s both. **Reversibility:** reversible — renaming the bundle file is a 30-second find-and-replace if we change our mind.
- **D-02:** Token names map 1:1 to roles in DESIGN.md (e.g., `--color-primary`, `--color-status-pending-fill`, `--color-rank-d`, `--font-family-display`, `--shape-md`, `--space-4`). Names are semantic, not visual. The contrast ledger in DESIGN.md is the source of truth; tokens are a direct transcription.
- **D-03:** Bootstrap 5.3 from CDN; Bootstrap JS for modal/dropdown/accordion/pagination/table/form controls (stock behavior, per EXPERIENCE.md). `tickettrade.bootstrap-overrides.css` re-skins Bootstrap's `--bs-*` tokens to point at the design tokens, so every Bootstrap primitive inherits the brand layer without custom CSS per component.

### Theme persistence mechanism (Area 2)
- **D-04:** Theme persisted in `localStorage` under key `tickettrade.theme` with three values: `light`, `dark`, `system`. Default is `system`. **Reversibility:** reversible — adding a cookie mirror later is a 20-line change.
- **D-05:** FOUC-free first paint via a synchronous inline `<script>` in `<head>` (~200 bytes) that runs before CSS loads. The script reads localStorage, falls back to per-surface default (student surface → `matchMedia('(prefers-color-scheme: dark)')` then dark, admin surface → light), and sets `data-theme` on `<html>`.
- **D-06:** Server renders surface-agnostic HTML. Each page sets a `data-surface="student|admin"` attribute on `<html>` (set by the layout template based on the route). The inline script reads this attribute to pick the system-preference fallback. No server-side theme awareness; the cookie mirror is intentionally not introduced.
- **D-07:** `/settings` toggle (a Phase 2 surface) updates localStorage, sets `data-theme`, and emits a toast "Theme set to {mode}." The toggle offers the same three-state light/dark/system control per EXPERIENCE.md.

### Mockup-driven surface scope (Area 3)
- **D-08:** Three static HTML mockups in `public/mockups/`: `board-mobile.html`, `my-tickets.html`, `admin-dashboard.html`. They are visual references, not production surfaces. **Reversibility:** reversible — converting a static mockup to a thin PHP View in Phase 2 or 3 is straightforward (the markup is already there, just wrap it in a template and pass fixture data).
- **D-09:** Each mockup `<link>`s the same `tickettrade.css` bundle the production app will use. This means the contrast and responsive criteria in success criteria #5 and #7 are verified by opening the mockup files in a browser — no PHP server required.
- **D-10:** Mockups use fixture data hardcoded in the HTML (e.g., 4 listings for board, 2 ticket cards for my-tickets, 4 KPI cards + 1 chart placeholder for admin-dashboard). No `<script>` driven data; the markup is the contract.

### Bootstrap 5 integration surface (Area 4)
- **D-11:** Single JS bundle at `public/assets/js/tickettrade.js` (~300 LOC), loaded with `defer` in the layout template after Bootstrap CDN bundle. No build step, no transpiler, no bundler — matches the assignment constraint. **Reversibility:** reversible — splitting into per-component files later is a mechanical refactor.
- **D-12:** Components self-register on `data-*` attributes in the DOM. Eight components in Phase 1:
  1. `toast` — ARIA live region, queue max 3, 4s/8s dismiss, manual dismiss on error/warning, pause on hover/focus
  2. `bottomNav` — fixed, 64px tall, hidden ≥768px, `aria-current="page"` on active, no badge counts
  3. `skeleton` — shimmer 1s, surface-container-high fill, opt-in via `data-skeleton`
  4. `themeController` — reads/writes localStorage, syncs `data-theme`
  5. `modalScrimGuard` — opt-in 2s scrim-click suppression (purchase confirmation pattern, ready for Phase 4)
  6. `starRating` — fieldset + radios + keyboard arrows + screen reader announcements (ready for Phase 5 reviews)
  7. `listViewToggle` — `aria-pressed`, persists per session via sessionStorage (ready for Phase 3 board view)
  8. `prefersReducedMotion` — toggles a class on `<html>` so CSS can react to motion preference
- **D-13:** Bundle exposes a thin `window.TicketTrade` global namespace for programmatic use: `toast.show(message, type)`, `toast.dismiss(id)`, `setTheme(mode)`, `getTheme()`. Most components stay data-attribute driven; the toast needs a programmatic API because the server renders flash messages into a `<div data-flash-toast="...">` on every page load (Phase 2+).

### the agent's Discretion
None — every area was explicitly decided.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 1.**

### Visual identity
- `DESIGN.md` — Brand & style, color palette (NSBM green, trust amber, info blue), six tier colors, semantic colors, surface tokens (light + dark), corkboard tokens, contrast ledger (all load-bearing combinations AA-pass), typography (Inter display, system-ui body, mono-code), layout & spacing, elevation, shapes, components. The contrast ledger is the source of truth for every token value.

### Experience spine
- `EXPERIENCE.md` — Information architecture (student + admin surfaces, navigation model, modal stack), voice and tone, component patterns with behavior, state patterns per surface (empty, cold, error, focused, offline), interaction primitives, accessibility floor (WCAG AA, keyboard, screen reader, reflow, reduce-motion, tap targets, ARIA mappings), banned interactions (anti-patterns).

### Architecture
- `ARCHITECTURE-SPINE.md` — AD-1 (layered modular monolith), AD-3 (webroot at `public/`, `src/` outside), AD-4 (hand-rolled route list, no regex routing), AD-13 (session/CSRF/rate-limit shape), AD-16 (failure envelope), AD-17 (operational envelope). Phase 1 establishes the directory structure that AD-1 and AD-3 require, but auth/routing primitives land in Phase 2.

### Requirements
- `.planning/REQUIREMENTS.md` — UX-01 (toast system), UX-02 (skeleton loading), UX-03 (empty/error states), UX-04 (design token system, all colors and tokens), UX-05 (typography), UX-06 (theme persistence), UX-07 (WCAG AA floor), UX-08 (keyboard nav floor), UX-09 (skeleton surface list), UX-10 (skip link). These are the FRs Phase 1 implements.

### Roadmap
- `.planning/ROADMAP.md` — Phase 1 entry: success criteria, 2 plans, MVP mode. Plan 01-01 covers the token system + theme + a11y floor; Plan 01-02 covers the toast/bottom nav/skeleton shells + the three mockups.

### Project context
- `AGENTS.md` — Operating manual for the project (team structure, how to read this codebase, command conventions).
- `.planning/PROJECT.md` — Tech stack, constraints, success metrics, key decisions (e.g., 6-tier rank system, single-tenant cohort, simulated payments, velocity cap thresholds).

### Mockup references (visual targets)
- `public/mockups/board-mobile.html` (to be authored in Plan 01-02) — Corkboard board view, dark mode, 4 listings, 2-column grid, list-view toggle visible. This is the most-viewed surface and the canonical visual reference for the corkboard pattern (FR-LND-008).
- `public/mockups/my-tickets.html` (to be authored in Plan 01-02) — My Tickets surface, 2 cards (active service with progress, active product with masked code), WhatsApp CTA, dispute action.
- `public/mockups/admin-dashboard.html` (to be authored in Plan 01-02) — Admin Dashboard, light mode, KPI grid, queue quick-links, velocity-flag table, audit log panel, bulk action bar.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
None — the project is greenfield. The Phase 1 output is the foundation that all later phases reuse.

### Established Patterns
- **Layered modular monolith** (AD-1): Bootstrap → FrontController → Action → Service → Model → PDO. Phase 1 establishes the public/ webroot structure (AD-3) but does not introduce the FrontController — that ships in Phase 2.
- **Tokens-as-contracts**: The contrast ledger in DESIGN.md is a load-bearing artifact. Every var in `tickettrade.tokens.css` traces to a row in the ledger. Adding a new role = adding a ledger row = adding a var.
- **Self-registering JS components**: The `data-*` attribute convention in `tickettrade.js` lets Views add behavior by adding markup, not by adding `<script>` blocks. This is the pattern every later component follows.

### Integration Points
- **Layout template** (introduced in Phase 2, but the convention starts here): `<head>` includes the inline FOUC-guard script, the Bootstrap CDN bundle, the `tickettrade.css` bundle, and the `tickettrade.js` bundle (defer). `<body>` includes the bottom nav (data-component="bottom-nav") and the toast container (data-component="toast"). Phase 1 mockups can include the same `<head>` block to be visually identical to the eventual production surface.
- **Bootstrap CDN**: Loaded once in the layout. Bootstrap's modal JS, dropdown JS, etc. handle their own behavior. `tickettrade.bootstrap-overrides.css` re-skins the result.
- **Inter font**: Loaded via Google Fonts CDN (per DESIGN.md typography section) in the layout `<head>`. Phase 1 mockups include the same `<link>`.

</code_context>

<specifics>
## Specific Ideas

- The inline FOUC-guard script is small enough to inline (~200 bytes). It reads `localStorage.getItem('tickettrade.theme')`, falls back to `matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'` for the system default, and writes `<html data-theme="...">`. The student/admin surface distinction is set by a `data-surface` attribute on `<html>` written by the layout template.
- The `tickettrade.tokens.css` file should be organized with section comment headers matching the DESIGN.md sections: `/* Colors — Brand */`, `/* Colors — Semantic */`, `/* Colors — Status */`, `/* Colors — Tier */`, `/* Colors — Surface */`, `/* Typography */`, `/* Spacing */`, `/* Shape */`, `/* Elevation */`, `/* Motion */`. This makes the file grep-friendly.
- The `tickettrade.bootstrap-overrides.css` file only sets `--bs-*` variables. It does not write any other CSS rules. Example entries: `--bs-primary: var(--color-primary)`, `--bs-body-bg: var(--color-surface)`, `--bs-border-radius: var(--shape-md)`, `--bs-link-color: var(--color-tertiary)`, `--bs-border-color: var(--color-outline-variant)`.
- The three mockups are the verification harness for success criteria #5 and #7. They open in a browser without a PHP server, link the same CSS bundle the production app uses, and demonstrate the contrast/responsive/shimmer/empty-state behavior.
- The `tickettrade.js` bundle's `prefersReducedMotion` component adds a class to `<html>` rather than reading the media query in CSS directly. This means the CSS uses `.reduce-motion .legend-glow { animation: none; }` patterns, which are easier to test and override than `@media` queries inside individual rules.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. The following items were considered and intentionally deferred to later phases:
- **Empty/error state named copy (UX-DR-34)** — The list of named copy strings for every list surface is in EXPERIENCE.md already. Phase 1 does not need to author them; they ship with each surface in Phase 2 (My Tickets, My Listings) and Phase 3 (Board, Sales, Profile).
- **axe-core in test plan** — Contrast verification is by ledger today. Adding automated axe-core scans to the test plan is a good idea but not Phase 1 scope. Can land with the test infrastructure in Phase 2 or Phase 9.
- **Layout template + FrontController** — The layout convention starts here (the `<head>` block, the bottom nav, the toast container), but the actual layout template and FrontController dispatch ship in Phase 2 with auth.
- **Settings page** — `/settings` is the entry point for theme toggle UI; it ships in Phase 2 alongside auth.

</deferred>

---

*Phase: 1-UX Foundation & Design System*
*Context gathered: 2026-08-30*
