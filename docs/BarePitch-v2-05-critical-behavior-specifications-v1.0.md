# BarePitch — Critical Behavior Specifications
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the behavior specifications for the most critical BarePitch modules.

This document is the source of truth for live match and correction behavior.

Route documents and test documents must follow these rules rather than redefine them independently.

The goal is to remove interpretation from high-risk areas of the application.

This document focuses on:
- match state transitions
- live match control
- goal registration
- penalty registration
- penalty shootout logic
- lineup behavior
- substitutions
- cards
- livestream synchronization
- finished match corrections
- locking and concurrency
- period behavior as part of match flow

BarePitch principle:

> BarePitch shows what matters, when it matters. Nothing more.

---

## 2. General Behavior Rules

### 2.1 Server Authority

The server is the source of truth for:
- match status
- match phase
- score
- events
- lineup state
- playing time
- permissions
- locks

Client-side display may update optimistically only when the server confirms success.

---

### 2.2 Write Safety

Every write action must validate:
- authenticated user
- user role
- team access
- match status
- match lock
- input validity

No write action may rely only on the frontend.

Authorization should be enforced before any lock-dependent mutation logic.

Locks do not elevate privileges.

---

### 2.3 Transaction Rule

Every action that changes match state, score, lineup, events, playing time, or penalty shootout data must run in a database transaction.

If any step fails:
- rollback
- return safe error
- do not partially update the match

---

## 3. Match State Machine

---

### 3.1 States

Allowed match states:

- `planned`
- `prepared`
- `active`
- `finished`

---

### 3.2 planned

Meaning:
- match exists
- match is not ready for kickoff
- lineup may be incomplete
- attendance may be incomplete
- no live events exist

Allowed actions:
- edit match details
- set attendance
- add guest players
- choose formation
- build lineup
- delete match, if permitted

Not allowed:
- start match
- register live events
- start livestream

---

### 3.3 planned → prepared

Triggered by:
- coach confirms preparation

Required conditions:
- at least 11 present players
- present players do not exceed team maximum
- formation is selected
- all starting positions are filled
- every starting player is present
- no starting player is marked injured
- no starting player is unavailable due to red card from this match

System behavior:
1. Validate conditions
2. Set match status to `prepared`
3. Assign all non-starting present players to bench
4. Keep lineup editable until match start
5. Do not start livestream
6. Do not allow event registration yet

Failure behavior:
- keep status `planned`
- return specific validation errors

---

### 3.4 prepared

Meaning:
- match is ready
- lineup is valid
- kickoff has not happened

Allowed actions:
- edit lineup
- edit attendance
- add or remove guest players
- change formation
- start match

Not allowed:
- register goals
- register cards
- register substitutions
- register live notes
- show livestream as active

Important rule:
- the lineup at the moment of match start becomes the actual starting lineup

---

### 3.5 prepared → active

Triggered by:
- coach starts match

Recommended interaction:
- swipe action
- confirmation

System behavior:
1. Validate coach role
2. Validate match lock
3. Validate prepared status
4. Start transaction
5. Set status to `active`
6. Set active phase to `regular_time`
7. Create or activate regular period 1
8. Set period start time
9. Set score to 0–0
10. Set livestream started timestamp
11. Generate high-entropy livestream token if missing
12. Commit transaction

Result:
- live events become available
- livestream becomes available
- timer begins from 0

---

### 3.6 active

Meaning:
- match is in progress
- live actions are available

Allowed actions:
- register goals
- register penalties
- register cards
- register substitutions
- add notes
- end current period
- start next period
- start extra time
- start penalty shootout
- finish match, when allowed

---

### 3.7 active → finished

Triggered by:
- coach explicitly finishes match

Allowed only:
- after regular time, if no extra time or penalties are selected
- after extra time, if no penalties are selected
- after penalty shootout ends

System behavior:
1. Validate allowed finish point
2. Confirm action
3. Start transaction
4. Set status to `finished`
5. Set finished_at
6. Set livestream_expires_at = finished_at + configured hours
7. Release match lock
8. Commit

---

### 3.8 finished

Meaning:
- match is closed
- live flow has ended
- match remains correctable by authorized users

Allowed actions:
- view match
- view events
- view statistics
- correct match data, coach and administrator only

Not allowed:
- restart live flow
- add normal live actions as if match were active
- return automatically to active status

---

## 4. Period Behavior

Period records are explicit data entities.

The system must persist enough data to distinguish:
- regular period 1
- regular period 2
- halftime
- extra-time period 1
- extra-time period 2
- penalty shootout phase

---

### 4.1 Regular Time

Regular time consists of:
- period 1
- period 2

Both periods:
- start manually
- end manually
- require confirmation

The configured duration is informational.

The system does not automatically end a period.

---

### 4.2 End First Half

Triggered by:
- coach ends period 1

Recommended interaction:
- swipe
- confirmation

System behavior:
1. Validate active regular period 1
2. Set period 1 ended_at
3. Stop playing time accumulation
4. Keep match status `active`
5. Enter halftime state

---

### 4.3 Start Second Half

Triggered by:
- coach starts period 2

System behavior:
1. Validate period 1 ended
2. Validate period 2 not started
3. Set period 2 started_at
4. Resume playing time accumulation for active field players

---

### 4.4 End Second Half

Triggered by:
- coach ends period 2

System behavior:
1. Set period 2 ended_at
2. Stop playing time accumulation
3. Present choices:
   - finish match
   - start extra time
   - start penalty shootout

---

## 5. Extra Time Behavior

---

### 5.1 Start Extra Time

Allowed only:
- after regular time has ended
- while match status is `active`

System behavior:
1. Set active phase to `extra_time`
2. Create or activate extra-time period 1
3. Set configured extra-time duration
4. Start extra-time period 1 manually

---

### 5.2 Extra-Time Periods

Extra time consists of:
- extra-time period 1
- extra-time period 2

Both:
- start manually
- end manually
- require confirmation

---

### 5.3 End Extra Time

After extra-time period 2 ends, show choices:
- finish match
- start penalty shootout

---

## 6. Goal Registration Behavior

---

### 6.1 Goal Types

BarePitch distinguishes:

1. regular goal
2. penalty scored during match
3. penalty missed during match
4. penalty shootout attempt

Only the first three belong to live match events.

Penalty shootout attempts use separate shootout logic.

---

### 6.2 Regular Goal Flow

Triggered by:
- coach selects goal action

Flow:
1. Open goal registration interface
2. Default team side = own team
3. Coach selects scoring team
4. Coach selects scorer
5. Coach optionally selects assist
6. Coach optionally selects scoring zone
7. Coach confirms
8. Server validates
9. Event is stored
10. Score cache is recalculated
11. Timeline updates
12. Livestream reflects change on next refresh

---

### 6.3 Eligible Scorers

For own team:
- active field players are selectable by default
- bench players may be selectable only when correcting after finished match
- sent-off players are not selectable for new active events after red card time

For opponent:
- player selection may be omitted
- event may be registered as opponent goal without player identity

---

### 6.4 Assist Rules

Assist is:
- optional
- only available for regular goals
- never available for penalties
- never available for penalty shootout attempts

Eligible assist players:
- own active field players
- cannot be the same as scorer

If assist is unknown:
- event is saved without assist

Assist may be added or corrected later by coach or administrator.

---

### 6.5 Goal Zone Grid

Goal zone selection uses a 3 × 3 matrix.

Codes:

- `tl` = top left
- `tm` = top middle
- `tr` = top right
- `ml` = middle left
- `mm` = middle middle
- `mr` = middle right
- `bl` = bottom left
- `bm` = bottom middle
- `br` = bottom right

Rules:
- zone is optional for regular goals
- zone is required only if the application chooses to enforce it for specific statistics
- zone must be stored when selected
- zone may be edited later

Recommended behavior:
- allow saving without zone
- encourage zone selection visually without blocking speed

---

### 6.6 Score Recalculation

After saving a goal:
- recalculate regular match score from `match_event`
- update cached score fields on `match`

Do not update score by blind increment only.

---

## 7. Penalty During Match Behavior

---

### 7.1 Penalty Event Types

Penalty during match may result in:
- scored
- missed

Both must be registerable.

---

### 7.2 Penalty Flow

Triggered by:
- coach selects penalty action

Flow:
1. Select team side
2. Select taker
3. Select outcome: scored or missed
4. If scored, optionally select goal zone
5. Confirm
6. Store event
7. Recalculate score if scored
8. Update timeline
9. Livestream updates on next refresh

---

### 7.3 Penalty Assist Rule

No assist is available for penalties.

---

### 7.4 Missed Penalty Rule

A missed penalty:
- is stored as an event
- does not change score
- appears in timeline
- appears in livestream if match event visibility includes penalties

---

## 8. Penalty Shootout Behavior

---

### 8.1 Purpose

Penalty shootout decides a match after regular time or extra time.

Shootout does not change:
- regular score
- extra-time score
- normal goal statistics

Shootout has separate score fields.

---

### 8.2 Start Conditions

Penalty shootout may start:
- after regular time
- after extra time

Allowed only when:
- match status is `active`
- active regular or extra-time periods are ended
- coach confirms

---

### 8.3 Attempt Flow

For each attempt:

1. Select team side
2. Select taker
3. Select outcome:
   - scored
   - missed
4. If scored, optionally select zone
5. Confirm attempt
6. Store attempt order
7. Store round number
8. Recalculate shootout score
9. Check if shootout is mathematically decided
10. Continue or present ending option

---

### 8.4 Taker Selection

Own team:
- selectable from match selection
- sent-off players are not selectable

Opponent:
- player identity may be entered as text
- player identity may be left blank if unknown

---

### 8.5 Shootout Order

System stores:
- absolute attempt order
- round number
- team side
- sudden death marker

---

### 8.6 Automatic Ending

System may determine shootout is decided when:
- one team can no longer equal the other within remaining standard attempts
- or sudden death produces a lead after equal attempts

When automatically decided:
- system shows confirmation
- coach must confirm ending
- match does not finish silently

---

### 8.7 Manual Ending

Coach may manually end shootout.

Manual ending requires confirmation.

Manual ending is allowed to handle:
- unusual referee decisions
- local competition rules
- data entry interruptions

---

## 9. Substitution Behavior

---

### 9.1 Substitution Flow

Triggered by:
- coach selects substitution action
- or coach selects player on field and chooses substitute

Flow:
1. Select outgoing player
2. Select incoming player
3. Confirm substitution
4. Store substitution record
5. Update current lineup
6. Update playing time
7. Update bench state
8. Add timeline entry
9. Livestream updates on next refresh

---

### 9.2 Eligible Outgoing Players

Outgoing player must:
- be active on field
- not already sent off
- belong to match selection

---

### 9.3 Eligible Incoming Players

Incoming player must:
- be on bench
- be present in match selection
- not sent off
- have `can_reenter = true`

---

### 9.4 Substitution Effects

After substitution:
- outgoing player becomes bench player
- incoming player becomes active field player
- incoming player takes selected grid position
- outgoing player's playing time stops
- incoming player's playing time starts

---

### 9.5 Position Handling

Coach may:
- replace player in same position
- move incoming player to another grid slot
- adjust lineup after substitution

System must maintain:
- no duplicate player on field
- no duplicate grid occupancy unless deliberately allowed by app behavior

Recommended:
- prevent duplicate grid occupancy

---

## 10. Red Card Behavior

---

### 10.1 Red Card Flow

Triggered by:
- coach registers red card

Flow:
1. Select player
2. Confirm red card
3. Store red card event
4. Set player as sent off
5. Set `can_reenter = false`
6. Remove player from active field state
7. Move player to bench context
8. Stop playing time
9. Reduce field count by one
10. Update timeline
11. Livestream updates on next refresh

---

### 10.2 Red Card Restrictions

A sent-off player may not:
- re-enter regular time
- re-enter extra time
- participate in penalty shootout
- be selected as incoming substitute
- be selected as penalty shootout taker

---

### 10.3 Lineup Effect

After red card:
- no replacement player is added automatically
- the field count is reduced
- lineup grid keeps empty space unless coach rearranges players

---

## 11. Yellow Card Behavior

---

### 11.1 Yellow Card Flow

Flow:
1. Select yellow card action
2. Select team side
3. Select player if own team
4. Confirm
5. Store event
6. Update timeline
7. Livestream updates on next refresh

---

### 11.2 Yellow Card Rules

Yellow cards:
- do not alter lineup
- do not alter playing time
- may be corrected later by coach or administrator

---

## 12. Lineup Behavior

---

### 12.1 Current State Only

BarePitch stores only the current lineup state.

It does not store:
- historical lineup snapshots
- minute-by-minute field positions

It does store:
- substitutions
- match events
- active field state

---

### 12.2 Grid Rules

Grid:
- 10 rows
- 11 columns

Rules:
- field players have grid row and grid column
- bench players have no grid coordinates
- sent-off players have no active grid coordinates

---

### 12.3 Prepared State Editing

Before match start:
- coach may freely change lineup
- coach may change formation
- coach may move players
- coach may replace starters

The lineup at match start becomes the actual start lineup.

---

### 12.4 Active State Editing

During active match:
- coach may adjust positions
- coach may execute substitutions
- coach may rearrange remaining players after red card

All changes update current lineup state.

---

## 13. Playing Time Behavior

---

### 13.1 Storage

Playing time is stored in seconds.

UI displays minutes.

---

### 13.2 Start Rules

Starters:
- begin playing time at match start

Substitutes:
- begin playing time when substituted in

---

### 13.3 Stop Rules

Playing time stops when:
- player is substituted off
- player receives red card
- active period ends

---

### 13.4 Extra Time

Extra time counts toward total playing time.

---

### 13.5 Bench Players

Players who never enter:
- playing time = 0

---

## 14. Livestream Synchronization Behavior

---

### 14.1 Start

Livestream starts when match becomes active.

Before active:
- livestream link may exist
- livestream must not show live match as started

If token compromise is suspected:
- current token should be invalidatable
- a replacement token may be generated
- the old token must stop granting access immediately

---

### 14.2 Update Mechanism

Livestream uses polling.

Recommended interval:
- 60 seconds

Public livestream endpoints should be rate-limited.

Coach interface may update immediately after server confirmation.

---

### 14.3 Livestream Content

Livestream shows:
- team names
- score
- current phase
- timeline
- key events
- current lineup if included by product choice

Livestream must not show:
- ratings
- private notes if marked internal
- attendance data
- statistics dashboard

Public livestream responses should send:
- no-store cache policy
- no-referrer policy
- noindex search-engine directive

---

### 14.4 Expiration

After match finish:
- livestream remains active for configured duration
- default 24 hours
- maximum 72 hours

After expiration:
- livestream access denied
- internal match data remains available

Failure responses should remain generic and should not reveal whether a token ever existed.

---

### 14.5 Manual Stop

Coach may stop livestream earlier.

After manual stop:
- public link is no longer accessible
- internal match data remains unchanged

Manual stop may be followed by explicit token rotation if the product chooses to allow a new public link later.

---

### 14.6 Corrections

While livestream is active:
- corrections are reflected
- score and timeline must show corrected data

---

## 15. Finished Match Correction Behavior

---

### 15.1 Permissions

Finished match corrections are allowed only for:
- coach of the team
- administrator

---

### 15.2 Editable Items

Allowed corrections:
- scorer
- assist
- goal zone
- penalty outcome
- card assignment
- note text
- substitution data
- shootout attempt
- final score through source events

Final score is never edited directly.

It changes only through source-event or source-attempt correction and recalculation.

---

### 15.3 Correction Process

Flow:
1. User selects correction
2. System checks permission
3. System checks match status
4. System acquires lock
5. User edits value
6. System validates
7. Transaction starts
8. Source data updates
9. Cached score recalculates
10. Audit log is written
11. Transaction commits
12. Match remains finished

Any lock failure or authorization failure must leave source data unchanged.

---

### 15.4 Audit Logging

Every correction must log:
- entity type
- entity id
- match id
- user id
- field name
- old value
- new value
- timestamp

---

## 16. Locking and Concurrency Behavior

---

### 16.1 Lock Scope

Lock applies to:
- match editing
- live match control
- finished match corrections

---

### 16.2 Lock Acquisition

When editing starts:
1. check existing lock
2. if no lock, acquire
3. if expired, replace
4. if owned by same user, refresh
5. otherwise deny edit

---

### 16.3 Timeout

Recommended timeout:
- 2 minutes

Timeouts should be short enough to limit stale editor possession but long enough to survive normal mobile interaction.

---

### 16.4 Refresh

During active editing:
- refresh every 30 seconds

Lock refresh routes must also verify the current authenticated user still has permission to edit the match.

---

### 16.5 Conflict Behavior

If another user holds lock:
- do not allow edit
- show read-only mode if useful
- show clear message

No silent overwrite is allowed.

---

## 17. Attendance Calculation Behavior

---

### 17.1 Statuses

Allowed:
- present
- absent
- injured

---

### 17.2 Calculation Rule

Attendance percentage excludes injured sessions from denominator.

Formula:

present / (present + absent)

Injured is not included.

Cancelled training sessions are excluded.

---

### 17.3 Match Attendance

Match attendance is used for:
- selection
- eligibility
- participation context

It does not automatically equal playing time.

---

## 18. Rating Calculation Behavior

---

### 18.1 Completion Rule

Rating counts only when all fields are filled.

Required fields:
- pace
- shooting
- passing
- dribbling
- defending
- physicality

---

### 18.2 Partial Rating Rule

Partial ratings:
- may be stored
- do not count in averages
- must have `is_complete = false`

---

### 18.3 Average Rule

Average ratings use:
- only complete ratings
- current filter period

Recommended display:
- rounded whole stars

---

## 19. Guest Player Lifecycle Behavior

---

### 19.1 Internal Guest Player

Internal guest player:
- belongs to another team within same club
- has player season context with another team
- is selected into match as guest

---

### 19.2 External Guest Player

External guest player:
- has no team in player season context
- can be created during match preparation
- requires name
- squad number optional
- profile fields optional

---

### 19.3 Guest Player Selection

During match preparation:
1. coach chooses add guest player
2. system shows internal guest players
3. system shows existing external guest players
4. coach may create new external guest player
5. selected guest appears in match selection

---

### 19.4 Guest Player Statistics

Guest player match data:
- counts in match records
- can be included in player history
- remains marked as guest use

---

## 20. Error Handling Behavior

---

### 20.1 Validation Errors

Validation errors must:
- be specific
- be safe
- not expose internals

Public token-based endpoints are an exception and should prefer generic unavailable messaging over existence-revealing detail.

Examples:
- lineup is incomplete
- player limit exceeded
- player cannot re-enter after red card
- match is locked by another user

---

### 20.2 System Errors

System errors must:
- rollback transactions
- log technical details server-side
- show safe message to user

Authentication delivery failures and public token endpoint failures should remain non-revealing in user-facing output.

---

## 21. Summary

These behavior specifications define the exact behavior of critical BarePitch modules.

They exist to prevent ambiguity in:
- live match flow
- scoring
- substitutions
- cards
- penalty shootouts
- livestream
- corrections
- locking
- calculations

The expected implementation standard is:

- explicit
- safe
- minimal
- predictable

Nothing more.

---

# End
