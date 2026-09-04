# Phase 6: Points, Ranks & Leaderboards - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-04
**Phase:** 06-points-ranks-leaderboards
**Areas discussed:** Streak Kings visibility, Velocity flag scope, On-Break detection rule, Weekly Risers empty handling, Leaderboard ordering ties, Tier-privilege tooltip scope, Points-log popover on Profile, Pair-cap enforcement surface

---

## Streak Kings visibility

| Option | Description | Selected |
|--------|-------------|----------|
| Full board (PTS-009) | Render the Streak Kings leaderboard with neutral chrome per EXPERIENCE.md L150 (no emoji, no "🔥", no congratulatory framing). | ✓ |
| Hidden board (EXPERIENCE.md L325 override) | Drop the leaderboard entirely; the data is collected but not surfaced. | |
| Board + per-user streak chips | Visible leaderboard + per-user "7-day streak" chip on profile. | |

**User's choice:** Selected by agent per WAD scope (all 8 gray areas; user said "no need to ask me"). D-01 in CONTEXT.md reconciles the contradiction.
**Notes:** EXPERIENCE.md L325 overrides "visible streak counters per user" (no chips on profile), NOT the leaderboard itself. PTS-009 is a hard requirement.

## Velocity flag scope

| Option | Description | Selected |
|--------|-------------|----------|
| Phase 6 ships engine only | `points_frozen` flag, velocity check, void/clear methods. Surface as gentler pill on Profile. | ✓ |
| Phase 6 ships engine + admin UI | Wire admin void/approve into the admin console. | |
| Defer entire velocity feature to Phase 7/8 | Phase 6 ships only the points engine; velocity is later. | |

**User's choice:** Selected by agent.
**Notes:** Phase 6 ships the engine; admin UI in Phase 8 (per roadmap). The pill is the gentler variant from D-02.

## On-Break detection rule

| Option | Description | Selected |
|--------|-------------|----------|
| Trigger on `points_log` insert + login | Denormalized `users.last_active_at` column, refreshed by DB trigger + login hook. | ✓ |
| Application-layer read every render | Compute On-Break from `MAX(points_log.event_at)` at render time. | |
| `last_active_at` on every HTTP request | Mark active on every successful page load. | |

**User's choice:** Selected by agent.
**Notes:** The trigger is the safe path (every writer is forced to refresh). Login is the explicit "I came back" signal. Read-only browsing does NOT clear On-Break.

## Weekly Risers empty handling

| Option | Description | Selected |
|--------|-------------|----------|
| Render only qualifying rows (variable count) | Board shrinks gracefully; no padding. | ✓ |
| Pad with empty-state rows | Fill the rest with the empty state. | |
| Hide board entirely when 0 qualify | Board only appears when at least 1 user qualifies. | |

**User's choice:** Selected by agent.
**Notes:** D-04 in CONTEXT.md applies the same rule to Campus Legends Wall and Category Leaders.

## Leaderboard ordering ties

| Option | Description | Selected |
|--------|-------------|----------|
| Ascending `user_id` (stable, deterministic) | First-registered user wins the tie. | ✓ |
| Recency (last write time) | Most-recent-active user wins the tie. | |
| Random | Non-deterministic. | |

**User's choice:** Selected by agent.
**Notes:** D-05 in CONTEXT.md. `user_id` is a stable identifier, no recency dependence, deterministic across all 4 boards.

## Tier-privilege tooltip scope

| Option | Description | Selected |
|--------|-------------|----------|
| Baseline only (tier name + threshold) | "Operative (C) — 150 to 399 points". | ✓ |
| Full privilege list (EXPERIENCE.md L169) | Multi-bullet: "C+ lists 5 active, B+ search boost, A+ featured, S Hall of Fame". | |
| No tooltip | Drop the tooltip entirely. | |

**User's choice:** Selected by agent.
**Notes:** D-06 in CONTEXT.md. The privilege claims are aspirational; no privilege gating exists in the PRD.

## Points-log popover on Profile

| Option | Description | Selected |
|--------|-------------|----------|
| Inline "Recent activity" section (5 rows) | Read-only, no JS popover. | ✓ |
| Hover popover (EXPERIENCE.md L150) | JS popover on the points total. | |
| Dedicated `/profile/activity` page | Full list with pagination. | |

**User's choice:** Selected by agent.
**Notes:** D-07 in CONTEXT.md. The inline section uses the same data; popover is a polish upgrade later.

## Pair-cap enforcement surface

| Option | Description | Selected |
|--------|-------------|----------|
| Log row with `pair_cap_hit=TRUE`, `delta=0` | Audit trail preserved, no points contribution. | ✓ |
| Silent drop | No row written when cap hit. | |
| Block the action entirely | Refuse the 3rd transaction. | |

**User's choice:** Selected by agent.
**Notes:** D-08 in CONTEXT.md. Honors "enforced at insert time" literally. Audit trail complete.

---

## the agent's Discretion

All 8 gray areas were decided by the agent per user's "no need to ask me" instruction. User's intent: ship a WAD-friendly Phase 6 that is consistent with the existing code, the PRD, and the EXPERIENCE.md override on streaks. The decisions are conservative (default to the PRD wording, prefer reversibility, avoid aspirational copy that promises features that don't exist).

## Deferred Ideas

See CONTEXT.md `<deferred>` section. Summary:
- Real-time leaderboard updates (v2)
- Tier-privilege tooltip with concrete claims (v2, when privilege gating ships)
- Points-log popover (Phase 7/8 polish)
- Velocity flag detailed log (v2 audit surface)
- Admin void/approve UI (Phase 8)
- Per-user streak chips on profile (intentionally excluded per EXPERIENCE.md L325)
- Cohort isolation (AD-20, S2 retro)
- Streak freeze / grace days (no PRD requirement)
