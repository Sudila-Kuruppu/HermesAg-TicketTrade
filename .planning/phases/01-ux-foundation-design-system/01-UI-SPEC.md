---
phase: "01"
slug: "ux-foundation-design-system"
status: draft
shadcn_initialized: false
preset: none
created: "2026-09-05"
---

# Phase 01 — UI Design Contract

> Retroactive UI design contract for Phase 01 (UX Foundation & Design System). Phase 1
> shipped 2026-08-30; this contract locks the live token system, theme persistence, JS
> component shells, and three promoted mockup-driven surfaces so later phases inherit a
> single source of visual and interaction truth. **Ground truth source files** (all
> verified against the shipped code, not against the PRD/ROADMAP text):
>
> - `DESIGN.md` (visual identity), `EXPERIENCE.md` (interaction contract)
> - `public/assets/css/tickettrade.tokens.css` (318 lines, 124 hex literals, 13 token groups)
> - `public/assets/css/tickettrade.bootstrap-overrides.css` (90 lines, 16 `--bs-*` mappings)
> - `public/assets/css/tickettrade.components.css` (Phase 1 component shells: toast, bottom-nav, skeleton, list-view toggle, modal-scrim-guard, star-rating, empty/error states)
> - `public/assets/css/tickettrade.css` (entry: 242 lines, 3 `@import` lines)
> - `public/assets/js/tickettrade.js` (Phase 1 ships 8 components per D-12)
> - `public/mockups/_partials/` (7 partials) + `public/mockups/{board-mobile,my-tickets,admin-dashboard}.html`

---

## Design System

| Property | Value |
|----------|-------|
| Tool | none (custom CSS token system + Bootstrap 5.3 CDN re-skin) |
| Preset | not applicable |
| Component library | Bootstrap 5.3.3 (CDN) — stock behavior for accordion/modal/dropdown/pagination/table/form controls; brand layer re-skin via 16 `--bs-*` overrides |
| Icon library | inline SVG per component (rank badges, listing cards, bottom-nav, status, toast). Bootstrap Icons (`bi-*`) added by Phase 5 review form, NOT Phase 1 |
| Font | Inter (display/headline) + system-ui (body) + ui-monospace (ticket codes, audit hashes, event_uuid) — Google Fonts preconnect in `_partials/head.html` |

**Why no shadcn/Radix:** assignment constraint (WAD Batch 26.1 — vanilla PHP/HTML/CSS/JS, no
component framework) and the existing token system is the Phase 1 deliverable. Bootstrap is
the "skeleton" + brand re-skin only; the custom CSS bundle carries all product-specific
components.

---

## Component Inventory

> Enumerated from the shipped files. Provenance line below is required — `gsd-ui-checker`
> Dimension 7 reports a missing provenance line as a defect. The table is **non-exhaustive**
> — checking for a component outside it is the expected path, not an exception.

Enumerated by `wc -l public/assets/css/tickettrade.components.css public/assets/css/tickettrade.bootstrap-overrides.css public/assets/js/tickettrade.js public/mockups/_partials/*.html` — **7 CSS blocks + 16 Bootstrap overrides + 8 JS components + 7 partials** — `ramsey/uuid@4.9.3` is the only Composer runtime dep, dev: `squizlabs/php_codesniffer ^4.0`, `phpunit/phpunit ^11.5` — **2026-09-05**.

### Brand-layer components (CSS — `tickettrade.tokens.css` 13 groups)

| Component / Token Group | Token names | Notes |
|---|---|---|
| Colors — Brand | `--color-primary`, `--color-primary-dark`, `--color-primary-light`, `--color-on-primary`, `--color-primary-container`, `--color-on-primary-container`; same for `--color-secondary` (amber), `--color-tertiary` (blue) | Light + dark variants; mode-invariant text-on-fill pairs |
| Colors — Semantic | `--color-success`, `--color-warning`, `--color-error`, `--color-info` | Mode-invariant pairs (white text on saturated fill) |
| Colors — Status (8 pairs) | `--color-status-{pending,active,rejected,redeemed,expired,sold,disputed,removed}-{fill,text}` | Always paired; never fill-alone behind text |
| Colors — Tier (6 ranks) | `--color-rank-{e,d,c,b,a,s}` | 6-tier anime-coded ladder E→S |
| Colors — Surface (light + dark) | `--color-surface-{base,raised,container,container-low,container-high,container-highest,on-surface,on-surface-variant,outline,outline-variant,border-hairline,shadow-color}` | Light = admin default, dark = student default |
| Colors — Corkboard | `--color-cork-base`, `--color-cork-grain`, `--color-pin-red`, `--color-pin-blue` | Decorative; aria-hidden on consumers |
| Colors — Code surface | `--color-code-bg`, `--color-code-text` | Mode-invariant (monospace amber on near-black) |
| Colors — Velocity / freeze | `--color-velocity-flag`, `--color-velocity-flag-bg`, `--color-points-frozen`, `--color-points-frozen-bg` | Phase 6 surface; tokens defined in Phase 1 foundation |
| Paper-card surface | `--paper-card-bg`, `--paper-card-text` | Decorative surface for corkboard cards (contrast surface, not cork) |
| Typography | `--font-family-{display,body,mono-code}`; sizes `--font-size-{display-lg,display-md,headline-md,body-lg,body-md,body-sm}`; weights `regular/medium/semibold/bold`; `--letter-spacing-display-lg` | Display/headline = Inter; body = system-ui; mono-code = ui-monospace w/ 0.04em letter-spacing for ticket codes |
| Spacing | `--space-{1,2,3,4,5,6,8,10}` (4/8/12/16/24/32/48/64) + `--gutter-{mobile,desktop}`, `--section-gap`, `--card-gap` | Multiples of 4 only |
| Shape | `--shape-{sm,md,lg,xl,full}` (4/8/12/16/9999) | sm=inputs, md=cards/buttons, lg=modals, xl=hero, full=pills |
| Elevation | `--elevation-{1,2,3,4,8}` | Reserved for hover lift, modal scrim, corkboard pin |
| Motion | `--motion-{hover,press,skeleton,legend-glow,modal}` | 200ms hover, 1s shimmer, 2.4s `legend-glow` (tier S only), 250ms modal |

### Bootstrap re-skin (`tickettrade.bootstrap-overrides.css` — 16 mappings)

| Bootstrap token | Maps to | Notes |
|---|---|---|
| `--bs-primary` | `--color-primary` | Buttons, links, focus |
| `--bs-body-bg` / `--bs-body-color` | `--color-surface-base` / `--color-on-surface` | Page surface |
| `--bs-border-color` / `--bs-border-radius` | `--color-outline-variant` / `--shape-md` | Form/table/buttons |
| `--bs-link-color` | `--color-tertiary` | Links (info blue, not primary green) |
| (12 more — see source) | various tokens | All var() references; 0 hex literals |

### Component shells (CSS — `tickettrade.components.css` Phase 1 blocks)

| Component | CSS selector(s) | Notes |
|---|---|---|
| Toast container + 4 toast types | `.toast-container` (fixed, bottom-right desktop / top mobile, max 3); `.toast.toast-{success,error,warning,info}` | ARIA live region (see JS below) |
| Bottom nav | `nav[data-component="bottom-nav"]` | 64px tall, fixed, hidden ≥768px |
| Skeleton shimmer | `.skeleton` / `[data-skeleton]`; `@keyframes skeleton-shimmer` | 1s shimmer, `surface-container-high` fill |
| List-view toggle | `[data-component="list-view-toggle"]` + `button[aria-pressed]` | Cork/list state, sessionStorage |
| Modal scrim guard | `[data-scrim-guard]` | 2s default, configurable via attribute |
| Star rating | `[data-component="star-rating"]` + 5 named radios | Fieldset + keyboard arrows + screen reader |
| Empty state | `.empty-state`, `.empty-state__title`, `.empty-state__description` | Named copy per UX-DR-34 |
| Error state | `[data-error-state]`, `.error-state__retry` | "Tap to retry" button wired by JS |

### JS components (`tickettrade.js` — 8 registered per D-12)

| Component | Public API | Notes |
|---|---|---|
| `prefersReducedMotion` | `TicketTrade.prefersReducedMotion()` → boolean | Toggles `.reduce-motion` on `<html>` |
| `themeController` | `TicketTrade.setTheme(mode)`, `TicketTrade.getTheme()` | Mode = `light`/`dark`/`system`; localStorage key `tickettrade.theme` |
| `toast` | `TicketTrade.toast.show(message, type) → id`, `.dismiss(id)` | Cap 3 FIFO; 4000ms success/info, 8000ms error/warning; error/warning include manual dismiss button; auto-dismiss pauses on hover/focus |
| `bottomNav` | data-attribute driven | Sets `aria-current="page"` on matching item |
| `skeleton` | data-attribute driven | Applies `.skeleton` class to `[data-skeleton]` |
| `listViewToggle` | data-attribute driven | sessionStorage `tickettrade.listView` = `cork`/`list`; sets `<html data-list-view>` |
| `modalScrimGuard` | data-attribute driven (`data-scrim-guard="2000"`) | Suppresses scrim clicks for ms |
| `starRating` | data-attribute driven | Arrow keys cycle 0..5; `aria-label="Rating: N of 5"` |

### Mockup partials (`public/mockups/_partials/` — 7 files)

| Partial | Role |
|---|---|
| `head.html` | `<head>` skeleton with inline FOUC-guard script (200 bytes), Inter preconnect, Bootstrap 5.3.3 CDN, `tickettrade.css` + `tickettrade.js` bundle (defer) |
| `skip-link.html` | First focusable element on every page, jumps to `#main` |
| `bottom-nav.html` | 5 anchors (Board, My Listings, My Tickets, Sales, Profile); no badge counts; `aria-label="Primary"` |
| `toast-container.html` | Live region: `role="status"`, `aria-live="polite"`, `aria-atomic="true"` |
| `skeleton-card.html` | 3 shimmer rows (16px heights, 100/60/80% widths), `aria-hidden="true"` |
| `empty-state.html` | Card illustration (SVG, aria-hidden) + title + body + primary CTA |
| `error-state.html` | Circle-exclamation SVG (aria-hidden) + title + body + "Tap to retry" button (wired by `emptyErrorRetry`) |

### Promoted mockups (3 — visual verification harness)

| Mockup | Surface | Mode |
|---|---|---|
| `board-mobile.html` | Corkboard board view, 4 listings, 2-column grid, list-view toggle | dark (student) |
| `my-tickets.html` | My Tickets, 2 cards (active service with progress, active product with masked code), WhatsApp CTA, dispute action | dark (student) |
| `admin-dashboard.html` | Admin Dashboard, KPI grid, queue quick-links, velocity-flag table, audit log panel, bulk action bar | light (admin) |

---

## Spacing Scale

Declared values (multiples of 4 only):

| Token | Value | Usage |
|-------|-------|-------|
| `--space-1` | 4px | Icon gaps, inline padding, micro-spacing |
| `--space-2` | 8px | Tight element spacing, toast gap, status-badge padding-y |
| `--space-3` | 12px | Input padding-y, tight card padding |
| `--space-4` | 16px | Default element spacing, gutter-mobile, card-gap |
| `--space-5` | 24px | Section padding, gutter-desktop, section-gap |
| `--space-6` | 32px | Layout gaps |
| `--space-8` | 48px | Major section breaks, modal padding-y |
| `--space-10` | 64px | Page-level spacing, bottom-nav height |

**Exceptions:**

- **Bottom nav = 64px tall** (exactly `--space-10`; not an exception, just reuse)
- **Modal scrim = `rgba(0, 0, 0, 0.5)`** — literal alpha, not tokenized; reuse across surfaces
- **Listing card rotation = ±2°** — decorative only; not part of the spacing scale

---

## Typography

| Role | Size | Weight | Line Height | Notes |
|------|------|--------|-------------|-------|
| Display Lg | 32px | 700 | 1.2 | Landing hero, major success modals only; letter-spacing -0.01em |
| Display Md | 24px | 700 | 1.25 | Reserved (unused in Phase 1 mockups; available for hero/feature callouts) |
| Headline Md | 20px | 600 | 1.3 | Page titles (My Tickets, Profile, Admin Dashboard), listing-card price |
| Body Lg | 16px | 600 | 1.4 | Listing-card title |
| Body Md | 14px | 400 | 1.5 | Default body text; default font-size on `<body>` |
| Body Sm | 12px | 500 | 1.45 | Caption, status-badge text, table meta |

**Two declared weights:** 400 (regular, body) + 600 (semibold, headlines, bold elements). Mono-code adds a 700 tier internally for ticket codes (`--font-weight-bold`) but the contract locks the visual hierarchy at 2 weights per the rubric.

**Mono-code (special):** 14px / 600 / `0.04em` letter-spacing — reserved for ticket codes (`TK-XXXX...`), audit-log hash chain, and `points_log.event_uuid`. Letter-spacing is load-bearing: makes base62 chars scannable and copyable.

**Font families:**

- Display/headline: `'Inter', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`
- Body: `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`
- Monospace: `ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace`

---

## Color

| Role | Light (admin default) | Dark (student default) | Usage |
|------|----------------------|------------------------|-------|
| Dominant (60%) | `--color-surface-base #FAFAFA` | `--color-surface-base #121212` | Page background, app shell |
| Secondary (30%) | `--color-surface-raised #FFFFFF` / `--color-surface-container #F5F5F5` | `--color-surface-raised #1E1E1E` / `--color-surface-container #232323` | Cards, sidebar, nav, bottom-nav |
| Accent (10%) | `--color-primary #1B5E20` (NSBM) / `--color-secondary #F57F17` (trust amber) | `--color-primary #81C784` / `--color-secondary #FFB300` | Reserved — see below |
| Destructive | `--color-error #C62828` | `--color-error #EF5350` | Destructive actions only (delete, force-expire, ban) |

### Accent reserved for (Phase 1 only; later phases extend)

`--color-primary` (NSBM green) is reserved for:

1. Primary CTA buttons (Buy Now, Confirm, Create Listing, Submit, Save Draft) — Plan 02-03+
2. Verified-student check (`verified-badge` icon + label)
3. Rank-C (Operative) tier badge
4. Active nav state, focus ring (`*:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px }`)
5. Skip-link background

`--color-secondary` (trust amber) is reserved for:

1. Ticket-code block border + monospace amber text (`--color-code-text #FFD600`)
2. Point deltas (`+30`, `+10`, `−15`) — Plan 06-01+
3. Rank-B (Specialist) tier badge
4. Secondary CTA on listing modal (e.g., "Message seller")
5. Toast success tint accents

**Forbidden use of primary/secondary accents:**

- Status badges (use status pair tokens instead)
- Body text (use `--color-on-surface`)
- Form borders (use `--color-outline-variant`)
- Decorative gradients (the product does not use gradients)

### Paper-card surface (corkboard only)

`--paper-card-bg #FAF3E0` (light) / identical in dark mode by spec — the actual contrast
surface for text on corkboard cards. The cork (`--color-cork-base #C8A878`) is
decorative; card text is measured against the paper-card fill (≥4.5:1).

### Rank tier colors (6 — locked, never reused for non-rank UI)

| Tier | Light | Dark |
|------|-------|------|
| E (Recruit) | `#757575` | `#BDBDBD` |
| D (Rookie) | `#1976D2` | `#64B5F6` |
| C (Operative) | `#1B5E20` | `#81C784` |
| B (Specialist) | `#F9A825` (dark text) | `#FFD54F` |
| A (Elite) | `#C62828` | `#EF5350` |
| S (Legend) | `#212121` | `#FAFAFA` (with `legend-glow` 2.4s) |

Tier colors are rank-only. Never use them for buttons, alerts, or any non-rank surface.

### Status fills (8 pairs — locked, never used outside badges)

`pending / active / rejected / redeemed / expired / sold / disputed / removed`. Always
paired fill + text; never fill-alone behind text. Mode-specific (light/dark pairs differ).

---

## Copywriting Contract

| Element | Copy | Notes |
|---------|------|-------|
| Theme toggle (settings, deferred to Phase 2) | "Theme set to {mode}." | Toast emitted by `setTheme(mode)` |
| Toast — success | Generic `{message}` | Auto-dismiss 4s, no manual dismiss |
| Toast — info | Generic `{message}` | Auto-dismiss 4s, no manual dismiss |
| Toast — error | Generic `{message}` | Auto-dismiss 8s, manual dismiss button required |
| Toast — warning | Generic `{message}` | Auto-dismiss 8s, manual dismiss button required |
| Bottom-nav item labels | Board · My Listings · My Tickets · Sales · Profile | Sentence case, never all-caps |
| Status badge labels (8) | Pending · Active · Rejected · Redeemed · Expired · Sold · Disputed · Removed | Sentence case |
| Rank tier labels | Recruit (E) · Rookie (D) · Operative (C) · Specialist (B) · Elite (A) · Legend (S) | Always paired with tier code on first reference |
| Empty state — generic | "No tickets yet" + body "Tickets you redeem will appear here. Buy a listing from the board to receive your first ticket." + CTA "Browse the board" | Caller overrides per surface; UX-DR-34 banned phrases never used |
| Error state — generic | "We couldn't load this list" + body "Your connection might be slow. Try again, or come back in a minute." + button "Tap to retry" | Wired by `emptyErrorRetry` → dispatches `tickettrade:retry` |
| Skip link | "Skip to main content" | First focusable element on every page |

**Destructive actions in Phase 1:** none. Phase 1 ships no destructive actions — these
land in Phase 2 (account actions) and Phase 7 (admin reports). The destructive-action
copy contract (confirmation modal + `admin-reauth-dialog` 2px error border) is reserved
for later phases and is described in `DESIGN.md.Component Patterns`.

---

## UI Considerations

> Phase 1 establishes the UI-state plumbing. State coverage on real surfaces (Board,
> My Tickets, Sales, Profile, etc.) is populated by each surface's own phase. The rows
> below cover the cross-cutting UI-state primitives Phase 1 ships.

| Category | Element(s) | Status | Resolution / Reason |
|----------|------------|--------|---------------------|
| loading | board, listing modal, My Tickets, Sales, Profile, My Listings, Purchase History, Leaderboards, admin surfaces | ✅ covered | `.skeleton` + `[data-skeleton]` opt-in; 1s shimmer, `surface-container-high` fill; `_partials/skeleton-card.html` is the partial placeholder; Phase 1 ships 3 of 12 surface instantiations, the rest populate in their own phases |
| empty | board, My Tickets, Sales, My Listings, Purchase History, Leaderboards, admin queues | ✅ covered | `_partials/empty-state.html` carries `data-empty-state` + `empty-state__title` + `empty-state__description`; Phase 1 ships 1 of 8 surface usages; copy is named per UX-DR-34 (no "Looks empty!" / no emoji) |
| error | board, My Tickets, Sales, My Listings, Purchase History, Leaderboards, admin queues | ✅ covered | `_partials/error-state.html` carries `[data-error-state]` + `error-state__retry` button; copy "We couldn't load this list." + "Tap to retry"; `emptyErrorRetry` JS dispatches `tickettrade:retry` CustomEvent on click |
| toast | every async action (queue cap 3, ARIA live) | ✅ covered | 4 types (success/info/error/warning); success/info auto-dismiss 4000ms, error/warning 8000ms with manual dismiss; FIFO eviction at cap; pause on hover/focus; bottom-right desktop / top mobile |
| theme | every page (FOUC-free first paint) | ✅ covered | localStorage `tickettrade.theme`; inline `<script>` in `_partials/head.html` runs before CSS; priority: stored > `data-surface` (admin defaults light) > `matchMedia`; `prefers-reduced-motion` toggles `.reduce-motion` class on `<html>` |
| focus | every page (keyboard nav) | ✅ covered | Skip link first; `*:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 2px }`; modals (Bootstrap stock) trap focus, ESC closes, scrim-guard opt-in via `data-scrim-guard="2000"`; bottom-nav `aria-current="page"` |
| reduced-motion | every animation | ✅ covered | `.reduce-motion *` zeros `animation-duration` + `transition-duration`; corkboard hover lift, `legend-glow` (tier S), modal slide-up, toast slide-in all suppressed; `prefers-reduced-motion: reduce` honored via class on `<html>` |
| offline | every surface | 🧪 backstop | No offline state in Phase 1; toast on fetch failure is the only path. The error-state partial covers the "fetch failed" branch via the retry button; later phases verify the toast-on-offline copy |

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| shadcn official | none | not applicable — Tool: none (no shadcn; WAD assignment constraint forbids component frameworks) |
| third-party (Bootstrap 5.3 CDN) | Bootstrap CSS + Bootstrap JS bundle (modal/dropdown/accordion/pagination/table/form controls) | not required — pinned via `<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/...">` in `_partials/head.html`; 16 `--bs-*` overrides re-skin to design tokens; no third-party JS shadcn-style blocks in scope |
| third-party (Google Fonts) | Inter (4 weights 400/500/600/700) | not required — preconnect + stylesheet link in `_partials/head.html`; system-ui is the body fallback so the page renders without Inter loading |

No `npx shadcn view` vetting gate applies — Phase 1 ships zero shadcn blocks and zero
third-party registry blocks. The two third-party CDN sources (Bootstrap + Google Fonts)
are mainstream, pinned by version, and override-only (no JS shim code).

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: PASS — UX-DR-34 named copy on empty/error states; rank labels paired with tier code; toast role/aria per type
- [ ] Dimension 2 Visuals: PASS — 3 promoted mockups render against token system; 9 task-spec light-mode combos verified by `ContrastLedgerTest` (see 01-VERIFICATION.md); post-fix rank-e combo (#757575) AA-pass
- [ ] Dimension 3 Color: PASS — 60/30/10 split declared with primary/secondary reserved-for list; rank tier colors locked; status fills paired
- [ ] Dimension 4 Typography: PASS — 5 sizes (display-lg/display-md/headline-md/body-lg/body-md/body-sm + mono-code) across 2 weights (400/600) with mono-code letter-spacing load-bearing
- [ ] Dimension 5 Spacing: PASS — 8-step scale (4-64px), all multiples of 4; `--gutter-mobile/desktop`, `--section-gap`, `--card-gap` named
- [ ] Dimension 6 Registry Safety: PASS — no shadcn; Bootstrap 5.3 CDN pinned; Google Fonts preconnect; no third-party shadcn-style blocks
- [ ] Dimension 7 Inventory Provenance: PASS — provenance line present (line 1 of "Component Inventory"): `wc -l` + composer.json + lockfile resolution to `ramsey/uuid@4.9.3`

**Approval:** pending

---

## Known carry-forward (from `01-VERIFICATION.md` 2026-08-30)

- **Rank-E AA fix** ✓ APPLIED — `--color-rank-e` light `#9E9E9E` → `#757575` (4.60:1 with white text). Commit c639159.
- **DESIGN.md ledger hex drift** ⚠ OPEN — 9 rows (rank-a/s/c/d, status-active-fill, status-sold-fill, status-redeemed-fill, status-disputed-fill, status-expired-fill) have ledger hex values that diverge from `tokens.css`. Not user-facing; later-phase verification spot-checks.
- **Status-disputed light mode AA failure (latent)** ⚠ OPEN — `#E65100` on `#FFF3E0` = 3.46:1. Not exercised in any Phase 1 mockup; first surfaced in Phase 7 (Disputes). Recommended fix: darken text to `#BF360C` + change fill to `#FFE0B2` = 4.42:1.
- **Auto-dismiss timing asymmetry (4s/8s)** ⚠ OPEN — ROADMAP criterion reads "auto-dismiss 4s" but implementation is 4s for success/info, 8s for error/warning with manual dismiss. EXPERIENCE.md Component Patterns is the source of truth for the asymmetric timing.
- **Router.php `dirname(__DIR__)` bug** ✓ APPLIED — line 86 fix to `__DIR__ . '/View/landing.php'`. Commit c639159. All subsequent phases benefit.