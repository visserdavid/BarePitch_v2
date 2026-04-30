# BarePitch — Domain Model and Schema
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the canonical domain model for BarePitch.

It is the source of truth for:
- core entities
- fields
- relationships
- constraints
- status values
- deletion policy
- schema invariants

This document does not define:
- route paths
- UI behavior
- implementation phase order

If another document refers to an entity without defining it, this document wins.

---

## 2. Modeling Principles

- Every entity must have one clear purpose.
- Every multi-team resource must be scoped explicitly to a club, season, team, or match context.
- Source-of-truth data must be stored separately from cached or derived values.
- Soft deletion is preferred for user-facing historical records.
- Public token values must never be stored in plaintext.
- Historical match data must remain reconstructable after corrections.

---

## 3. Global Conventions

### 3.1 Primary Keys

- Every table uses a single numeric primary key named `id`.

### 3.2 Timestamps

Where relevant, entities should use:
- `created_at`
- `updated_at`

Lifecycle entities may also use:
- `deleted_at`
- `deactivated_at`
- `used_at`
- `expires_at`

### 3.3 Foreign Keys

- Foreign keys must be explicit.
- Cascading hard deletes should be avoided for historical match data.
- Referential integrity must be enforced at database level where practical.

### 3.4 Soft Delete Rule

Use soft deletion for:
- users
- players
- teams where historical references must remain visible
- matches if deletion is allowed at all

Do not soft delete operational short-lived records that are safe to expire naturally:
- locks
- login tokens

### 3.5 Text and Enum Rule

- Free-form text is allowed only where the product explicitly needs it.
- Status-like fields should use documented enum values, not arbitrary text.
- Free-text input must have explicit maximum lengths in the database schema, server-side validators, and UI form controls.
- Length limits exist primarily for data quality, security, validation consistency, and UI stability. Database volume reduction is a secondary benefit.
- Use `VARCHAR(n)` for names, labels, email addresses, locale identifiers, and short text fields with predictable size.
- Use `TEXT` only for fields that intentionally support paragraph-like notes or larger internal values.
- User-facing free text must be trimmed before validation unless preserving surrounding whitespace is explicitly required.
- Empty strings should be normalized to `NULL` for optional free-text fields unless the field is required.

Canonical text length defaults:

| Data category | Max length | Recommended SQL type |
|---|---:|---|
| Person first name | 80 | `VARCHAR(80)` |
| Person last name | 80 | `VARCHAR(80)` |
| Person display name | 120 | `VARCHAR(120)` |
| Club, team, season, phase, formation, and position labels | 120 | `VARCHAR(120)` |
| Opponent name | 120 | `VARCHAR(120)` |
| Email address | 254 | `VARCHAR(254)` |
| Locale key | 10 | `VARCHAR(10)` |
| Short notes and live match notes | 500 | `VARCHAR(500)` or `TEXT` with validator limit |
| Longer internal/admin notes | 2000 | `TEXT` with validator limit |
| Public or login token hash stored as SHA-256 hex | 64 | `CHAR(64)` |

If a specific field needs a different limit, this document must define it explicitly.

---

## 4. Core Entity List

Canonical core entities:

1. `club`
2. `season`
3. `phase`
4. `team`
5. `user`
6. `user_team_role`
7. `player`
8. `player_profile`
9. `player_season_context`
10. `formation`
11. `formation_position`
12. `training`
13. `training_attendance`
14. `match`
15. `match_period`
16. `match_selection`
17. `match_lineup_slot`
18. `match_event`
19. `substitution`
20. `penalty_shootout_attempt`
21. `match_rating`
22. `audit_log`
23. `match_lock`
24. `magic_login_token`
25. `livestream_token`

---

## 5. Entity Definitions

### 5.1 `club`

Purpose:
- top-level organization

Fields:
- `id`
- `name`
- `is_active`
- `created_at`
- `updated_at`

Constraints:
- `name` required
- `name` unique
- `name` max length follows the global label limit

Deletion policy:
- should not be hard deleted once related teams exist

### 5.2 `season`

Purpose:
- time container for clubs and teams

Fields:
- `id`
- `club_id`
- `label`
- `starts_on`
- `ends_on`
- `is_active`
- `created_at`
- `updated_at`

Constraints:
- `label` required
- `starts_on <= ends_on`
- unique per club on `label`
- `label` max length follows the global label limit

Invariants:
- a season belongs to one club

### 5.3 `phase`

Purpose:
- competition or planning subdivision within a season

Fields:
- `id`
- `season_id`
- `number`
- `label`
- `starts_on`
- `ends_on`
- `focus_text`
- `created_at`
- `updated_at`

Constraints:
- `number` unique within season
- `label` required
- valid date range
- `label` max length follows the global label limit
- `focus_text` max length follows the short notes limit

### 5.4 `team`

Purpose:
- working context for coaching staff

Fields:
- `id`
- `club_id`
- `season_id`
- `name`
- `max_match_players`
- `livestream_hours_after_match`
- `training_days_json`
- `is_active`
- `created_at`
- `updated_at`

Constraints:
- unique on `club_id + season_id + name`
- `max_match_players >= 11`
- `livestream_hours_after_match between 1 and 72`
- `name` max length follows the global label limit

Invariants:
- a team belongs to one club and one season

### 5.5 `user`

Purpose:
- authenticated application account

Fields:
- `id`
- `first_name`
- `last_name`
- `email`
- `locale`
- `is_administrator`
- `is_active`
- `created_at`
- `updated_at`
- `deactivated_at`

Constraints:
- `email` unique
- `locale` required
- `first_name` and `last_name` max length follows the person name limit
- `email` max length follows the email limit
- `locale` max length follows the locale key limit

Deletion policy:
- soft deactivate only

### 5.6 `user_team_role`

Purpose:
- per-team role assignment

Fields:
- `id`
- `user_id`
- `team_id`
- `role_key`
- `created_at`
- `updated_at`

Allowed `role_key` values:
- `coach`
- `trainer`
- `team_manager`

Constraints:
- unique on `user_id + team_id + role_key`

### 5.7 `player`

Purpose:
- persistent player identity across seasons

Fields:
- `id`
- `first_name`
- `last_name`
- `display_name`
- `is_active`
- `created_at`
- `updated_at`
- `deactivated_at`

Deletion policy:
- soft deactivate only

Constraints:
- `first_name` and `last_name` max length follows the person name limit
- `display_name` max length follows the person display name limit

### 5.8 `player_profile`

Purpose:
- lightweight non-season-specific player metadata

Fields:
- `id`
- `player_id`
- `default_preferred_foot`
- `notes_internal`
- `created_at`
- `updated_at`

Constraints:
- one-to-one with `player`
- `notes_internal` max length follows the longer internal/admin notes limit

Note:
- sensitive private medical storage is not allowed here

### 5.9 `player_season_context`

Purpose:
- player assignment and season-specific attributes

Fields:
- `id`
- `player_id`
- `season_id`
- `team_id`
- `preferred_line`
- `preferred_foot`
- `squad_number`
- `is_guest_eligible`
- `created_at`
- `updated_at`

Constraints:
- unique on `player_id + season_id`
- `team_id` may be `NULL` only for external guest players

Invariants:
- internal players have a team
- external reusable guest players use `team_id = NULL`

### 5.10 `formation`

Purpose:
- reusable formation template

Fields:
- `id`
- `team_id`
- `name`
- `grid_rows`
- `grid_cols`
- `is_active`
- `created_at`
- `updated_at`

Constraints:
- `grid_rows = 10`
- `grid_cols = 11`
- `name` max length follows the global label limit

### 5.11 `formation_position`

Purpose:
- required starting slot definition within a formation

Fields:
- `id`
- `formation_id`
- `label`
- `line_key`
- `grid_row`
- `grid_col`
- `sort_order`

Constraints:
- unique on `formation_id + grid_row + grid_col`
- `label` max length follows the global label limit

### 5.12 `training`

Purpose:
- scheduled team training session

Fields:
- `id`
- `team_id`
- `phase_id`
- `starts_at`
- `notes`
- `focus_tags_json`
- `status`
- `created_at`
- `updated_at`

Allowed `status` values:
- `planned`
- `completed`
- `cancelled`

Constraints:
- `notes` max length follows the short notes limit

### 5.13 `training_attendance`

Purpose:
- player attendance per training session

Fields:
- `id`
- `training_id`
- `player_season_context_id`
- `status`
- `absence_reason`
- `injury_note`
- `created_at`
- `updated_at`

Allowed `status` values:
- `present`
- `absent`
- `injured`

Allowed `absence_reason` values:
- `sick`
- `holiday`
- `school`
- `other`

Constraints:
- unique on `training_id + player_season_context_id`

### 5.14 `match`

Purpose:
- fixture and aggregate match container

Fields:
- `id`
- `team_id`
- `phase_id`
- `date`
- `kick_off_time`
- `opponent_name`
- `home_away`
- `match_type`
- `regular_half_duration_minutes`
- `extra_time_half_duration_minutes`
- `status`
- `active_phase`
- `goals_scored`
- `goals_conceded`
- `shootout_goals_scored`
- `shootout_goals_conceded`
- `finished_at`
- `created_at`
- `updated_at`
- `deleted_at`

Allowed `status` values:
- `planned`
- `prepared`
- `active`
- `finished`

Allowed `active_phase` values:
- `none`
- `regular_time`
- `halftime`
- `extra_time`
- `penalty_shootout`
- `finished`

Notes:
- score fields are cached, not canonical

Constraints:
- `opponent_name` max length follows the opponent name limit

### 5.15 `match_period`

Purpose:
- explicit persisted match timeline periods

Fields:
- `id`
- `match_id`
- `period_key`
- `sort_order`
- `started_at`
- `ended_at`
- `configured_duration_minutes`

Allowed `period_key` values:
- `regular_1`
- `regular_2`
- `extra_1`
- `extra_2`

Constraints:
- unique on `match_id + period_key`

### 5.16 `match_selection`

Purpose:
- players selected or made available for a match

Fields:
- `id`
- `match_id`
- `player_id`
- `player_season_context_id`
- `guest_type`
- `is_guest`
- `attendance_status`
- `absence_reason`
- `injury_note`
- `shirt_number_override`
- `is_starting`
- `is_on_bench`
- `is_active_on_field`
- `is_sent_off`
- `can_reenter`
- `playing_time_seconds`
- `created_at`
- `updated_at`

Allowed `guest_type` values:
- `none`
- `internal`
- `external`

Allowed `attendance_status` values:
- `present`
- `absent`
- `injured`

Constraints:
- unique on `match_id + player_id`

### 5.17 `match_lineup_slot`

Purpose:
- current authoritative lineup position state

Fields:
- `id`
- `match_id`
- `match_selection_id`
- `formation_position_id`
- `grid_row`
- `grid_col`
- `is_active_slot`
- `created_at`
- `updated_at`

Constraints:
- one active field slot per player
- unique active occupancy on `match_id + grid_row + grid_col`
- bench players have `grid_row = NULL` and `grid_col = NULL`

### 5.18 `match_event`

Purpose:
- canonical source of truth for live match events except shootout attempts

Fields:
- `id`
- `match_id`
- `period_id`
- `event_type`
- `team_side`
- `player_selection_id`
- `assist_selection_id`
- `zone_code`
- `outcome`
- `minute_display`
- `match_second`
- `note_text`
- `created_by_user_id`
- `created_at`
- `updated_at`

Allowed `event_type` values:
- `goal`
- `penalty`
- `yellow_card`
- `red_card`
- `note`

Allowed `team_side` values:
- `own`
- `opponent`

Allowed `outcome` values:
- `scored`
- `missed`
- `none`

Constraints:
- `note_text` max length follows the short notes limit

### 5.19 `substitution`

Purpose:
- substitution history source record

Fields:
- `id`
- `match_id`
- `period_id`
- `player_off_selection_id`
- `player_on_selection_id`
- `target_grid_row`
- `target_grid_col`
- `match_second`
- `created_by_user_id`
- `created_at`
- `updated_at`

### 5.20 `penalty_shootout_attempt`

Purpose:
- canonical shootout attempt history

Fields:
- `id`
- `match_id`
- `attempt_order`
- `round_number`
- `team_side`
- `player_selection_id`
- `player_name_text`
- `outcome`
- `zone_code`
- `is_sudden_death`
- `created_by_user_id`
- `created_at`
- `updated_at`

Constraints:
- unique on `match_id + attempt_order`
- `player_name_text` max length follows the person display name limit

### 5.21 `match_rating`

Purpose:
- optional post-match skill rating per player

Fields:
- `id`
- `match_id`
- `player_selection_id`
- `pace`
- `shooting`
- `passing`
- `dribbling`
- `defending`
- `physicality`
- `is_complete`
- `created_by_user_id`
- `created_at`
- `updated_at`

Constraints:
- unique on `match_id + player_selection_id`

### 5.22 `audit_log`

Purpose:
- append-only audit trail for sensitive changes

Fields:
- `id`
- `entity_type`
- `entity_id`
- `match_id`
- `user_id`
- `action_key`
- `field_name`
- `old_value_json`
- `new_value_json`
- `created_at`

Constraints:
- append-only in normal product operation
- `field_name` max length follows the global label limit

### 5.23 `match_lock`

Purpose:
- edit lock for match preparation, live control, and corrections

Fields:
- `id`
- `match_id`
- `user_id`
- `locked_at`
- `expires_at`
- `created_at`
- `updated_at`

Constraints:
- at most one active lock per match

### 5.24 `magic_login_token`

Purpose:
- one-time passwordless login token

Fields:
- `id`
- `user_id`
- `token_hash`
- `expires_at`
- `used_at`
- `requested_ip`
- `requested_user_agent`
- `created_at`

Constraints:
- token value never stored in plaintext
- only one active unused token should remain valid per user
- `token_hash` length must match the selected hash encoding, with `CHAR(64)` required for SHA-256 hex

### 5.25 `livestream_token`

Purpose:
- public bearer token for match livestream access

Fields:
- `id`
- `match_id`
- `token_hash`
- `issued_at`
- `expires_at`
- `stopped_at`
- `rotated_from_token_id`
- `created_by_user_id`

Constraints:
- token value never stored in plaintext
- only one active token should grant public access at a time
- `token_hash` length must match the selected hash encoding, with `CHAR(64)` required for SHA-256 hex

---

## 6. Cross-Entity Invariants

- A match must use a phase from the same season as its team.
- A player may have only one `player_season_context` per season.
- A sent-off `match_selection` record must never become re-enterable.
- `match.goals_scored` and `match.goals_conceded` must be derivable from `match_event`.
- `match.shootout_goals_scored` and `match.shootout_goals_conceded` must be derivable from `penalty_shootout_attempt`.
- Finished matches remain `finished` after corrections.
- Public livestream access must depend on token validity, started state, and not-stopped state.

---

## 7. Derived vs Canonical Data

Canonical source data:
- `match_event`
- `penalty_shootout_attempt`
- `substitution`
- `match_period`
- `training_attendance`
- `match_rating` input fields

Derived or cached data:
- `match.goals_scored`
- `match.goals_conceded`
- `match.shootout_goals_scored`
- `match.shootout_goals_conceded`
- `match_selection.playing_time_seconds`
- `match_selection.is_active_on_field`
- `match_selection.is_on_bench`
- `match_rating.is_complete`

Only service-layer business logic may update derived or cached data.

---

## 8. Open Modeling Decisions That Must Stay Consistent

The following choices are fixed unless this document is updated:

- external guest players use normal `player` records plus `player_season_context.team_id = NULL`
- livestream uses a dedicated `livestream_token` entity rather than a bare token field on `match`
- periods are explicit records, not implied timestamps on `match`
- lineup state is stored as current state, not as minute-by-minute replay data

---

## 9. AI Implementation Notes

When generating schema or model code:
- do not invent additional core entities unless a documented gap requires approval
- do not merge canonical and cached data into one undifferentiated structure
- do not store public or login tokens in plaintext
- do not remove historical entities in the name of simplification

---

## End
