# Phase 6: Points, Ranks & Leaderboards - Context

**Gathered:** 2026-09-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 6 lights up the full gamification layer so the marketplace's trust signals become visible to students. The Phase 4/5 stub `points_service` gains its final shape: every point-earning action writes to `points_log` (UUID v7, `UNIQUE uniq_event`) and updates `users.points` + `users.tier` in the same transaction. Velocity, same-pair caps, the new-account 50% multiplier, the On-Break state, and admin void/approve of frozen accounts are all enforced at insert time. Four daily-refreshed leaderboards surface the top users with privacy-preserving nicknames. Concretely:

1. **Points engine completion** — `points_service` adds: `awardListingApproval(int $userId, int $listingId)`, `awardReportValidated(int $userId, int $reportId)`, `awardStreakBonus(int $userId, int $streakDays)`, plus the velocity and pair-cap enforcement layered on top of the existing `awardTransaction()` and `awardReviewPoints()`. The FR-PTS-007 halving is unchanged. `users.points_frozen` is a hard gate on every writer.
2. **6-tier ladder as inline SVG** — `config/ranks.php` already ships the tier table. Phase 6 ADDS the `legend-glow` CSS animation for tier S (2.4s ease-in-out, `prefers-reduced-motion: reduce` honored), the `rank-badge` SVG partial, and the 6 badge classes (E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark).
3. **Tier progress bar on Profile** — horizontal bar showing points within the current tier; track `surface-container` fill; fill uses the current tier color; tooltip "X of Y toward {next tier name}".
4. **Velocity flag (FR-PTS-010)** — `users.points_frozen` flips to TRUE when the user exceeds >300 pts/day OR >150 pts/hr; new awards blocked while frozen; admin void/approve lands in Phase 8 but the Phase 6 code reserves the action surface (a `voidPoints()` and `clearPointsFreeze()` method on `points_service`, callable by Phase 8 admin Actions).
5. **On-Break state (FR-PTS-008)** — `users.last_active_at` is refreshed on every `points_log` write and on every successful login. Profile shows a grayed tier badge + "On Break" pill when 14+ days inactive. Re-activation on the next action restores the full color instantly (no point penalty).
6. **Four leaderboards** — Campus Legends Wall (top 20 tier S), Weekly Risers (top 10 with weekly_points >= 50, Mon-Sun Asia/Colombo), Category Leaders (top 3 per category by successful sales), Streak Kings (top 10 by current login streak). Refreshing happens via the hand-triggered `POST /admin/cron/daily` Action extending the existing cron endpoint.
7. **Hand-triggered daily cron** — `POST /admin/cron/daily` extends `App\Admin\Action\CronAction` with a `runDailySweep()` method: refreshes 4 leaderboard summary tables, updates `last_active_at` flags, recomputes streak counters, caches leaderboards to JSON. The endpoint is admin-only and rate-limited per AD-19.

The phase does NOT add: real-time leaderboard updates (daily refresh is enough for the WAD scope), admin console (Phase 8), moderation queue (Phase 7), audit log hash chain (Phase 8).

</domain>

<decisions>
## Implementation Decisions

### Streak Kings visibility (reconciliation of PRD PTS-009 + EXPERIENCE.md L325)
- **D-01:** The Streak Kings leaderboard IS visible on `/leaderboards` (PTS-009 is a hard requirement and the four-board grid is the surface that ships). EXPERIENCE.md L325's "no visible streak counters" override applies to the **per-user** streak display (no "7-day streak 🔥" chip on the profile, no streak counter in the bottom nav, no encouragement chrome around it). The leaderboard row format follows the EXPERIENCE.md L150 neutral tone: rank number + nickname + program/year + tier badge; no streak-count badge, no "🔥" emoji, no congratulatory framing. The board's empty state is the standard pattern from EXPERIENCE.md L213-214: "No active streaks yet. Streaks build as you log in daily." — descriptive, not promotional. — **Reversibility:** reversible — the leaderboard is a query; hiding it later is a route change.

### Velocity flag scope
- **D-02:** Phase 6 ships the **engine** (the `points_frozen` flag, the velocity check at insert time, the short-circuit response on all writers, and the `voidPoints()` + `clearPointsFreeze()` methods on `points_service` callable by Phase 8 admin). On the **user-facing Profile**, the flag surfaces as a single small pill: "Earning paused — admin review" (UX-DR-16 in EXPERIENCE.md, neutral, non-punitive). The pill links nowhere in Phase 6 (admin Phase 7/8 wires the resolution). NO detailed velocity log, no "you earned X pts/hour" copy, no countdown. The tone is "something is paused, an admin will look" — not "you've been caught gaming". — **Reversibility:** reversible — adding a detailed log later is a View + Model addition; removing the pill is a one-line conditional.

### On-Break detection rule
- **D-03:** A user is "On Break" iff `users.last_active_at < (NOW() - INTERVAL 14 DAY)`. `last_active_at` is a denormalized column on `users`, refreshed by:
  - Every successful `points_log` insert (a write to the gamification table = "active"). A `BEFORE INSERT` trigger on `points_log` does `UPDATE users SET last_active_at = NOW() WHERE user_id = NEW.user_id`. Trigger is the only writer; the column is not updated from PHP.
  - Every successful login (a dedicated `auth_service::recordLogin(int $userId)` method, called from the post-authenticate hook in Phase 2's auth flow).
  Re-activation: the next trigger fire (any of the above) updates the column; the next render of the Profile checks the column and removes the On-Break pill instantly. No point penalty, no reset, no toast on re-activation. Reads of `points_log` and listing views do NOT count as activity (read-only browsing should not "save" you from On-Break). — **Reversibility:** costly — the trigger is migration work and the column is denormalized across all writers; changing the rule means re-deriving the column.

### Weekly Risers + Campus Legends empty handling
- **D-04:** Boards render ONLY the rows that qualify. When 0 users meet the threshold, the standard empty state renders (EXPERIENCE.md L213-214). When fewer than the max qualify (e.g., 3 tier-S users for Campus Legends Wall, 4 users with weekly_points >= 50 for Weekly Risers), the board shows those N rows with no padding — the surface shrinks gracefully. Weekly Risers eligibility check is `weekly_points >= 50` per PTS-009; rows below the threshold are not surfaced at all. Category Leaders (top 3 per category) renders each category as a sub-section with its own empty state when a category has 0 successful sales in the period. — **Reversibility:** reversible — the empty-state copy and the row count are template parameters.

### Leaderboard ordering ties
- **D-05:** Primary sort: descending by score (points / weekly_points / sale_count / streak_days). Tiebreaker: ascending `users.user_id` (stable, deterministic, no recency bias; the first-registered user wins the tie). The rule is documented in the `points_service::computeLeaderboard()` JSDoc-equivalent and is the same across all four boards. Velocity cap ordering follows the same rule. — **Reversibility:** reversible — the ORDER BY clause is one Model method.

### Tier-privilege tooltip scope
- **D-06:** Phase 6 ships the EXPERIENCE.md L143 baseline: tooltip shows "Operative (C) — 150 to 399 points" (full tier name + threshold range). The multi-bullet privilege claim from EXPERIENCE.md L169 ("tier C+ can list up to 5 active listings; tier B+ gets search rank boost; tier A+ gets featured listings; tier S gets Hall of Fame + early access") is **NOT shipped in Phase 6** — those claims describe privilege gating that does not exist in the current PRD scope (no listing quota, no search rank boost, no featured slots, no Hall of Fame surface). Adding the privilege list to the tooltip now would be aspirational copy that promises features the product does not enforce. Defer to v2 (PLAT scope) when privilege gating is actually implemented. — **Reversibility:** reversible — the tooltip is a partial; adding privilege text later is a one-line change.

### Points-log popover on Profile
- **D-07:** Phase 6 ships a simple **inline "Recent activity" section** on `/profile` (last 5 points_log rows: delta + reason + relative date). The popover (hover on the points total, EXP-DR-L150) is deferred to v2 — popover behavior adds JS keyboard/focus plumbing (focus trap, ESC dismissal, focus return to trigger) that is polish for a Phase 7/8 effort, not a Phase 6 MVP concern. The inline section is built from the same data the popover would use; swapping to a popover is a JS + CSS change later. — **Reversibility:** reversible — the data is the same.

### Pair-cap enforcement surface
- **D-08:** FR-PTS-006 ("max 2 counted transactions/day per buyer-seller pair") is enforced by INSERTING the `points_log` row (audit trail preserved) with `metadata.pair_cap_hit = TRUE` and `delta = 0` (no contribution to `users.points`). The row is visible in the recent-activity section ("Daily pair cap reached — counted as 0" is the meta text) so the audit trail is complete. This honors "enforced at insert time" literally and avoids silent drops. `points_log.event_uuid` stays UNIQUE — the cap row gets a fresh UUID v7 like any other. — **Reversibility:** reversible — flipping the behavior to silent drop is a one-line change in the model.

### the agent's Discretion

These items follow from locked requirements or are routine implementation choices appropriate for a WAD-assignment scope:

- **Migrations** — `018_points_log_indexes.sql` ADDS the composite indexes the velocity and pair-cap checks need: `INDEX idx_points_user_event (user_id, event_at DESC)` for the velocity window reads, `INDEX idx_points_pair (user_id, reference_id, event_at DESC)` for the same-pair check. `019_users_last_active.sql` ADDS `users.last_active_at DATETIME NULL` and a `BEFORE INSERT` trigger on `points_log` that refreshes it. `020_leaderboard_summary.sql` creates the four leaderboard summary tables (`leaderboard_campus_legends`, `leaderboard_weekly_risers`, `leaderboard_category_leaders`, `leaderboard_streak_kings`) — each a flat table with `user_id`, `score`, `rank_position`, `metadata_json`, `snapshot_at`. `021_login_streaks.sql` creates `login_streaks(user_id, login_date, streak_count, updated_at, UNIQUE KEY uq_user_date (user_id, login_date))` for the daily-cron streak counter. Per D-23 of Phase 2, migrations continue from the highest existing number — Phase 5 ends at `017_reviews.sql`, so Phase 6 starts at `018_*`.
- **Trigger vs application write for `last_active_at`** — D-03 picks the trigger path so every insert to `points_log` is guaranteed to refresh the column, even if a future Phase 7/8 writer forgets. The trigger lives in `019_users_last_active.sql` migration. Application code (login) writes directly via `auth_service::recordLogin()` since `points_log` is not involved.
- **Velocity window SQL** — the per-user cap is checked inside the points Service before insert: `SELECT COALESCE(SUM(delta), 0) FROM points_log WHERE user_id = ? AND event_at >= NOW() - INTERVAL 1 DAY AND pair_cap_hit = FALSE`. If `current + delta > 300`, the row is inserted with `metadata.velocity_cap_hit = TRUE` and `delta = 0`. The hourly window uses `INTERVAL 1 HOUR`. Both checks happen in the same transaction as the insert (the lock from `FOR UPDATE` on the user row serializes them).
- **Pair-cap SQL** — `SELECT COUNT(*) FROM points_log WHERE user_id IN (?, ?) AND reference_id IN (?, ?) AND event_at >= CURDATE() AND pair_cap_hit = FALSE AND reference_type IN ('final_session', 'transaction')` — counting distinct `reference_id` rows (each ticket is one row pair). When the count is >= 2, the new row is inserted with `metadata.pair_cap_hit = TRUE` and `delta = 0`.
- **Halo on tier S** — CSS keyframes `legend-glow` 2.4s ease-in-out alternate infinite; box-shadow oscillates between `0 0 0 rgba(198,40,40,0)` and `0 0 16px rgba(198,40,40,0.55)`. `prefers-reduced-motion: reduce` swaps the animation to `animation: none` and the badge falls back to the static `bg-dark` fill.
- **Tier progress bar color** — fill uses the current tier's `--color-rank-{tier}` token (already in `DESIGN.md`). The next-tier color is the FILL color when the user is at the top of the current tier (e.g., a user at 399 points in Specialist (B) sees a bar filled with `rank-b` even though the next tier is Elite (A)). Tooltip text: "X of Y toward {next tier name}" where X is `points - currentTierMin` and Y is `nextTierMin - currentTierMin`.
- **Leaderboard row format** — per EXPERIENCE.md L150: rank number in `body-md` (color `secondary`), display name in `body-md` (the user's nickname — never full name, never student_id), program/year in `body-sm` (`on-surface-variant` color), tier badge right-aligned. Category Leaders adds a small "Textbooks" pill left of the name.
- **Daily cron order** — `runDailySweep()` does (1) refresh leaderboard summary tables from `points_log` and `users`, (2) update `last_active_at` flags (mark users with no `points_log` row in 14+ days as `last_active_at = MIN(last_active_at, 14 days ago)` so the On-Break check is honest), (3) recompute streak counters in `login_streaks`, (4) write leaderboards to `var/leaderboards/{board}.json` cache. Idempotent per NFR-REL-002: re-running on the same day produces the same end state.
- **JSON cache for leaderboards** — the four boards cached as `var/leaderboards/{board_slug}.json` (per-board file) so the `/leaderboards` page is a file read + JSON decode, not a multi-table JOIN. The cache is regenerated by the daily cron. If a manual refresh is needed, an admin can `POST /admin/cron/daily` (rate-limited per AD-19). The JSON schema per board: `{generated_at, rows: [{rank, user_id, nickname, program, year, tier, score, metadata}]}`.
- **Streak computation** — `login_streaks` is the authoritative table. `users.current_streak` and `users.longest_streak` are denormalized display copies refreshed by the daily cron. The 7-day and 30-day streak bonuses (FR-PTS-001) write ONE `points_log` row with `reference_type='streak_7day'` or `'streak_30day'` and `delta=+15` or `+50`. Streak Kings leaderboard reads `users.current_streak` (the denormalized copy, refreshed daily).
- **Rate limits** — no new `Support\RateLimit` named limits in Phase 6. The existing `points` named limit (150/day/user per NFR-SEC-007) was reserved for the per-day velocity cap; the actual enforcement is the velocity check itself. Admin `POST /admin/cron/daily` uses the existing `admin_cron` rate limit (5/min/IP).
- **Routes** — Phase 6 ADDS `GET /leaderboards` (auth=false — public; guests see the same data). NO new POST routes (the daily cron is an extension of the existing `POST /admin/cron/ticket-expiry` route per the roadmap's `POST /admin/cron/daily` is renamed/extended). The existing `GET /profile` route gets a new section in the View (Recent activity + tier progress bar + On-Break pill conditional).
- **Error codes** — Phase 6 ADDS: `E_VELOCITY_CAP_HIT`, `E_PAIR_CAP_HIT`, `E_POINTS_FROZEN`, `E_VOID_INSUFFICIENT_BALANCE` (Phase 8 admin), `E_LEADERBOARD_EMPTY`. The 4 cap-hit codes are NOT user-facing errors — they map to `ok=true` with `data.skipped='velocity_cap'|'pair_cap'|'points_frozen'` and a zero-delta row is written for the audit trail. They exist for the rare case where the Service is called from a non-standard caller and needs to surface the skip reason.
- **Audit logging** — every velocity-cap, pair-cap, and freeze event writes an `audit_log` row via `Support\Audit::log()` (the Phase 4 stub): `action='points.velocity_cap' | 'points.pair_cap' | 'points.frozen' | 'points.unfrozen'`, `metadata={user_id, score, window, event_uuid}`. Phase 8 wraps the hash chain.
- **Toast copy on Profile** — none in Phase 6. The Profile is a read surface; no actions trigger toasts. The `Recent activity` section is read-only.
- **Empty states** — `/leaderboards` follows EXPERIENCE.md L213-214 pattern: skeleton shimmer for cold load (10 rows per board × 4 boards), per-board empty state when no rows qualify. `/profile` Recent activity: "No activity yet. Earn your first points by listing an item or completing a transaction." (descriptive, not promotional).
- **Privacy on leaderboards** — nickname + program + year (PTS-009). NEVER student_id digits. NEVER full name. The query is `SELECT user_id, nickname, program, year, tier FROM users JOIN ... WHERE ...` and explicitly does not select `full_name` or `student_id`.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 6.**

### PRD and Topic 4 brief
- `prd.md` §5 Points Earning — Authoritative earning table (profile verification +50, approved listing +5, sale +30, purchase +10, detailed review +10, valid report +20, 7-day streak +15, 30-day streak +50) and the new-account 50% halving rule.
- `prd.md` FR-PTS-001..010 — Normative per-action deltas, tier ladder, badge classes, On-Break, leaderboards, velocity flag.
- `prd.md` §7 Schema — `points_log` table authoritative definition (event_uuid UUID v7 with `UNIQUE KEY uniq_event`, delta signed SMALLINT, reference_type, reference_id, balance_after, metadata JSON, event_at).
- `prd.md` §6 Leaderboards — The four-board grid: Campus Legends Wall (top 20, tier S), Weekly Risers (top 10, min +50/wk), Category Leaders (top 3/category), Streak Kings (top 10). Privacy: nickname + program/year.
- `.planning/WAD-CONTEXT.md` — Topic 4 scope reminder (the gamification layer is one of the "additional innovative features" the brief encourages; the WAD-minimum CRUD is in earlier phases, Phase 6 lights up the trust-signal layer).
- `_bmad-output/planning-artifacts/prds/prd-nsbm-marketplace-2026-08-22/rank-buy-sell-spec.md` — Open-question resolution reference (OQ-005: leaderboard pagination resolved as "no pagination, fixed top 10/20 rows").

### Architecture and ADs
- `ARCHITECTURE-SPINE.md` AD-1..AD-20 — The binding layer rules. Critical for Phase 6:
  - AD-1: Action → Service → Model dependency arrow.
  - AD-2: `Points`, `Leaderboard` (NEW) are bounded contexts. `Points\Service\points_service` is the SOLE writer of `points_log` and `users.points`/`users.tier` (AD-10). `Leaderboard\Service\leaderboard_service` is the SOLE writer of the four `leaderboard_*` summary tables and the JSON cache files.
  - AD-10: `points_service` is the sole writer of `points_log`. Every other context that adjusts points MUST go through this Service. The velocity and pair-cap checks happen at insert time inside this Service.
  - AD-11: `App\Admin\Action\CronAction` is the single owner of (a) ticket expiry, (b) 24h listing auto-approve, (c) 3-day dispute auto-dismiss (Phase 4), (d) daily leaderboard refresh + streak update (Phase 6), (e) audit_reverify (Phase 8). Phase 6 EXTENDS this Action with `runDailySweep()`.
  - AD-12: `audit_log` hash chain — Phase 6 calls `Support\Audit::log()` from `points_service` for every cap-hit / freeze event. The hash chain lands in Phase 8.
  - AD-16: Failure envelope on every Action exit. Phase 6 ADDS the cap-hit codes per D-08.
  - AD-19: Admin re-auth 300s sliding window for `POST /admin/cron/daily`.
  - AD-20: Cohort gate is at S2 retro, not Phase 6.

### Visual identity and experience
- `DESIGN.md` §Rank tier — 6-tier anime-style ladder (E Recruit → S Legend), `legend-glow` animation, badge class per tier (E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark). Tier S has the only animation; rest are flat pills.
- `DESIGN.md` §Components.leaderboard-row — rank number in `secondary`, display name in `body-md`, program/year in `body-sm` (`on-surface-variant`), tier badge right-aligned, empty state per board.
- `DESIGN.md` §Components.tier-progress — 8px tall, `rounded/full`, track `surface-container` fill, fill uses current tier color, tooltip "X of Y toward {next tier name}".
- `DESIGN.md` §Components.on-break-pill — grayed surface + tooltip "Inactive 14+ days — next action restores full badge".
- `DESIGN.md` §Components.velocity-flag — pill "Earning above legitimate ceiling — review queued" (UX-DR-16; Phase 6 ships the gentler "Earning paused — admin review" variant per D-02).
- `EXPERIENCE.md` L143 — Rank badge tooltip "Operative (C) — 150 to 399 points" (full tier name + threshold range).
- `EXPERIENCE.md` L150 — Leaderboard row format: rank number + display name + program/year + tier badge. No encouragement chrome.
- `EXPERIENCE.md` L153 — On-Break pill tooltip "Inactive 14+ days — next action restores full badge".
- `EXPERIENCE.md` L213-214 — Leaderboards cold-load skeleton + per-board empty states.
- `EXPERIENCE.md` L325 — Streaks override ("no visible streak counters per user; data model still records the streak; the override is the visibility policy, not the points rule"). Reconciles with PTS-009 in D-01.

### Existing code
- `config/ranks.php` — Phase 2 ships the 6-tier ladder table. Phase 6 EXTENDS with `legend-glow` CSS (the table is data; the animation lives in `tickettrade.components.css`).
- `src/Points/Service/points_service.php` — Phase 2 ships `awardVerificationBonus()`; Phase 4 ADDS `awardTransaction()`; Phase 5 ADDS `awardReviewPoints()`. Phase 6 ADDS `awardListingApproval()`, `awardReportValidated()`, `awardStreakBonus()`, `voidPoints()`, `clearPointsFreeze()`, plus the velocity and pair-cap enforcement layered into ALL existing writers.
- `src/Points/Model/points_log_model.php` — Phase 2 ships. Phase 6 ADDS `sumForUserInWindow()`, `countPairInDay()`, `recentForUser()`.
- `src/Auth/Service/auth_service.php` — Phase 2 ships. Phase 6 ADDS `recordLogin(int $userId)` (called from the post-authenticate hook). Existing `tierFromPoints()` is reused.
- `src/Admin/Action/CronAction.php` — Phase 4 ships with `runListingAutoApproveSweep()`, `runDisputeAutoDismissSweep()`, `runTicketExpirySweep()`. Phase 6 ADDS `runDailySweep()` (refresh leaderboards, update `last_active_at`, recompute streaks, cache JSON).
- `src/User/View/public_profile.php` — Phase 5 ships with rank badge + review aggregation. Phase 6 ADDS the tier progress bar, On-Break pill, "Earning paused" pill, and Recent activity section.
- `src/User/Model/user_model.php` — Phase 2 ships. Phase 6 ADDS `updateLastActive()`, `findForLeaderboard($criteria)`, `getStreakDisplay()` (read from `login_streaks`).
- `src/User/Service/user_service.php` — Phase 5 ships. Phase 6 EXTENDS with `getRecentActivityForProfile($userId)` (the 5-row `points_log` projection).
- `src/Support/View/partials/{rank_badge,tier_progress,on_break_pill,velocity_flag_pill,leaderboard_row}.php` (NEW) — Phase 6 ADDS these partials (reused across profile, listing modal, leaderboards, ticket cards).
- `public/assets/css/tickettrade.components.css` — Phase 5 ships. Phase 6 ADDS `.legend-glow` keyframes, `.tier-progress`, `.on-break-pill`, `.velocity-flag-pill`, `.leaderboard-row` rules. Colors map to existing `--color-rank-{tier}` tokens.
- `public/assets/js/tickettrade.js` — Phase 5 ships. Phase 6 ADDS a small `tierProgress` component (~20 LOC) for the tooltip hover/focus; no popover yet (D-07). Honors `prefersReducedMotion` (disables `legend-glow`).
- `migrations/001_initial.sql` through `017_reviews.sql` — Phase 5 ships. Phase 6 ADDS `018_points_log_indexes.sql`, `019_users_last_active.sql` (with trigger), `020_leaderboard_summary.sql`, `021_login_streaks.sql`.

### Cross-phase lock-ins
- `.planning/REQUIREMENTS.md` PTS-01..10 — All implemented by Phase 6.
- `.planning/REQUIREMENTS.md` PER-05 — Leaderboard summary-table queries served from indexes over summary tables refreshed daily by cron.
- `.planning/REQUIREMENTS.md` SEC-06 — Rate limits (Phase 6 does NOT add new named limits; the per-day velocity cap is the natural ceiling).
- `.planning/phases/04-purchases-tickets-lifecycle/04-CONTEXT.md` D-06 — Phase 4's `awardTransaction()` is the Phase 6 contract; Phase 6 swaps the implementation without changing callers.
- `.planning/phases/05-reviews-ratings/05-CONTEXT.md` D-05 — `awardReviewPoints()` is the Phase 6 contract; same pattern.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Rank tier table** (`config/ranks.php`) — Phase 2 ships; Phase 6 reuses without modification. `tierFromPoints()` is the canonical resolver.
- **`Support\Audit::log($actorUserId, $action, $targetType, $targetId, $metadata)`** — Phase 4 ships the stub; Phase 6 calls from `points_service` for every cap-hit and freeze event.
- **Design token system** (`public/assets/css/tickettrade.{tokens,bootstrap-overrides,components}.css`) — every color/typography/spacing token Phase 6 needs is already defined: `--color-rank-{tier}` for each tier, `--color-secondary` for rank numbers in leaderboards, `--color-surface-container` for the tier progress track, `--color-on-surface-variant` for program/year meta text.
- **Toast container** (`data-component="toast"` + `window.TicketTrade.toast.show(...)`) — Phase 6 emits toasts for: tier-up (when `users.tier` changes after an award), streak-7-day bonus awarded, streak-30-day bonus awarded. No toasts on the Profile or Leaderboards pages (read-only).
- **Skeleton shimmer** (`data-skeleton`) — Cold-load states on `/leaderboards` (10 rows per board × 4 boards) and on the Profile's Recent activity section (5 rows).
- **`points_log_model::insert(PDO, int $userId, int $delta, string $referenceType, ?int $referenceId, int $balanceAfter, string $eventUuid, ?string $metadataJson): int`** — Phase 2 ships; Phase 6 calls it from every `points_service` writer. The metadata JSON is the channel for the cap-hit flags and the freeze state.
- **`auth_service::tierFromPoints(int $points): string`** — Phase 2 ships; Phase 6 calls it to recompute the tier after every award.
- **`Support\Csrf`, `Support\RateLimit`, `Support\Auth`, `Support\Db`, `Support\View`, `Support\Router`, `Support\ResponseHeaders`** — All Phase 2 ships. Phase 6 imports them from Action files.
- **`User\Model\user_model::findById(int $userId): ?array`** — Phase 2 ships; Phase 6 uses it for the `last_active_at` and `points_frozen` reads.
- **Toast-on-tier-up** — Phase 6 emits a `tier-up` toast when the post-award tier differs from the pre-award tier: "Tier up! You're now {tier_name} ({tier_code})." Fired from `points_service` after the `users.tier` UPDATE. Honored for visible tier transitions only (E→D, D→C, C→B, B→A, A→S); same-tier re-renders do not fire.

### Established Patterns
- **Layered Modular Monolith** (AD-1) — Bootstrap → FrontController → Action → Service → Model → PDO. Phase 6's `Points/Action/*_action.php` files are thin: validate input (CSRF + rate limit + admin re-auth where required), call `Points/Service/points_service.php`, render View. All state mutation goes through the Service.
- **Failure envelope** (AD-16) — Every Action returns `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`. The cap-hit codes from D-08 map to `ok=true` with `data.skipped='velocity_cap'|'pair_cap'|'points_frozen'`; they are not errors.
- **Sole-writer pattern** (AD-2 + AD-10) — `Points/Service/points_service.php` is the sole writer of `points_log` and `users.points`/`users.tier`. `Leaderboard/Service/leaderboard_service.php` (NEW) is the sole writer of the four `leaderboard_*` summary tables. No Action writes to either table.
- **Atomic UPDATE for state mutation** (AD-9) — The `points_service` writers use `SELECT ... FOR UPDATE` on the user row to serialize concurrent awards (per-user race-free). The INSERT into `points_log` + UPDATE on `users.points/tier/last_active_at` happen in the same transaction.
- **Tokens-as-contracts** (Phase 1) — Every color/spacing/typography/elevation token in `tickettrade.tokens.css` traces to a row in `DESIGN.md`'s contrast ledger. Phase 6 inherits this; the rank-tier color tokens already ship from Phase 1.
- **Self-registering JS components** (Phase 1) — `data-component="..."` attributes register behavior. Phase 6 ADDS `data-component="tier-progress"` (~20 LOC) for the hover/focus tooltip.
- **Migrations runner** (Phase 2 D-22..D-28) — Each `.sql` migration runs in a single transaction, `IF NOT EXISTS`/`IF EXISTS` discipline, `.applied` file tracks progress. Phase 6 adds four migration files following the same pattern.
- **Hand-triggered cron** (Phase 3 D-28..D-30) — `POST /admin/cron/ticket-expiry` is the existing endpoint; Phase 6 ADDS `POST /admin/cron/daily` extending `App\Admin\Action\CronAction`. Both endpoints are admin-only with the AD-19 re-auth gate.
- **Idempotent cron** (NFR-REL-002) — Re-running the daily cron on the same day produces the same end state. The `cron_log` table is the audit trail (a row per run with `processed_count` + `errors_json` + `actor_user_id`).
- **JSON file cache** — Phase 6 introduces the pattern of caching read-mostly data to JSON files under `var/leaderboards/`. The cache is regenerated by the daily cron; the View reads the file and JSON-decodes. This is the canonical pattern for "expensive read with a daily refresh" surfaces in this project. Future read-heavy surfaces (e.g., a Phase 7 admin analytics rollup) follow the same pattern.

### Integration Points
- **`config/routes.php`** — Phase 6 ADDS `GET /leaderboards` (auth=false, csrf=false, no rate limit). The existing `POST /admin/cron/ticket-expiry` route stays; ADDS `POST /admin/cron/daily` (auth=true, admin=true, csrf=true, rate_limit='admin_cron', admin_reauth=true).
- **`config/rate_limits.php`** — No new entries; reuses existing `admin_cron` (5/min/IP).
- **`config/contexts.php`** — Phase 6 ADDS `Leaderboard` to the bounded contexts list.
- **`config/bootstrap.php`** — No structural change; PSR-4 autoload picks up `App\Leaderboard\*`.
- **`src/Points/Service/points_service.php`** — Phase 4+5 ship. Phase 6 EXTENDS with the new writers and the velocity/pair-cap enforcement. Existing callers (`ticket_service`, `review_service`, `auth_service::register`) are unchanged.
- **`src/Admin/Action/CronAction.php`** — Phase 4 ships. Phase 6 ADDS `runDailySweep()` method. The endpoint at `POST /admin/cron/daily` routes to this method.
- **`src/Auth/Service/auth_service.php`** — Phase 6 ADDS `recordLogin(int $userId)` method. Called from the post-authenticate hook in the existing login flow.
- **`src/Support/View/partials/rank_badge.php` (NEW)** — Renders the inline SVG tier badge. Reused across profile, listing modal, ticket cards, leaderboards, admin.
- **`src/Support/View/partials/tier_progress.php` (NEW)** — Renders the horizontal tier progress bar with the tooltip. Used on Profile.
- **`src/Support/View/partials/leaderboard_row.php` (NEW)** — Renders a single leaderboard row (rank + name + program/year + tier badge). Used on `/leaderboards`.
- **`src/Support/View/partials/on_break_pill.php` (NEW)** — Renders the On-Break pill with the EXPERIENCE.md L153 tooltip. Used on Profile and leaderboard rows.
- **`src/Support/View/partials/velocity_flag_pill.php` (NEW)** — Renders the "Earning paused — admin review" pill (D-02's gentler variant). Used on Profile.
- **`src/User/View/public_profile.php`** — Phase 5 ships. Phase 6 ADDS the tier progress bar, On-Break pill conditional, velocity flag pill conditional, and Recent activity section.
- **`src/User/View/profile.php` (NEW)** — Phase 6 ADDS the owner-facing `/profile` page (own profile, with edit affordances from Phase 2 + the new Phase 6 sections). The View mirrors `public_profile.php` for the gamification sections and adds an Edit Profile button + the Recent activity section.
- **`src/User/Action/ProfileAction.php` (NEW)** — Phase 6 ADDS the Action for `GET /profile` (the owner view). Mirrors the `PublicProfileAction` shape but with the current user as the target.
- **`src/Leaderboard/Service/leaderboard_service.php` (NEW)** — `computeAndCache(): array` runs the four board queries and writes the JSON cache; `getCached($board): array` reads the JSON cache for the View.
- **`src/Leaderboard/Model/leaderboard_model.php` (NEW)** — `refreshCampusLegends()`, `refreshWeeklyRisers()`, `refreshCategoryLeaders()`, `refreshStreakKings()` (each a single SQL UPSERT into the summary table).
- **`src/Leaderboard/View/leaderboards.php` (NEW)** — The four-board grid.
- **`src/Leaderboard/Action/LeaderboardAction.php` (NEW)** — `handleGet()` for `GET /leaderboards`.
- **`src/Listing/View/listing_modal.php`** — Phase 5 ships the rank badge in the seller info row. Phase 6 ADDS the On-Break pill next to it when the seller is inactive.
- **`src/Ticket/View/{my_tickets,sales}.php`** — Phase 4 ships the rank badge. Phase 6 ADDS the On-Break pill.
- **`migrations/018_points_log_indexes.sql` through `021_login_streaks.sql`** — Phase 6 ships.

</code_context>

<specifics>
## Specific Ideas

- The `legend-glow` keyframes are subtle: 2.4s ease-in-out alternate infinite, `box-shadow: 0 0 16px rgba(198,40,40,0.55)` at peak, transparent at trough. The badge stays full-red at all times; the glow is a halo. Under `prefers-reduced-motion: reduce`, the animation is replaced with a static `box-shadow: 0 0 12px rgba(198,40,40,0.35)` so the tier is still visually distinguished but does not animate.
- The tier progress bar's tooltip is `data-bs-toggle="tooltip"` (Bootstrap 5's stock tooltip) with the format "X of Y toward {next tier name}" — e.g., "60 of 100 toward Operative (C)". When the user is at the top tier (S, ≥1500), the bar renders full with tooltip "Top tier reached".
- The Velocity cap insert flow inside `points_service::awardTransaction()`:
  1. Lock both user rows (`SELECT ... FOR UPDATE`)
  2. Compute effective delta per FR-PTS-007 (halving)
  3. SELECT `COALESCE(SUM(delta), 0) FROM points_log WHERE user_id = ? AND event_at >= NOW() - INTERVAL 1 DAY AND pair_cap_hit = FALSE` — call this `day_total`
  4. SELECT `COALESCE(SUM(delta), 0) FROM points_log WHERE user_id = ? AND event_at >= NOW() - INTERVAL 1 HOUR AND pair_cap_hit = FALSE` — call this `hour_total`
  5. If `day_total + effective > 300` OR `hour_total + effective > 150`:
     - `INSERT points_log` with `delta = 0`, `metadata.velocity_cap_hit = TRUE, effective_delta = $effective, day_total_before = $day_total, hour_total_before = $hour_total`
     - If `users.points_frozen = FALSE`: `UPDATE users SET points_frozen = TRUE, frozen_at = NOW() WHERE user_id = ?` (new behavior — set the flag at first velocity-cap hit, not on second)
     - Return `{ok: true, data: {skipped: 'velocity_cap', event_uuid: ...}}`
  6. Else: continue with the normal award path (D-08's pair-cap check, then INSERT)
- The Pair-cap insert flow (after velocity cap passes):
  1. `SELECT COUNT(DISTINCT reference_id) FROM points_log WHERE user_id IN (?, ?) AND reference_id IN (?, ?) AND event_at >= CURDATE() AND pair_cap_hit = FALSE AND reference_type IN ('final_session', 'transaction')` — count of distinct tickets in this pair today, counted for points
  2. If `count >= 2`:
     - `INSERT points_log` with `delta = 0`, `metadata.pair_cap_hit = TRUE, effective_delta = $effective, pair_count_today = $count`
     - Return `{ok: true, data: {skipped: 'pair_cap', event_uuid: ...}}`
  3. Else: continue with the normal award path
- The `voidPoints()` method (Phase 8 caller, Phase 6 ships the method):
  ```php
  public static function voidPoints(int $userId, int $delta, string $reason): array
  {
      // Reads users.points FOR UPDATE
      // Computes new balance: max(0, points - delta)
      // INSERT points_log with negative delta = -(points - new_balance), event_uuid fresh
      // UPDATE users SET points = new_balance, tier = tierFromPoints(new_balance)
      // Returns {ok: true, data: {voided: $delta, balance_after: $new_balance}}
      // Phase 8 admin Action calls this; Phase 6 ships the method as a no-op caller (no UI yet)
  }
  ```
- The `clearPointsFreeze()` method (Phase 8 caller, Phase 6 ships the method):
  ```php
  public static function clearPointsFreeze(int $userId): array
  {
      // UPDATE users SET points_frozen = FALSE, frozen_at = NULL WHERE user_id = ?
      // Returns {ok: true, data: {unfrozen_user_id: $userId}}
  }
  ```
- The 4 leaderboards render as a CSS grid on desktop (2x2) and stacked cards on mobile. Each card has the board name + skeleton shimmer on cold load + the row list (or empty state). No pagination control (top 10/20 are fixed per EXPERIENCE.md L149 + OQ-005).
- The daily cron order in `runDailySweep()`:
  1. `leaderboard_service::refreshAll()` — populates the four summary tables from `points_log` + `users` + `login_streaks` + `tickets`
  2. `user_service::refreshLastActiveFlags()` — for users with no `points_log` row in 14+ days, sets `last_active_at = (NOW() - INTERVAL 14 DAY)` so the On-Break check is consistent (without this, the check would use the last actual write time which might be 30 days ago — that user IS On-Break, but the column is honest; the refresh is for users whose last write was more than 14 days but whose `last_active_at` is fresher because the daily cron has been running). Wait — that's not right. The daily cron doesn't need to refresh `last_active_at` for On-Break detection; the column is updated by the trigger + login. The daily cron's job is just to refresh the leaderboards. Reconsidering: drop the `refreshLastActiveFlags` step. The `last_active_at` column is updated atomically on every activity. On-Break is just `last_active_at < NOW() - INTERVAL 14 DAY`. No batch refresh needed.
  3. `user_service::recomputeStreakDisplay()` — for users with a login in the last 24h, increment `login_streaks.streak_count` and update `users.current_streak` / `users.longest_streak` if needed. Awards the +15 (7-day) or +50 (30-day) `points_log` row when the streak crosses the threshold.
  4. `leaderboard_service::writeJsonCache()` — writes the 4 `var/leaderboards/{slug}.json` files
  5. Log the run to `cron_log` with `sweep='daily', processed_counts={campus_legends: N, weekly_risers: N, ...}, errors=[]`
- The `Weekly Risers` board's `weekly_points` is computed as `SUM(delta) FROM points_log WHERE user_id = ? AND event_at >= ? AND event_at < ?` where the window is Mon-Sun Asia/Colombo. The `?` params are `last_monday = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)` and `next_monday = DATE_ADD(last_monday, INTERVAL 7 DAY)`. The query is in `Leaderboard\Model\leaderboard_model::refreshWeeklyRisers()` and is a single GROUP BY query.
- The Phase 6 plan's WAD-friendly demo path: register buyer + seller (both with 0 points) → buyer verifies email (auto-credited +50, tier D, toasts "Tier up! You're now Rookie (D)") → seller creates 5 listings, gets approved (each +5, total +25; tier D, On-Break still N/A since they're both active) → buyer buys 1 listing (seller +30, buyer +10 — both halving on first-5 = +15 and +5) → seller redeems → both see tier progression on Profile (e.g., seller at 65 = Rookie, buyer at 55 = Rookie) → admin runs `POST /admin/cron/daily` → /leaderboards shows the 4 boards with the seed users at the top → buyer + seller visit Profile, see the tier progress bar (e.g., buyer at 55, "5 of 100 toward Operative (C)"). Velocity cap demo: hard-to-trigger in a demo; documented in tests.

</specifics>

<deferred>
## Deferred Ideas

- **Real-time leaderboard updates** — Daily refresh is the v1 contract (PTS-009). Polling on `/leaderboards` is sufficient. WebSockets / server-sent events are a v2 enhancement.
- **Tier-privilege tooltip with concrete privilege claims** — D-06 defers the L169 multi-bullet privilege copy until privilege gating is actually implemented. If a future phase adds listing quotas or search rank boost, the tooltip slot is already there.
- **Points-log popover on Profile** — D-07 ships the inline Recent activity section. The hover popover (EXPERIENCE.md L150) is a Phase 7/8 polish item.
- **Velocity flag detailed log on Profile** — D-02 ships the gentler "Earning paused" pill. A detailed "you earned X pts/hour, the cap is Y" log is a v2 audit surface; for the WAD scope the single pill is enough.
- **Admin void/approve UI for frozen accounts** — Phase 6 ships the `points_service::voidPoints()` and `clearPointsFreeze()` methods but no UI. Phase 8 wires the admin console.
- **Streak leaderboard badges** — D-01 ships the leaderboard without "🔥" emoji or any streak-count badge on individual user profile pages (per EXPERIENCE.md L325). If a future phase wants to celebrate streaks, it's a v2 change.
- **Cohort isolation (AD-20)** — Single-cohort MVP. The leaderboard queries filter through the same gate; a future `cohort_id` column would slot into the WHERE clauses without changing the API surface.
- **Privacy hardening** — PTS-009 says "no student_id digits". The query is `SELECT nickname, program, year FROM users` which never selects `student_id`. No PII risk. If a future phase adds more sensitive fields, the leaderboard query needs review.
- **Leaderboard pagination** — OQ-005 resolves as "no pagination control". Top 10/20 are the only content. If a future phase needs a "see all" expansion, it's a separate surface.
- **Per-tier privilege gating** (e.g., tier S users get to skip the listing auto-approve queue) — No such gating in the PRD scope. Aspirational copy in EXPERIENCE.md L169 stays aspirational until a real gating requirement is added.
- **Streak freeze / grace days** — Some streak systems offer "1 day off per week". The PRD does not require this; the streak is binary (logged in or not). The login_streaks table is the source of truth; a future "streak_freezes_used" column could add this without schema breaks.

### Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

</deferred>

---

*Phase: 6-Points, Ranks & Leaderboards*
*Context gathered: 2026-09-04*
