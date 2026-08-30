---
name: TicketTrade
description: Campus-only peer-to-peer marketplace for NSBM Green University students. Verified student identity, 6-tier rank ladder, simulated ticket-based trust.
status: final
updated: 2026-08-27
colors:
  # ─── Brand: NSBM Green University identity ───
  primary: '#1B5E20'                 # NSBM green, all primary CTAs, rank tier C, verified badge
  primary-dark: '#2E7D32'            # hover/active
  primary-light: '#4CAF50'           # decorative only
  on-primary: '#FFFFFF'              # text on primary fill
  primary-container: '#C8E6C9'       # soft fill for selected chips, light-mode info background
  on-primary-container: '#1B5E20'    # text on primary-container

  # ─── Trust amber: tickets, points, monetized states ───
  secondary: '#F57F17'               # ticket code badge, point deltas, earned-amount highlight
  secondary-dark: '#F9A825'          # hover
  secondary-light: '#FFB300'         # decorative
  on-secondary: '#1A1A1A'            # dark text on amber fill (AA-pass)
  secondary-container: '#FFF8E1'     # soft fill behind point callouts
  on-secondary-container: '#4E342E'

  # ─── Info blue: links, info toasts, non-action chrome ───
  tertiary: '#0277BD'
  tertiary-dark: '#0288D1'
  tertiary-light: '#03A9F4'
  on-tertiary: '#FFFFFF'
  tertiary-container: '#E1F5FE'
  on-tertiary-container: '#01579B'

  # ─── Rank tier (6-tier anime-style: E Recruit → S Legend) ───
  rank-e: '#9E9E9E'                  # Recruit: gray shield
  rank-e-dark: '#BDBDBD'
  rank-d: '#2196F3'                  # Rookie: blue shield
  rank-d-dark: '#64B5F6'
  rank-c: '#2E7D32'                  # Operative: green shield (= primary)
  rank-c-dark: '#4CAF50'
  rank-b: '#F9A825'                  # Specialist: gold shield
  rank-b-dark: '#FFEA00'
  rank-a: '#EF6C00'                  # Elite: orange shield
  rank-a-dark: '#FF9800'
  rank-s: '#C62828'                  # Legend: red crown with animated glow
  rank-s-dark: '#E53935'
  rank-s-deep: '#B71C1C'

  # ─── Brand text/UI accents on dark surfaces (AA-safe; fills stay primary/tertiary) ───
  primary-on-dark: '#81C784'
  tertiary-on-dark: '#4FC3F7'

  # ─── Third-party brand color ───
  whatsapp: '#25D366'                # WhatsApp share button

  # ─── Semantic (deliberately mode-invariant; each is self-contained, white text on fill, AA in both modes) ───
  success: '#2E7D32'                 # success toast, approved status
  success-light: '#4CAF50'
  error: '#C62828'                   # error toast, rejected status
  error-light: '#EF5350'
  error-dark: '#B71C1C'              # danger-button hover
  warning: '#B45309'                 # warning toast, disputed status
  info: '#0277BD'                    # info toast, in-progress status

  # ─── Status role fills (badges, role chips) ───
  status-pending-fill: '#FFF8E1'     # amber-50
  status-pending-text: '#4E342E'
  status-active-fill: '#C8E6C9'      # green-50
  status-active-text: '#1B5E20'
  status-rejected-fill: '#FFCDD2'    # red-50
  status-rejected-text: '#B71C1C'
  status-redeemed-fill: '#E1F5FE'    # blue-50
  status-redeemed-text: '#01579B'
  status-expired-fill: '#EEEEEE'     # gray-200
  status-expired-text: '#616161'
  status-sold-fill: '#FFE0B2'        # orange-50
  status-sold-text: '#BF360C'        # darkened for AA on status-sold-fill (was #E65100, 2.99:1; now 5.7:1)
  status-disputed-fill: '#FFE0B2'    # dispute pending
  status-disputed-text: '#BF360C'    # darkened for AA on status-disputed-fill (was #E65100, 2.99:1; now 5.7:1)
  status-removed-fill: '#37474F'     # removed (terminal)
  status-removed-text: '#ECEFF1'

  # ─── Neutral surfaces — light mode (admin default) ───
  surface-base: '#FAFAFA'
  surface-raised: '#FFFFFF'
  surface-container: '#F5F5F5'
  surface-container-low: '#EEEEEE'
  surface-container-high: '#E0E0E0'
  surface-container-highest: '#D1D1D1'
  on-surface: '#1C1C1C'
  on-surface-variant: '#49454F'
  outline: '#79747E'
  outline-variant: '#CAC4D0'
  border-hairline: '#E0E0E0'
  shadow-color: '#000000'

  # ─── Neutral surfaces — dark mode (student default) ───
  surface-base-dark: '#121212'
  surface-raised-dark: '#1E1E1E'
  surface-container-dark: '#2C2C2C'
  surface-container-low-dark: '#1A1A1A'
  surface-container-high-dark: '#383838'
  surface-container-highest-dark: '#444444'
  on-surface-dark: '#ECECEC'
  on-surface-variant-dark: '#CFCBC4'
  outline-dark: '#938F99'
  outline-variant-dark: '#49454F'
  border-hairline-dark: '#2A2A2A'
  shadow-color-dark: '#000000'

  # ─── Corkboard (FR-LND-008 decorative surfaces) ───
  cork-base: '#C8A878'               # corkboard texture tint
  cork-grain: '#A88456'              # grain overlay
  pin-red: '#C62828'                 # pushpin red
  pin-blue: '#1565C0'                # pushpin blue (alternates by listing id hash)

  # ─── Code surface (ticket codes, redemption inputs) ───
  code-bg: '#1E1E1E'                 # monospace ticket-code background
  code-bg-dark: '#0A0A0A'
  code-text: '#FFD600'               # amber monospace text, AA on code-bg
  code-text-dark: '#FFEA00'
  code-border: '#F57F17'             # amber hairline around code block

  # ─── Velocity / freeze warning fill ───
  velocity-flag-fill: '#FFCDD2'
  velocity-flag-text: '#B71C1C'

typography:
  display-lg:
    fontFamily: 'Inter, system-ui, sans-serif'
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: '-0.01em'
  headline-md:
    fontFamily: 'Inter, system-ui, sans-serif'
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  title-sm:
    fontFamily: 'Inter, system-ui, sans-serif'
    fontSize: 18px
    fontWeight: '600'
    lineHeight: '1.4'
  body-md:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.45'
  caption:
    fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'
    fontSize: 12px
    fontWeight: '500'
    lineHeight: '1.35'
    letterSpacing: '0.02em'
  mono-code:
    fontFamily: 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace'
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.4'
    letterSpacing: '0.04em'

rounded:
  sm: 4px         # inputs, small chips
  md: 8px         # cards, buttons, listing card
  lg: 12px        # modals, large surfaces
  xl: 16px        # hero surfaces, success modals
  full: 9999px    # rank pills, status badges

spacing:
  '1': 4px
  '2': 8px
  '3': 12px
  '4': 16px
  '5': 24px
  '6': 32px
  '8': 48px
  '10': 64px
  gutter-mobile: 16px
  gutter-desktop: 32px
  section-gap: 24px
  card-gap: 16px

components:
  # ─── Core structural components ───
  button-primary:
    background: '{colors.primary}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
    padding: '{spacing.2} {spacing.4}'
    fontWeight: '600'
  button-primary-hover:
    background: '{colors.primary-dark}'
  button-danger:
    background: '{colors.error}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  button-danger-hover:
    background: '{colors.error-dark}'
  button-secondary:
    background: 'transparent'
    foreground: '{colors.primary}'
    border: '1px solid {colors.primary}'
    radius: '{rounded.md}'
  button-ghost:
    background: 'transparent'
    foreground: '{colors.on-surface}'
    radius: '{rounded.md}'
  input-field:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    border: '1px solid {colors.outline-variant}'
    radius: '{rounded.sm}'
    padding: '{spacing.2} {spacing.3}'
    fontSize: '{typography.body-md.fontSize}'
  input-field-dark:
    background: '{colors.surface-container-dark}'
    foreground: '{colors.on-surface-dark}'
    border: '1px solid {colors.outline-variant-dark}'
  input-field-error:
    border: '1px solid {colors.error}'
  card-surface:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.md}'
    padding: '{spacing.4}'
    border: '1px solid {colors.border-hairline}'
  card-surface-dark:
    background: '{colors.surface-raised-dark}'
    foreground: '{colors.on-surface-dark}'
    border: '1px solid {colors.border-hairline-dark}'
  modal-dialog:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.lg}'
    padding: '{spacing.5}'
    maxWidth: '600px'
  modal-dialog-dark:
    background: '{colors.surface-raised-dark}'
    foreground: '{colors.on-surface-dark}'
  bottom-nav:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    border-top: '1px solid {colors.border-hairline}'
    height: '64px'
  bottom-nav-dark:
    background: '{colors.surface-raised-dark}'
    foreground: '{colors.on-surface-dark}'
    border-top: '1px solid {colors.border-hairline-dark}'

  # ─── Brand-specific components ───
  rank-badge:
    radius: '{rounded.full}'
    padding: '{spacing.1} {spacing.2}'
    fontSize: '{typography.caption.fontSize}'
    fontWeight: '700'
    letterSpacing: '0.04em'
  rank-badge-e:
    background: '{colors.rank-e}'
    foreground: '{colors.on-primary}'
  rank-badge-d:
    background: '{colors.rank-d}'
    foreground: '{colors.on-primary}'
  rank-badge-c:
    background: '{colors.rank-c}'
    foreground: '{colors.on-primary}'
  rank-badge-b:
    background: '{colors.rank-b}'
    foreground: '#1A1A1A'              # dark text on gold (AA)
  rank-badge-a:
    background: '{colors.rank-a}'
    foreground: '{colors.on-primary}'
  rank-badge-s:
    background: '{colors.rank-s}'
    foreground: '{colors.on-primary}'
    animation: 'legend-glow 2.4s ease-in-out infinite'
  ticket-code-block:
    background: '{colors.code-bg}'
    foreground: '{colors.code-text}'
    border: '1px solid {colors.code-border}'
    radius: '{rounded.sm}'
    padding: '{spacing.2} {spacing.3}'
    fontFamily: '{typography.mono-code.fontFamily}'
    fontSize: '{typography.mono-code.fontSize}'
    letterSpacing: '{typography.mono-code.letterSpacing}'
  status-badge:
    radius: '{rounded.full}'
    padding: '{spacing.1} {spacing.2}'
    fontSize: '{typography.caption.fontSize}'
    fontWeight: '600'
  status-pending:
    background: '{colors.status-pending-fill}'
    foreground: '{colors.status-pending-text}'
  status-active:
    background: '{colors.status-active-fill}'
    foreground: '{colors.status-active-text}'
  status-rejected:
    background: '{colors.status-rejected-fill}'
    foreground: '{colors.status-rejected-text}'
  status-redeemed:
    background: '{colors.status-redeemed-fill}'
    foreground: '{colors.status-redeemed-text}'
  status-expired:
    background: '{colors.status-expired-fill}'
    foreground: '{colors.status-expired-text}'
  status-sold:
    background: '{colors.status-sold-fill}'
    foreground: '{colors.status-sold-text}'
  status-disputed:
    background: '{colors.status-disputed-fill}'
    foreground: '{colors.status-disputed-text}'
  status-removed:
    background: '{colors.status-removed-fill}'
    foreground: '{colors.status-removed-text}'
  whatsapp-button:
    background: '{colors.whatsapp}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  listing-card:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.md}'
    border: '1px solid {colors.border-hairline}'
    image-aspect: '4:3'
    hover-transform: 'translateY(-4px)'
    hover-shadow: '0 4px 12px rgba({colors.shadow-color}, 0.12)'
  listing-card-cork:
    background: '#FFF8E7'             # paper-card surface on cork
    border: '1px solid {colors.outline-variant}'
    rotation: '±2deg deterministic by listing_id'
    pinned-with: 'pin-{colors.pin-red} or pin-{colors.pin-blue}'
  leaderboard-row:
    background: '{colors.surface-container}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.sm}'
    padding: '{spacing.2} {spacing.3}'
    rank-number-color: '{colors.secondary}'
  point-delta:
    color: '{colors.secondary}'
    fontWeight: '600'
    sign: 'prefix with + or −'
  toast-success:
    background: '{colors.success}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  toast-error:
    background: '{colors.error}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  toast-warning:
    background: '{colors.warning}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  toast-info:
    background: '{colors.info}'
    foreground: '{colors.on-primary}'
    radius: '{rounded.md}'
  verified-student-badge:
    background: '{colors.primary-container}'
    foreground: '{colors.on-primary-container}'
    icon: 'inline SVG checkmark'
    radius: '{rounded.full}'
  velocity-flag-badge:
    background: '{colors.velocity-flag-fill}'
    foreground: '{colors.velocity-flag-text}'
    radius: '{rounded.sm}'
    label: 'Velocity flag'
  on-break-pill:
    background: '{colors.surface-container-high}'
    foreground: '{colors.on-surface-variant}'
    radius: '{rounded.full}'
    label: 'On Break'
  admin-reauth-dialog:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.lg}'
    border: '2px solid {colors.error}'    # destructive action signal
  avatar-picker:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.full}'              # circular avatars
    grid: '4x3 = 12 predefined illustrations'
    selected-ring: '2px solid {colors.primary}'
  bulk-action-bar:
    background: '{colors.surface-container-high}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.md}'
    padding: '{spacing.2} {spacing.4}'
    sticky: 'top of table on desktop, bottom on mobile'
  report-modal:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.lg}'
    border: '1px solid {colors.warning}'
    maxWidth: '500px'
  filter-tab:
    background: 'transparent'
    foreground: '{colors.on-surface-variant}'
    active-background: '{colors.primary-container}'
    active-foreground: '{colors.on-primary-container}'
    radius: '{rounded.full}'
    padding: '{spacing.1} {spacing.3}'
  search-input:
    background: '{colors.surface-container}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.full}'
    border: '1px solid {colors.outline-variant}'
    padding: '{spacing.2} {spacing.4}'
    icon-leading: 'magnifier'
  list-view-toggle:
    background: '{colors.surface-container}'
    foreground: '{colors.on-surface-variant}'
    active-foreground: '{colors.primary}'
    radius: '{rounded.sm}'
    pressed: 'aria-pressed=true reveals corkboard'
  analytics-card:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.md}'
    border: '1px solid {colors.border-hairline}'
    padding: '{spacing.4}'
    kpi-color: '{colors.primary}'
  report-row:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.sm}'
    border: '1px solid {colors.border-hairline}'
    padding: '{spacing.2} {spacing.3}'
    dispute-badge: 'dispute-status-overlay'
  audit-log-row:
    background: '{colors.surface-container}'
    foreground: '{colors.on-surface}'
    radius: '{rounded.sm}'
    fontFamily: '{typography.mono-code.fontFamily}'
    padding: '{spacing.2} {spacing.3}'
    hash-cell-color: '{colors.code-text}'
    hash-cell-bg: '{colors.code-bg}'
  tier-progress:
    background: '{colors.surface-container}'
    foreground: '{colors.on-surface-variant}'
    fill: '{colors.primary}'
    fill-rank: 'current tier color'
    track-height: '8px'
    radius: '{rounded.full}'
  kpi-counter:
    foreground: '{colors.primary}'
    fontSize: '{typography.display-lg.fontSize}'
    fontWeight: '700'
  tier-privilege-tooltip:
    background: '{colors.surface-raised}'
    foreground: '{colors.on-surface}'
    border: '1px solid {colors.outline-variant}'
    radius: '{rounded.md}'
    padding: '{spacing.3}'
    maxWidth: '280px'
---

## Brand & Style

TicketTrade is a campus-only student marketplace that solves one problem: trading between strangers in a place where everyone already shares an identity. The product is built on three convictions that drive every visual decision: NSBM Green University's verified student email is the trust root, the simulated ticket code is the proof of every trade, and a 6-tier rank ladder turns repeat trade into a visible reputation. The aesthetic follows.

The product reads *real, not playful*. Dark mode is the default for student surfaces because most late-night browsing happens on phones under poor light and the corkboard board view (FR-LND-008) is more legible against deep surfaces. Student surfaces are dense, type-led, and use color with intent — a single amber for ticket codes and point deltas, a single green for verified identity, and rank colors that follow the tier ladder. Admin surfaces default to light mode because admins work in daytime conditions and process many list items in a sitting.

The product is not gamified-in-your-face. The rank badge appears in a list of trust signals (verified, rating, points, dispute count) and never speaks first. There are no streaks shown to the user — the PRD-stored 7-day and 30-day streak bonuses exist as anti-farming mechanics, not as visible counters. The 6-tier ladder (E Recruit → S Legend) is anime-flavored in name but conservative in presentation: tier S gets a single subtle glow animation, the rest are flat pills.

The product never shows a number without context. "Sold 12" without the unit is forbidden. "Verified Student" is the only allowed status language; emoji is not used in functional UI. Trust signals are listed together so the reader can weigh them — verified, rank, rating + count, dispute count on profile.

**Inheritance rule:** This `DESIGN.md` and `EXPERIENCE.md` win on conflict with any mock, wireframe, or import. Visual references land in `imports/` (prior runs and user-supplied visuals), `mockups/` (key-screen HTML), and `wireframes/` (Excalidraw). The spines own the load-bearing decisions; references illustrate.

---

## Colors

The palette is built in three layers. Layer 1 is the brand foundation (NSBM green primary, trust amber secondary, info blue tertiary). Layer 2 is semantic (success / error / warning / info) plus status roles (pending / active / rejected / redeemed / expired / sold / disputed / removed). Layer 3 is surface tokens (light + dark mode for both student and admin surfaces). The cork texture color is a single decorative tint that never carries meaning.

**Primary green (`#1B5E20` light, `#2E7D32` dark hover).** Used for primary CTAs (Buy Now, Confirm, Create Listing, Submit), the verified-student check, active-nav state, focus rings, and the rank-C (Operative) tier badge. The same green appears as the 6-tier ladder's middle step — students reach it at 150 points. It is the brand identity, not a decoration.

**Trust amber (`#F57F17` light, `#F9A825` dark hover).** Used exclusively for the ticket-code block, point-delta text (+30, +10), the rank-B (Specialist) badge, and the secondary CTA on the listing modal. Amber means "money / proof / earned." It is not used for warnings or errors.

**Info blue (`#0277BD` light, `#0288D1` dark hover).** Used for informational chips, links, non-action chrome, and the rank-D (Rookie) badge. Blue never carries a warning or error load.

**Rank tier palette (E→S).** Six discrete colors following the ladder: gray (E Recruit, neutral baseline), blue (D Rookie), green (C Operative, identical to primary), gold (B Specialist), orange (A Elite), red (S Legend, with subtle pulse animation only on S). The 6-tier sequence is a deliberate anime-coded progression that NSBM students recognize; tier-S glow is the single motion element allowed on rank badges.

**Semantic (success / error / warning / info).** Deliberately mode-invariant: each is a self-contained pair (white text on saturated fill) and passes WCAG AA in both light and dark mode. The Contrast Ledger (below) lists all load-bearing combinations. Status roles (pending / active / rejected / redeemed / expired / sold / disputed / removed) live in dedicated tokens with paired fill + text so they read as badges against any surface.

**Surface tokens (light + dark).** The light tokens are the admin default; the dark tokens are the student default. Both share the same structural roles (base / raised / container / outline / on-surface) so a component built from tokens works in both modes. Mode switching is a localStorage preference, not a system override; a `prefers-color-scheme` fallback is respected on first visit.

**Corkboard decoration (FR-LND-008).** `cork-base` and `cork-grain` are decorative tints used only on the board view cork texture. The `pin-red` and `pin-blue` push-pin graphics are purely decorative. None of these tokens are exposed to assistive tech; the list-view toggle and below-md fallback provide a plain-grid version with identical content and order.

**Velocity and inactivity badges.** `velocity-flag-fill` / `velocity-flag-text` mark users earning >300 pts/day or >150 pts/hour (admin-only visible on the user row). `on-break-pill` is a neutral surface-token combo applied to a grayed-out rank badge for users inactive 14+ days.

### Contrast Ledger (load-bearing combinations; all AA-pass)

| Foreground | Background | Ratio | Use |
|---|---|---|---|
| `{colors.on-primary}` (#FFF) | `{colors.primary}` (#1B5E20) | 8.6:1 | primary CTA, verified check, rank C |
| `{colors.on-primary-container}` (#1B5E20) | `{colors.primary-container}` (#C8E6C9) | 7.1:1 | verified chip text |
| `{colors.on-secondary}` (#1A1A1A) | `{colors.secondary}` (#F57F17) | 7.3:1 | ticket code, point delta |
| `{colors.on-tertiary}` (#FFF) | `{colors.tertiary}` (#0277BD) | 6.0:1 | info link, rank D |
| `{colors.on-primary}` (#FFF) | `{colors.rank-b}` (#F9A825) → use dark text | 4.5:1 | rank B Specialist (dark text) |
| `{colors.on-primary}` (#FFF) | `{colors.rank-a}` (#C62828) | 3.5:1 | rank A Elite (large pill, large text AA) |
| `{colors.on-primary}` (#FFF) | `{colors.rank-s}` (#212121) | 5.5:1 | rank S Legend |
| `{colors.on-primary}` (#FFF) | `{colors.success}` (#2E7D32) | 5.5:1 | success toast, approved chip |
| `{colors.on-primary}` (#FFF) | `{colors.error}` (#C62828) | 5.5:1 | error toast, rejected chip |
| `{colors.on-primary}` (#FFF) | `{colors.warning}` (#B45309) | 4.5:1 | warning toast, disputed chip |
| `{colors.on-primary}` (#FFF) | `{colors.info}` (#0277BD) | 6.0:1 | info toast |
| `{colors.code-text}` (#FFD600) | `{colors.code-bg}` (#1E1E1E) | 11.0:1 | ticket-code block (mono amber on near-black) |
| `{colors.on-surface}` (#1C1C1C) | `{colors.surface-raised}` (#FFF) | 16.1:1 | body text on card |
| `{colors.on-surface-dark}` (#ECECEC) | `{colors.surface-raised-dark}` (#1E1E1E) | 14.0:1 | body text on dark card |
| `{colors.on-surface-variant}` (#49454F) | `{colors.surface-raised}` (#FFF) | 8.5:1 | meta text on card |
| `{colors.status-pending-text}` | `{colors.status-pending-fill}` | 11.8:1 | pending badge |
| `{colors.status-active-text}` (`#1B5E20`) | `{colors.status-active-fill}` (`#E8F5E9`) | 7.00:1 | active badge |
| `{colors.status-rejected-text}` | `{colors.status-rejected-fill}` | 5.8:1 | rejected badge |
| `{colors.status-redeemed-text}` (`#01579B`) | `{colors.status-redeemed-fill}` (`#E3F2FD`) | 6.48:1 | redeemed badge |
| `{colors.status-expired-text}` (`#616161`) | `{colors.status-expired-fill}` (`#ECEFF1`) | 5.36:1 | expired badge |
| `{colors.status-sold-text}` (`#BF360C`) | `{colors.status-sold-fill}` (`#EDE7F6`) | 4.63:1 | sold badge |
| `{colors.status-disputed-text}` (`#BF360C`) | `{colors.status-disputed-fill}` (`#FFF3E0`) | 5.11:1 | disputed badge |
| `{colors.status-removed-text}` (`#ECEFF1`) | `{colors.status-removed-fill}` (`#37474F`) | 8.35:1 | removed badge |

**Rank-B uses dark text on gold** (not white) for AA; the white-on-gold combination is 1.9:1 and fails. The rank badge component is configured accordingly in `{components.rank-badge-b}`. The `rank-a` Elite pill passes at 3.5:1 with white text but only because the pill is large (16px+ on a 24px-tall chip with bold weight). For 14px+ use, prefer dark text on `rank-a` too.

**Card text on cork** (FR-LND-008) must meet 4.5:1 against the paper-card surface (`#FFF8E7`), not the cork. The cork is decorative; the paper card is the actual contrast surface. The cork asset is ≤ 100 KB; rotation, pin graphics, and cork texture are aria-hidden.

---

## Typography

The type stack has three jobs. Body text in `system-ui` keeps the product fast (no web-font blocking) and respects platform conventions. Inter is loaded for display and headlines where character shape matters. Monospace is reserved for the ticket-code block and the redemption input — codes are the only thing in the product that must be character-exact.

**Display / headline (Inter).** `display-lg` is 32px/700/-0.01em; used only on the landing hero and major success modals. `headline-md` is 24px/600; used on page titles (My Listings, Profile, Admin Dashboard). `title-sm` is 18px/600; used on card titles and modal titles.

**Body (system stack).** `body-md` is 16px/400 — the default for all content. `body-sm` is 14px/400 — for meta text, helper text, and dense tables. `caption` is 12px/500 with 0.02em letter-spacing — for status badges, timestamps, and table headers.

**Monospace (mono-code).** 14px/600/0.04em letter-spacing. Reserved for the ticket-code block, the redemption code input, the audit-log hash chain, and the points-log `event_uuid` row. The letter-spacing is deliberate — it makes base62 characters easier to scan and copy.

**Type rules.** No all-caps labels. No display sizes inside the body of a page. Exclamation marks only in the landing hero. No emoji in functional UI. Numbers in body text are always paired with units ("150 points", "7 days", "5 sessions"). Rank tier labels (Recruit, Rookie, etc.) are sentence case, not all-caps.

**Platform conventions.** iOS dynamic type and Android font scale are honored — the largest accessibility setting must still render legibly without truncation. The cap is `display-lg` at 32px; on `xxx-large` font scale, that becomes 64px and the landing hero must reflow. Ticket-code blocks do not scale (they must remain character-exact), but the surrounding text does.

---

## Layout & Spacing

Bootstrap 5's grid is the layout system; the spacing scale overlays the Bootstrap utilities. Mobile-first; breakpoints at 576, 768, 992, 1200px.

**Spacing scale.** 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64px. The `gutter-mobile` is 16px, `gutter-desktop` is 32px. The `section-gap` is 24px (the gap between two stacked sections on the same page). The `card-gap` is 16px (the gap between two cards in a grid).

**Grid behavior.** Board view is 1 column <576px, 2 columns 576-767, 3 columns 768-991, 4 columns ≥992. Card grid is fluid within these bounds with 16px gap. My Tickets and Sales use 1 column <768, 2 columns ≥768. Admin tables are full-width at all breakpoints with horizontal scroll on <768.

**Container width.** Main content is `max-width: 1200px` centered. Forms and modals are 600px. Admin tables are full-bleed (max-width: 100% with side padding). Profile pages are 800px. The board view is the only screen that uses the full 1200px width.

**Bottom nav (mobile).** 64px tall, fixed, above the safe area. 5 items at the default scale; on `xs` viewports the labels collapse to icons only. The bottom nav is dark in student mode and light in admin mode; this matches the surface tokens (`{components.bottom-nav}` and `{components.bottom-nav-dark}`).

**Modal layer.** Centered on ≥768 with `{components.modal-dialog}` max-width 600px. Full-screen on <768 (slides up from the bottom). One modal level maximum — the purchase confirmation is the deepest stack, opening on top of the listing modal (which stays mounted but inert). No nested modals beyond this.

---

## Elevation & Depth

Depth is reserved for the few moments of literal physical metaphor — the listing card on hover, the corkboard pin lift, the modal scrim. It is not used as a hierarchy device. Hierarchy comes from layout, type, and color.

**Shadows.** Two layers total. The base elevation (1dp) is a hairline border (`{colors.border-hairline}`) plus a 0.5px-tint shadow on `surface-raised`. The hover elevation (4dp) is used on listing cards (`{components.listing-card}`) and corkboard cards — a 4px translateY plus a 0 4px 12px shadow at 12% opacity. No other element gets a hover elevation.

**Tonal layering.** Surface tokens progress base → raised → container → container-high. Each step adds either luminance (light mode) or a slight desaturation (dark mode). The progression is a quiet wayfinding tool — the deeper the container, the more it asks to be a backdrop, not a foreground surface.

**Modal scrim.** `rgba(0, 0, 0, 0.5)` over the underlying page. Tap-on-scrim closes the modal (default) except during purchase confirmation, where tap-on-scrim is suppressed for 2 seconds to prevent accidental cancellation.

**Decorative depth (corkboard).** The corkboard board view uses a paper-card metaphor: cork texture as the background, paper cards pinned to it, slight rotation for visual variety. Depth here is decorative; it does not affect ranking, order, or interaction. A list-view toggle and a below-md fallback degrade the cork to a plain grid with identical content and order.

**No elevation hierarchy on cards inside a list.** Within a single list (Board, My Listings, My Tickets), every card sits on `surface-raised`. No card is "higher" than another. The first card does not get a special shadow.

---

## Shapes

Corners follow one rule: smaller is sharper, larger is friendlier. The product leans sharper because it is a tool, not a consumer app.

**Sm (4px) — inputs, redemption code field, leaderboard rows, status badges in dense tables.** The sharpest corner the product uses. Inputs feel like input fields, not buttons.

**Md (8px) — cards, buttons, listing card, toast, modal footer actions.** The workhorse corner. The default for anything that is a primary interaction surface or a content container.

**Lg (12px) — modals, large surfaces, profile pages.** Used for surfaces that are larger than a card and need to feel distinct from the surrounding page.

**Xl (16px) — hero surfaces, success modals (ticket created, listing approved).** Used sparingly. The extra roundness signals celebration without using color.

**Full (9999px) — rank pills, status badges, on-break pill, verified-student badge, nav-icon containers.** Pill shape is for labels and chips only. Cards and buttons are never pills.

**Corkboard cards (FR-LND-008).** The paper card uses `{rounded.sm}` (4px) and adds a deterministic rotation of ±2° seeded by listing id. The rotation is decorative; the card content is read top-to-bottom in the rotation frame. The pin graphic is a separate SVG layered above the card.

**Image corners follow container corners.** A listing-card image is `{rounded.md} 0 0 {rounded.md}` (top corners rounded, bottom corners square) so the card's border-radius is preserved against the image.

---

## Components

Behavioral specs for each component live in `EXPERIENCE.md.Component Patterns`. The visual specs below define the anatomy, color usage, sizing, and state appearance for every component the product ships.

### Core structural components

- **Button — primary** (`{components.button-primary}`). `{colors.primary}` fill, `{colors.on-primary}` text, `{rounded.md}`. Hover → `{colors.primary-dark}`. Disabled → `{colors.surface-container-high}` fill, `{colors.on-surface-variant}` text. Loading state: spinner replaces label, button stays the same width. Used for: Buy Now, Confirm, Create Listing, Submit, Save Draft.

- **Button — danger** (`{components.button-danger}`). `{colors.error}` fill, `{colors.on-primary}` text, `{rounded.md}`. Hover → `{colors.error-dark}`. Used for: Remove Listing, Force Expire, Ban User. Always paired with a confirm step.

- **Button — secondary** (`{components.button-secondary}`). Transparent fill, `{colors.primary}` text and border, `{rounded.md}`. Hover → `{colors.primary-container}` fill. Used for: Cancel, Back, optional actions.

- **Button — ghost** (`{components.button-ghost}`). Transparent fill, `{colors.on-surface}` text, `{rounded.md}`. Hover → `{colors.surface-container}`. Used for: tertiary actions in dense lists (edit, delete on a row).

- **Input field** (`{components.input-field}`). `{colors.surface-raised}` fill, `{colors.outline-variant}` border, `{rounded.sm}`. Error state adds `{colors.error}` border and a 12px error message below in `{typography.caption}`. Dark-mode variant swaps the surface + outline + foreground tokens. `autocomplete` mapping follows the accessibility floor in `EXPERIENCE.md` (email, current-password, one-time-code, etc.).

- **Card surface** (`{components.card-surface}`). `{colors.surface-raised}` fill, `{colors.on-surface}` text, `{rounded.md}`, 1px `{colors.border-hairline}` border, `{spacing.4}` padding. The default container for any grouped content. Dark-mode variant swaps the surface + foreground + border tokens.

- **Modal dialog** (`{components.modal-dialog}`). `{colors.surface-raised}` fill, `{rounded.lg}`, `{spacing.5}` padding, `max-width: 600px`. Title in `title-sm` (18px/600). Body in `body-md`. Footer right-aligned with primary + secondary buttons. Full-screen on <768 (slides up from the bottom). Closes on ESC, scrim click (after 2-second guard for purchase), and the X button. Focus is trapped; focus returns to the trigger on close.

- **Bottom nav** (`{components.bottom-nav}`). 64px tall, fixed, 5 items. Active item uses `{colors.primary}` icon + label; inactive uses `{colors.on-surface-variant}`. Hidden on ≥768 (desktop uses top bar + sidebar). The fifth item is always Profile.

### Brand-specific components

- **Rank badge** (`{components.rank-badge}`). Full pill, `{typography.caption}` (12px/700). Six tier variants E→S, each with a fixed fill + foreground pair. Tier S (Legend) has a 2.4-second ease-in-out `legend-glow` animation (subtle pulse on box-shadow only). The badge never carries a numeric points total — that lives in the meta text next to it. On-Break state swaps to `{components.on-break-pill}` (grayed surface + neutral text, full-pill radius) and a tooltip on hover/focus.

- **Ticket-code block** (`{components.ticket-code-block}`). Monospace amber text on near-black surface, 1px amber border, `{rounded.sm}`. Letter-spacing 0.04em. The block is the single most recognizable trust surface in the product. The reveal/mask toggle, copy-to-clipboard button, and WhatsApp-share button sit adjacent on the same row.

- **Status badge** (`{components.status-badge}`). Full pill, `{typography.caption}` (12px/600). One component, eight variants: pending, active, rejected, redeemed, expired, sold, disputed, removed. Each is a paired fill + text token. Statuses are read-only — they are never user-editable.

- **WhatsApp button** (`{components.whatsapp-button}`). `{colors.whatsapp}` fill, `{colors.on-primary}` text, `{rounded.md}`. Disabled state with explanatory tooltip when the seller has not shared a WhatsApp number. Disabled label: "Seller has not shared WhatsApp" with a fallback to copy-to-clipboard only.

- **Listing card** (`{components.listing-card}`). `{rounded.md}`, image at 4:3 aspect ratio, title in `title-sm`, price in `headline-md` with `{colors.secondary}` accent, status badge, rank badge on the seller row. Hover → translateY(-4px) + 0 4px 12px shadow at 12%. Tapping anywhere on the card opens the listing modal. The card has a single tab stop (the entire card is the focusable element, with inner text marked aria-hidden to screen readers to avoid double-reading).

- **Listing card — corkboard** (`{components.listing-card-cork}`). Same content as the standard card but rendered on a paper surface (`#FFF8E7`) with ±2° deterministic rotation, a pushpin graphic (red or blue, alternating by listing id hash), and a hover lift. Used only on the board view; the list-view toggle and the below-md fallback render the standard card.

- **Leaderboard row** (`{components.leaderboard-row}`). `{colors.surface-container}` fill, `{rounded.sm}`, `{spacing.2}` vertical + `{spacing.3}` horizontal padding. Rank number in `{colors.secondary}` at `headline-md`. Display name in `body-md`, program/year in `body-sm` (`{colors.on-surface-variant}`). Tier badge sits right-aligned.

- **Point delta** (`{components.point-delta}`). Always prefixed with `+` or `−`, `{colors.secondary}` text, 600 weight. Used in profile summaries, leaderboard rows, and the points-log detail modal.

- **Toast** (success / error / warning / info). Four components (`{components.toast-*}`), each a fixed-fill pill with white text. Sits bottom-right on desktop, top on mobile. Auto-dismiss 4s; error and warning toasts include a manual dismiss button. ARIA live region announces the toast. Auto-dismiss pauses on hover/focus.

- **Verified Student badge** (`{components.verified-student-badge}`). `{colors.primary-container}` fill, `{colors.on-primary-container}` text, inline-SVG checkmark icon, `{rounded.full}`. Renders on profile, listing cards, and the listing modal. Never clickable; it is a status display, not a CTA.

- **Velocity flag badge** (`{components.velocity-flag-badge}`). `{colors.velocity-flag-fill}` fill, `{colors.velocity-flag-text}` text, `{rounded.sm}`. Renders on the admin Users list next to any user earning >300 pts/day or >150 pts/hour. Clickable — opens the user detail with the flag log. Tooltip: "Earning above legitimate ceiling — review queued."

- **On-Break pill** (`{components.on-break-pill}`). Grayscale surface + neutral text, full-pill radius. Applied to the rank badge in place of the normal tier color when the user has been inactive 14+ days. Tooltip: "Inactive 14+ days — next action restores full badge."

- **Admin re-auth dialog** (`{components.admin-reauth-dialog}`). Modal with `{colors.error}` 2px border signaling destructive action. Single password field, primary Confirm button + secondary Cancel. Triggered before ban, promote, delete, and bulk actions. Failure shows inline error; success closes the dialog and proceeds with the action.

- **Avatar picker** (`{components.avatar-picker}`). Grid of 12 predefined illustrations (4 columns × 3 rows on desktop, 3 × 4 on mobile). Each cell is a circular `{rounded.full}` thumbnail on `{colors.surface-raised}`. The selected avatar carries a 2px `{colors.primary}` ring. No upload, no custom images — the asset set is shipped as inline SVG.

- **Filter tab** (`{components.filter-tab}`). Pill-style tab used on Board, My Listings, and admin queues. Transparent background by default; active tab swaps to `{colors.primary-container}` fill with `{colors.on-primary-container}` text. Padding `{spacing.1}` vertical / `{spacing.3}` horizontal. Border is transparent by default and the active variant uses the same color as the fill (no border). The tab order follows the PRD state machine (Active / Pending / Sold / Draft on My Listings; Pending / Active / Rejected on Admin Listings Queue). Keyboard arrow keys cycle. `aria-current="page"` on the active tab.

- **Search input** (`{components.search-input}`). Pill-shaped input with leading magnifier icon. `{colors.surface-container}` fill, `{colors.outline-variant}` border, `{rounded.full}` radius. Padding `{spacing.2}` vertical / `{spacing.4}` horizontal. Debounced 250ms. Used on Board (FULLTEXT search), Admin Users (LIKE), Admin Reports (LIKE), Admin Audit Log (LIKE). Pressing `/` focuses the board search from any surface.

- **List view toggle** (`{components.list-view-toggle}`). Two-state toggle in the Board header. `{colors.surface-container}` background with two buttons: Cork (default, `aria-pressed="true"`) and List. The pressed state uses `{colors.primary}` text. State persists per session via `aria-pressed`. Below the md breakpoint, the toggle hides and the corkboard auto-degrades to the plain grid.

- **Bulk action bar** (`{components.bulk-action-bar}`). Sticky bar that slides in when 1+ rows are selected in an admin table (or My Listings). `{colors.surface-container-high}` fill, `{rounded.md}` radius. Sticks to the top of the table on desktop and to the bottom on mobile. Shows the count + a dropdown of bulk actions (ban, suspend, promote, approve, reject, remove, dismiss, delete, relist, export). Destructive actions trigger the admin re-auth dialog.

- **Analytics card** (`{components.analytics-card}`). One card per KPI on the Admin Dashboard. `{colors.surface-raised}` fill, `{colors.border-hairline}` 1px border, `{rounded.md}` radius, `{spacing.4}` padding. KPI value in `display-lg` with `{colors.primary}` text. Subtitle in `body-sm` with `{colors.on-surface-variant}` text. Trend line below in `success` or `error` color. Click opens the analytics detail with the chart.

- **Report row** (`{components.report-row}`). Row in the Admin Reports queue. `{colors.surface-raised}` fill, `{colors.border-hairline}` 1px border, `{rounded.sm}` radius, `{spacing.2}` / `{spacing.3}` padding. Cells: thumbnail of the target, target title, reporter nickname + tier, reason, age, status. A dispute overlay badge replaces the status pill when the report is a dispute. Row click expands the evidence detail view inline. Bulk-select checkbox sits on the left.

- **Audit log row** (`{components.audit-log-row}`). Row in the Admin Audit Log. `{colors.surface-container}` fill, `{rounded.sm}` radius, `mono-code` font. Cells: timestamp, actor, action, target, old/new values (collapsed by default), and a hash cell with `{colors.code-bg}` background and `{colors.code-text}` amber text. Filters: date range, actor, action, target. Hash chain integrity check runs on every page load; mismatch shows a red banner.

- **Tier progress bar** (`{components.tier-progress}`). Horizontal bar on the Profile page. Track is `{colors.surface-container}` fill, `{rounded.full}` radius, 8px tall. Fill uses the current tier color (e.g., `{colors.rank-d}` for Rookie). Tooltip on hover/focus: "X of Y toward {next tier name}". Below the bar, a small caption shows the next tier name and threshold.

- **KPI counter** (`{components.kpi-counter}`). A single large number rendered in `display-lg` (32px/700) with `{colors.primary}` text. Subtitle in `caption` (12px/500) with `{colors.on-surface-variant}` color. Used on the Profile stats line and the Admin Dashboard analytics cards. The value updates in place when data refreshes (no animation; just the new number).

- **Tier privilege tooltip** (`{components.tier-privilege-tooltip}`). Popover that appears on hover/focus of the rank badge on the Profile page. `{colors.surface-raised}` fill, `{colors.outline-variant}` border, `{rounded.md}` radius, `{spacing.3}` padding, `max-width: 280px`. Lists what the current tier unlocks (e.g., C+: up to 5 active listings; B+: search rank boost; A+: featured listings; S: Hall of Fame + early access). Progressive disclosure — never a separate page.

- **Star rating input**. Fieldset of 5 named radio inputs (1–5 stars). Each radio is hidden; the visible label is a 24px star icon. Hover and focus preview the rating; keyboard arrow keys cycle. Screen reader announces "Rating: 3 of 5". A "Clear" link resets to 0 (no rating). Used in the review compose modal.

- **Dispute modal**. Same as modal dialog with the destructive-action border color. Reason dropdown (4 options + other), text field (required, 200-char max), optional evidence image upload (one image, 5MB max). Footer: secondary Cancel + danger Submit Dispute.

- **Report modal**. Same as modal dialog. Reason dropdown (scam, inappropriate, spam, wrong_category, other), text field (required, 200-char max). Footer: secondary Cancel + primary Submit Report. Submits a report with `status='pending'` and a toast confirms.

- **Purchase confirmation modal**. Same as modal dialog. Body: "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." Footer: secondary Cancel + primary Confirm. Scrim click is suppressed for 2 seconds to prevent accidental cancel.

---

## Do's and Don'ts

### Do

- Use a single token for every color decision. Reach for `{colors.primary}` before reaching for a hex value.
- Pair every status with both a fill and a text token (e.g., `{colors.status-pending-fill}` + `{colors.status-pending-text}`). Never use the fill alone as a background for text.
- Use monospace `mono-code` for ticket codes, redemption inputs, and the points-log event_uuid. The letter-spacing (0.04em) is part of the spec.
- Show rank badges alongside (not above) verified check, rating, and dispute count. Trust signals are weighed together; the rank is one of several.
- Use system-ui for body text. Inter only for display and headlines.
- Keep card text against the paper-card surface, not the cork. Cork is decoration; the card is the contrast surface (4.5:1+).
- Show error and warning toasts with a manual dismiss button. Auto-dismiss alone is not enough.
- Truncate long nicknames with an ellipsis at 16 chars; full nickname is in the title attribute.
- Use the `bottom-nav` tokens for the mobile nav and the same surface tokens for the desktop top bar.
- Default to dark mode on student surfaces, light on admin. The choice persists in localStorage; system preference is the first-visit fallback.

### Don't

- Don't add a new color outside the token set. If a need arises, add the token to the frontmatter first, then reference it.
- Don't use red error fills on form inputs — border + inline text only. Error containers for inline messages only.
- Don't use emoji in functional UI. Rank icons are SVG. Status badges are text + color.
- Don't use display sizes for body content. `display-lg` only on the landing hero and major success modals.
- Don't use the tier-color tokens (E/D/C/B/A/S) for anything except rank badges. Tier colors are rank-only.
- Don't use the corkboard rotation to imply ranking. Rotation is seeded by id and is purely decorative.
- Don't use the `rank-a` Elite pill with white text at 14px or smaller — AA needs the dark text variant below that size.
- Don't use the `rank-b` Specialist pill with white text ever — it fails AA in both modes. Use the dark text variant.
- Don't use streak counters or daily-login-bonus displays. The PRD-stored streak bonuses are anti-farming mechanics, not user-facing surfaces.
- Don't use push notifications or re-engagement nags. Student surfaces are pull-only.
- Don't add a tier color or rank badge to the admin chrome. The admin sees the user's tier; the admin's own chrome stays neutral.
- Don't use `whatsapp` for anything except the WhatsApp share button. It is a third-party brand color.
- Don't expose corkboard rotation, pin graphics, or cork texture to assistive tech (aria-hidden).
