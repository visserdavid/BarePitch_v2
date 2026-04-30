# BarePitch — State and Derived Data Policy
Version 1.0 — April 2026

---

## 1. Purpose

This document defines state ownership and derived-data rules for BarePitch.

It is the source of truth for:
- canonical data sources
- cached fields
- recalculation triggers
- stale-state repair expectations

---

## 2. Core Principle

BarePitch must distinguish between:
- source-of-truth records
- current-state projections
- cached aggregates

No cached or projected value may become the only truth for a critical domain.

---

## 3. Canonical Sources by Domain

| Domain | Canonical Source |
|---|---|
| Match score | `match_event` |
| Shootout score | `penalty_shootout_attempt` |
| Match periods | `match_period` |
| Current lineup | `match_lineup_slot` plus substitution/red-card effects |
| Substitution history | `substitution` |
| Playing time basis | `match_period`, `substitution`, `match_event` red cards |
| Match attendance | `match_selection.attendance_status` |
| Training attendance | `training_attendance` |
| Rating fields | `match_rating` score columns |
| Correction history | `audit_log` |
| Public livestream access | `livestream_token` validity and lifecycle fields |

---

## 4. Cached or Derived Fields

Cached or derived fields include:
- `match.goals_scored`
- `match.goals_conceded`
- `match.shootout_goals_scored`
- `match.shootout_goals_conceded`
- `match_selection.playing_time_seconds`
- `match_selection.is_active_on_field`
- `match_selection.is_on_bench`
- `match_selection.is_starting`
- `match_rating.is_complete`

These fields exist for speed or convenience.

They are never canonical if source data disagrees.

---

## 5. Current-State Projection Rules

### 5.1 Lineup

The app stores current lineup state, not full positional replay history.

Current lineup must reflect:
- preparation result before kickoff
- substitutions
- red card removals
- explicit rearrangement actions if supported

### 5.2 Playing Time

Playing time is derived from:
- periods started and ended
- starting lineup
- substitution in/out moments
- red card exits

### 5.3 Rating Completeness

`match_rating.is_complete` is true only when all six skill fields are present and valid.

---

## 6. Recalculation Triggers

Recalculation is mandatory after:
- goal create/update
- penalty create/update
- shootout attempt create/update
- substitution create/update
- red card create/update
- match preparation confirmation
- finished-match correction
- training attendance write
- rating write

Recommended recalculation ownership:
- score-related: `LiveMatchService` or `CorrectionService`
- lineup-related: `MatchPreparationService`, `LiveMatchService`, `CorrectionService`
- attendance/statistics-related: `TrainingService`, `StatisticsService`
- ratings-related: `RatingService` or `StatisticsService`

---

## 7. Transaction Rules for Derived Data

When source data and cached data both change:
- they must be updated in the same transaction where practical
- rollback must restore both source and cached state

Do not:
- persist source data successfully and defer required cache updates without a defined reconciliation mechanism

---

## 8. Blind Increment Prohibition

Do not update score caches by blind increment or decrement alone.

Required rule:
- recalculate score from source events

This applies to:
- live event writes
- corrections
- repair jobs

---

## 9. Stale-State Detection

The system should treat these as stale-state indicators:
- cached score differs from event-derived score
- shootout cache differs from attempt-derived score
- active lineup contains duplicate active players
- sent-off player marked active on field
- `match_rating.is_complete` differs from field completeness

When stale state is detected:
- log the anomaly
- prevent unsafe further mutation if integrity is at risk
- repair from source data where safe

---

## 10. Repair Strategy

Recommended repair order:

1. identify affected match or entity
2. re-read canonical source records
3. recalculate derived values
4. persist corrected cached state in transaction
5. write audit or technical log as appropriate

Direct manual edits to cached fields are not allowed as normal workflow.

If emergency database repair is ever required:
- it must be treated as an operational exception
- it must be logged outside normal product flow

---

## 11. Statistics Policy

Statistics are derived views, not primary business records.

Statistics must be recomputable from:
- matches
- match events
- shootout attempts
- substitutions
- attendance
- ratings

Statistics should never become the only place where historical truth exists.

---

## 12. Livestream Projection Policy

Livestream is a read projection of current and corrected match state.

It must reflect:
- current score cache
- current phase
- timeline subset allowed for public view

It must never reveal:
- private notes
- attendance details
- internal-only administrative data

If underlying match source data changes during active livestream:
- the next permitted refresh must reflect corrected public state

---

## 13. AI Implementation Rules

When generating code:
- define source-of-truth before defining cached fields
- ensure services know which recalculations they own
- add reconciliation tests for every derived domain
- never invent extra caches without documenting them here

---

## End
