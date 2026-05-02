# Shared Context — v0.7.0 Extra Time, Penalties, and Shootout

# Purpose
Reusable reference for all prompts in the v0.7.0 bundle.

---

## Milestone Identity

**Version**: v0.7.0  
**Name**: Extra Time, Penalties, and Shootout  
**Goal**: Support match flows beyond regular time: in-match penalty events, extra time periods, and penalty shootout with separate scoring.

---

## Technical Stack

PHP, MySQL, plain CSS, vanilla JS. No framework, no build pipeline.

---

## Architecture

Same layer rules as all prior milestones. Services own all transactions.

---

## What Prior Milestones Established

- v0.1.0–v0.5.0: full stack, auth, preparation, live match core (goals, periods, finish)
- v0.6.0: substitutions, playing time, yellow/red cards, red-card restrictions, `sent_off` flag, `getEligiblePenaltyTakers()`

---

## Critical Invariants

**Normal match score and shootout score are COMPLETELY SEPARATE**:
- Normal score is derived from `match_events` (goal_own, goal_opponent, penalty_scored_own, penalty_scored_opponent)
- Shootout score is derived from `match_shootout_attempts` — a SEPARATE table
- Shootout goals must NEVER be added to `match_events` or affect the normal score
- The two scores are displayed separately: "3-2 (4-3 on penalties)"

**Sent-off players cannot take penalties** — in-match penalties AND shootout. The `getEligiblePenaltyTakers()` method from v0.6.0 enforces this.

**Missed in-match penalties do NOT update the score** — only `penalty_scored_own` and `penalty_scored_opponent` events affect the score.

**Duplicate `attempt_order` within the same round is rejected** — the `match_shootout_attempts` table must enforce uniqueness at the database level or in the service.

**All period transitions follow the documented state machine** — read `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` for when extra time and shootout are allowed.

**Extra-time duration from match configuration** — the `matches.extra_time_duration` field (set during match creation in v0.3.0) determines the length of each extra-time period. If this field is null, extra time cannot be started.

---

## Global Exclusions

- Livestream (v0.8.0)
- Finished-match corrections (v0.8.0)
- Advanced audit UI beyond what is already required
- Ratings, training, full statistics

---

## Required Documentation

All docs `-00` through `-12`. Focus on `-05` (critical behavior spec for extra time, penalties, shootout state machine).
