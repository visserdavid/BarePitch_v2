# BarePitch — Data Integrity and Test Scenarios
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the data integrity rules and test scenarios for BarePitch.

This document verifies the product rules.

It must not become the primary place where behavior is invented.

The goal is to ensure that the application remains:

- consistent
- predictable
- recoverable
- safe under correction
- reliable during live match usage

This document is intended for:

- manual testing
- automated test planning
- implementation validation
- regression testing

---

## 2. Core Integrity Principles

### 2.1 Source of Truth

| Domain | Source of Truth |
|---|---|
| Match score | `match_event` |
| Shootout score | `penalty_shootout_attempt` |
| Current lineup | `match_lineup_slot` |
| Match periods and active phase | `match_period` + match phase fields |
| Substitution history | `substitution` |
| Playing time | periods, substitutions, red cards |
| Attendance | `attendance` |
| Player identity | `player` |
| Player season context | `player_season_context` |
| Corrections | source table + `audit_log` |

Cached values may exist for performance, but must be recalculable from source data.

Canonical live-match behavior is defined in `BarePitch-v2-05-critical-behavior-specifications-v1.0.md`.

### 2.2 Cached Data Rule

Cached fields include:

- `match.goals_scored`
- `match.goals_conceded`
- `match.shootout_goals_scored`
- `match.shootout_goals_conceded`
- `match_selection.playing_time_seconds`
- `match_rating.is_complete`

After source data changes:

- cached values must be recalculated
- recalculation must happen inside the same transaction where possible
- cached values must never be edited as independent truth

### 2.3 Transaction Rule

Every multi-step write operation that affects match state must run inside a transaction.

Examples:

- starting match
- registering goal
- registering penalty
- substitution
- red card
- shootout attempt
- finished match correction
- score recalculation
- audit logging

Failure must result in rollback.

No partial state is allowed.

### 2.4 Authorization Rule

Every write operation must check authorization server-side.

Each write must validate:

- authenticated user
- active role
- team access
- resource ownership
- match state
- lock ownership if required

### 2.5 Locking Rule

Only one active editor may modify a match at one time.

The lock applies to:

- live match control
- match preparation
- finished match corrections

Lock conflict must prevent write access.

No silent overwrite is allowed.

### 2.6 State Transition Rule

Allowed core flow:

- `planned → prepared`
- `prepared → active`
- `active → finished`

No automatic transition from `finished` back to `active` is allowed.

---

## 3. Entity Integrity Rules

### 3.1 Club

Rules:

- club name is required
- club name must be unique
- inactive clubs are not shown by default
- club deletion should be avoided if related teams exist

Test priority: low

### 3.2 Season

Rules:

- season label is required
- season label must be unique
- start date must be before or equal to end date
- teams belong to one season
- phases belong to one season

Test priority: medium

### 3.3 Phase

Rules:

- phase belongs to one season
- phase number must be unique within season
- phase date range must be valid
- matches and trainings must use phases from their team season

Test priority: medium

### 3.4 Team

Rules:

- team belongs to one club
- team belongs to one season
- team name must be unique within club and season
- max match players must be at least 11
- livestream duration must not exceed 72 hours

Test priority: high

### 3.5 User

Rules:

- email is required
- email must be unique
- user without role has no team access
- administrator is global
- non-admin roles are team-bound

Test priority: high

### 3.6 Player

Rules:

- player identity persists across seasons
- player must not be duplicated per season
- player may have one season context per season
- external guest player has season context with `team_id = NULL`

Test priority: high

### 3.7 Match

Rules:

- match belongs to one team
- match belongs to one phase
- match phase must belong to the same season as the team
- match starts only from `prepared`
- finished match remains `finished` after correction
- match periods must be explicitly stored

Test priority: critical

---

## 4. Match Preparation Integrity

### 4.1 Preparation Requirements

A match can become `prepared` only when:

- at least 11 players are present
- player count does not exceed team maximum
- formation is selected
- all starting positions are filled
- every starter is present
- no starter is injured

### 4.2 Test Scenarios

#### MP-01: Prepare valid match

Given:

- match status is `planned`
- 11 players are present
- formation is selected
- all starting positions are filled

When:

- coach confirms preparation

Then:

- match status becomes `prepared`
- non-starters are placed on bench
- no validation errors are shown

#### MP-02: Prepare with 10 present players

Given:

- match status is `planned`
- only 10 players are present

When:

- coach confirms preparation

Then:

- match remains `planned`
- validation error is shown

Expected message:

- At least 11 players are required.

#### MP-03: Prepare with too many players

Given:

- team max match players is 18
- 19 players are present

When:

- coach confirms preparation

Then:

- match remains `planned`
- validation error is shown

Expected message:

- Player limit exceeded.

#### MP-04: Starter marked injured

Given:

- player is assigned to starting lineup
- same player has attendance status `injured`

When:

- coach confirms preparation

Then:

- preparation is blocked

Expected message:

- Injured players cannot start.

#### MP-05: Formation missing

Given:

- match has present players
- no formation is selected

When:

- coach confirms preparation

Then:

- preparation is blocked

Expected message:

- Formation is required.

#### MP-06: Incomplete lineup

Given:

- formation has 11 positions
- only 10 positions are filled

When:

- coach confirms preparation

Then:

- preparation is blocked

Expected message:

- Lineup is incomplete.

---

## 5. Match State Integrity

#### MS-01: Start prepared match

Given:

- match status is `prepared`
- user has coach role
- match is not locked by another user

When:

- coach starts match

Then:

- status becomes `active`
- active phase becomes `regular_time`
- first period starts
- score is 0–0
- livestream starts

#### MS-02: Start planned match

Given:

- match status is `planned`

When:

- coach starts match

Then:

- action is rejected
- status remains `planned`

Expected message:

- Match must be prepared before it can start.

#### MS-03: Finish active match at valid point

Given:

- match is active
- regular time has ended
- coach chooses finish match

When:

- finish is confirmed

Then:

- status becomes `finished`
- finished_at is set
- livestream expiration is set

#### MS-04: Edit finished match

Given:

- match status is `finished`
- user is coach

When:

- coach corrects event

Then:

- correction is allowed
- match remains `finished`
- audit log is created

#### MS-05: Finished match must not restart

Given:

- match status is `finished`

When:

- user attempts to start match

Then:

- action is rejected

Expected message:

- Finished matches cannot be restarted.

---

## 6. Score Integrity

### 6.1 Score Rules

Regular match score is calculated from:

- regular goals
- extra-time goals
- scored penalties during match

Regular match score excludes:

- penalty shootout goals

Shootout score is calculated separately from `penalty_shootout_attempt`.

### 6.2 Test Scenarios

#### SC-01: Own regular goal

Given:

- active match score is 0–0

When:

- coach registers own regular goal

Then:

- `match_event` is created
- `goals_scored` becomes 1
- `goals_conceded` remains 0
- timeline shows goal

#### SC-02: Opponent regular goal

Given:

- active match score is 0–0

When:

- coach registers opponent goal

Then:

- `goals_scored` remains 0
- `goals_conceded` becomes 1

#### SC-03: Scored penalty during match

Given:

- active match score is 0–0

When:

- own scored penalty is registered

Then:

- penalty event is stored
- `goals_scored` becomes 1

#### SC-04: Missed penalty during match

Given:

- active match score is 0–0

When:

- missed penalty is registered

Then:

- penalty event is stored
- score remains 0–0
- timeline shows missed penalty

#### SC-05: Shootout goal does not affect match score

Given:

- match score after extra time is 1–1
- penalty shootout is active

When:

- own shootout attempt is scored

Then:

- `shootout_goals_scored` increases
- `goals_scored` remains 1

#### SC-06: Score recalculates after correction

Given:

- finished match has score 2–1
- one own goal event is corrected to opponent goal

When:

- correction is saved

Then:

- score is recalculated from source events
- cached score updates
- audit log records change

---

## 7. Lineup Integrity

### 7.1 Lineup Rules

- only current lineup state is stored
- one active player may occupy one grid slot
- bench players have no grid coordinates
- sent-off players cannot be active on field
- current lineup must match substitution and red card effects

### 7.2 Test Scenarios

#### LU-01: Duplicate player on field

Given:

- player A already occupies a grid slot

When:

- coach attempts to place player A in another active slot

Then:

- action is rejected

Expected message:

- Player is already on the field.

#### LU-02: Duplicate grid slot

Given:

- grid slot row 5, col 6 is occupied

When:

- coach attempts to place another player in same slot

Then:

- action is rejected or replacement flow is required

Expected behavior:

- no accidental overwrite

#### LU-03: Bench player has no coordinates

Given:

- player is on bench

Then:

- grid_row is NULL
- grid_col is NULL
- is_active_on_field = false

#### LU-04: Active player has coordinates

Given:

- player is on field

Then:

- grid_row is not NULL
- grid_col is not NULL
- is_active_on_field = true

---

## 8. Substitution Integrity

### 8.1 Substitution Rules

A valid substitution requires:

- outgoing player is active on field
- incoming player is on bench
- incoming player is eligible
- incoming player is not sent off
- target grid slot is valid

### 8.2 Test Scenarios

#### SU-01: Valid substitution

Given:

- player A is active on field
- player B is on bench

When:

- coach substitutes B for A

Then:

- A moves to bench
- B moves to field
- substitution record is created
- playing time updates
- timeline shows substitution

#### SU-02: Substitute sent-off player

Given:

- player B has red card

When:

- coach attempts to substitute B on

Then:

- action is rejected

Expected message:

- Player cannot re-enter after red card.

#### SU-03: Substitute player not in match selection

Given:

- player C is not selected for match

When:

- coach attempts to substitute C on

Then:

- action is rejected

#### SU-04: Outgoing player not on field

Given:

- player A is on bench

When:

- coach attempts to substitute A off

Then:

- action is rejected

---

## 9. Red Card Integrity

### 9.1 Red Card Rules

When red card is registered:

- event is stored
- player leaves field
- player cannot re-enter
- player cannot take penalty shootout attempt
- playing time stops
- field player count decreases by one

### 9.2 Test Scenarios

#### RC-01: Valid red card

Given:

- player A is active on field

When:

- coach registers red card for A

Then:

- red card event is stored
- A leaves field
- A moves to bench context
- A can_reenter = false
- A is_sent_off = true
- playing time stops

#### RC-02: Red-carded player re-entry blocked

Given:

- player A has red card

When:

- coach tries to substitute A back in

Then:

- action is rejected

#### RC-03: Red-carded player in shootout blocked

Given:

- player A has red card
- penalty shootout is active

When:

- coach selects A as taker

Then:

- action is rejected

---

## 10. Penalty Shootout Integrity

### 10.1 Shootout Rules

- shootout attempts are stored separately from match events
- attempt order must be unique per match
- shootout score is calculated separately
- sent-off players are not eligible
- shootout can end automatically or manually
- ending requires confirmation

### 10.2 Test Scenarios

#### PS-01: Valid own scored attempt

Given:

- penalty shootout is active

When:

- coach registers own scored attempt

Then:

- attempt is stored
- shootout_goals_scored increases
- match score remains unchanged

#### PS-02: Valid missed attempt

Given:

- penalty shootout is active

When:

- coach registers missed attempt

Then:

- attempt is stored
- shootout score does not increase

#### PS-03: Duplicate attempt order

Given:

- attempt_order 3 already exists

When:

- system attempts to store another attempt_order 3

Then:

- database rejects or application prevents it

#### PS-04: Automatic ending requires confirmation

Given:

- shootout is mathematically decided

When:

- system detects winner

Then:

- system shows ending confirmation
- match is not finished silently

#### PS-05: Manual ending requires confirmation

Given:

- shootout is active

When:

- coach manually ends shootout

Then:

- confirmation is required

---

## 11. Attendance Integrity

### 11.1 Attendance Rules

Attendance statuses:

- present
- absent
- injured

Calculation formula:

present / (present + absent)

Excluded:

- injured
- cancelled training sessions

### 11.2 Test Scenarios

#### AT-01: Injured excluded from denominator

Given:

- 9 sessions present
- 1 session injured

When:

- attendance percentage is calculated

Then:

- percentage = 9 / 9 = 100%

#### AT-02: Absent included in denominator

Given:

- 8 present
- 2 absent

When:

- attendance percentage is calculated

Then:

- percentage = 8 / 10 = 80%

#### AT-03: Cancelled training excluded

Given:

- 8 present
- 1 absent
- 1 cancelled training

When:

- attendance percentage is calculated

Then:

- percentage = 8 / 9

---

## 12. Rating Integrity

### 12.1 Rating Rules

Rating counts only when all required fields are filled:

- pace
- shooting
- passing
- dribbling
- defending
- physicality

Partial rating:

- may be stored
- does not count in average
- is_complete = false

### 12.2 Test Scenarios

#### RT-01: Complete rating

Given:

- all six fields are filled

When:

- rating is saved

Then:

- is_complete = true
- rating counts in average

#### RT-02: Partial rating

Given:

- only five fields are filled

When:

- rating is saved

Then:

- is_complete = false
- rating does not count in average

---

## 13. Guest Player Integrity

### 13.1 Guest Player Rules

Internal guest player:

- must belong to another team in same club

External guest player:

- has season context with team_id = NULL

Guest player status:

- match-context only

### 13.2 Test Scenarios

#### GP-01: Add internal guest player

Given:

- player belongs to another team within same club

When:

- coach adds player as guest

Then:

- player is added to match selection
- is_guest = true
- guest_type = internal

#### GP-02: Add player from different club

Given:

- player belongs to another club

When:

- coach adds player as guest

Then:

- action is rejected

#### GP-03: Create external guest player

Given:

- coach enters guest player name

When:

- coach saves external guest player

Then:

- player is created
- season context created with team_id = NULL
- player can be selected as guest

---

## 14. Livestream Integrity

### 14.1 Livestream Rules

- livestream starts when match becomes active
- livestream expires after configured duration
- default duration is 24 hours
- maximum duration is 72 hours
- coach may stop livestream manually
- corrections are visible while livestream active

### 14.2 Test Scenarios

#### LS-01: Livestream starts with match

Given:

- match status is prepared

When:

- coach starts match

Then:

- livestream_started_at is set
- livestream token exists
- public link becomes available

#### LS-02: Livestream expires

Given:

- match finished more than configured hours ago

When:

- public user opens livestream link

Then:

- access denied
- expired message shown

#### LS-03: Manual livestream stop

Given:

- livestream is active

When:

- coach stops livestream

Then:

- livestream_stopped_at is set
- public link becomes inaccessible

#### LS-04: Correction visible in active livestream

Given:

- match finished
- livestream still active
- score corrected

When:

- public livestream refreshes

Then:

- corrected score is shown

---

## 15. Audit Integrity

### 15.1 Audit Rules

Finished match corrections must create audit log entries.

Audit record includes:

- entity type
- entity id
- match id
- user id
- field name
- old value
- new value
- timestamp

### 15.2 Test Scenarios

#### AU-01: Finished match event correction

Given:

- finished match
- coach changes scorer

When:

- correction is saved

Then:

- audit log entry is created
- old scorer and new scorer are stored

#### AU-02: Unauthorized correction blocked

Given:

- finished match
- user has trainer role

When:

- trainer attempts correction

Then:

- action is rejected
- no audit entry is created
- source data unchanged

#### AU-03: Substitution correction logged

Given:

- finished match
- coach changes a stored substitution

When:

- correction is saved

Then:

- substitution source data is updated
- derived lineup or playing-time caches are recalculated as needed
- audit log entry is created

---

## 16. Lock Integrity

### 16.1 Lock Rules

- one active editor per match
- lock timeout: 2 minutes
- refresh interval: 30 seconds
- no silent overwrite

### 16.2 Test Scenarios

#### LK-01: Acquire free lock

Given:

- match has no lock

When:

- coach opens edit mode

Then:

- lock is assigned to coach

#### LK-02: Second user blocked

Given:

- coach A holds active lock

When:

- coach B opens edit mode

Then:

- coach B is blocked or read-only

#### LK-03: Expired lock replaced

Given:

- lock is older than 2 minutes

When:

- coach B opens edit mode

Then:

- lock is reassigned to coach B

#### LK-04: Lock owner refreshes

Given:

- coach A owns lock

When:

- refresh request succeeds

Then:

- locked_at is updated

---

## 17. Security Integrity

### 17.1 Security Rules

The application must enforce:

- prepared statements
- CSRF protection
- secure sessions
- server-side authorization
- output escaping
- maximum length validation for free-text fields
- neutral magic link responses
- one-time login tokens
- token expiration

### 17.2 Test Scenarios

#### SE-01: CSRF missing

Given:

- authenticated user

When:

- user submits POST without CSRF token

Then:

- request is rejected

#### SE-02: Unauthorized role

Given:

- trainer role

When:

- trainer submits match start request

Then:

- request is rejected

#### SE-03: XSS input

Given:

- user enters script tag in note

When:

- note is displayed

Then:

- script is escaped and not executed

#### SE-04: SQL injection attempt

Given:

- user submits malicious SQL string in opponent name

When:

- match is saved

Then:

- value is stored or rejected safely
- no SQL execution occurs

#### SE-05: Free-text length limit

Given:

- user submits a value longer than the documented maximum for a free-text field

When:

- the form is submitted

Then:

- request is rejected with a validation error
- no truncated or partial value is silently stored
- previously stored value remains unchanged

Example:

- opponent name longer than 120 characters
- live match note longer than 500 characters
- email longer than 254 characters

#### SE-06: Optional empty text normalization

Given:

- user submits an optional free-text field containing only whitespace

When:

- the value is validated and saved

Then:

- the value is normalized to `NULL`
- no whitespace-only string is stored

---

## 18. Regression Test Checklist

Before each release, verify:

- login works
- team context works
- player creation works
- match creation works
- match preparation validation works
- match start works
- goal registration updates score
- missed penalty does not update score
- substitution updates lineup
- red card blocks re-entry
- penalty shootout score remains separate
- livestream starts and expires
- finished match correction logs audit
- lock conflict prevents overwrite
- unauthorized actions fail
- CSRF protection works
- output escaping works
- free-text length limits are enforced server-side
- optional empty text normalization works

---

## 19. MVP Acceptance Scenario

The MVP is acceptable when this full scenario succeeds:

1. Admin creates club
2. Admin creates season
3. Admin creates phase
4. Admin creates team
5. Admin creates user
6. Admin assigns coach role
7. Coach logs in
8. Coach selects team
9. Coach creates players
10. Coach creates match
11. Coach marks 11 players present
12. Coach selects formation
13. Coach fills lineup
14. Coach prepares match
15. Coach starts match
16. Coach registers own goal
17. Coach registers opponent goal
18. Coach performs substitution
19. Coach registers yellow card
20. Coach registers red card
21. Coach finishes match
22. Coach corrects assist
23. Audit log is created
24. Livestream shows corrected result while active
25. Statistics reflect correct result

No manual database correction is allowed during this scenario.

---

## 20. Summary

Data integrity in BarePitch depends on:

- clear source of truth
- transactional writes
- strict state transitions
- server-side authorization
- recalculation from source data
- lock-based edit protection
- audit logging
- explicit tests for edge cases

The system is reliable only when the test scenarios in this document pass.

---

# End
