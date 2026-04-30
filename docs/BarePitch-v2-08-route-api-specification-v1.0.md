# BarePitch — Route and API Specification
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the route and API structure for BarePitch.

This document owns endpoint contracts only.

Match behavior, state transitions, and correction rules must follow `BarePitch-v2-05-critical-behavior-specifications-v1.0.md`.

BarePitch is a lightweight PHP/MySQL web application using:
- server-rendered HTML pages
- POST routes for state changes
- small JSON endpoints only where needed
- no full single-page application architecture

This specification defines:
- route paths
- HTTP methods
- purpose
- required roles
- request data
- response behavior
- validation rules
- redirect behavior
- JSON response behavior

---

## 2. General Routing Principles

---

### 2.1 Server-rendered by Default

Most routes return HTML.

JSON endpoints are used only for:
- livestream polling
- lock refresh
- live match partial updates where useful
- asynchronous validation where needed

---

### 2.2 Method Rules

Use:

- `GET` for reading pages
- `POST` for state changes
- no destructive changes through `GET`

BarePitch does not require `PUT`, `PATCH`, or `DELETE` in the MVP.

Reason:
- simpler PHP form handling
- better shared hosting compatibility
- easier CSRF handling

---

### 2.3 CSRF Protection

All `POST` routes require a valid CSRF token.

Exceptions:
- public livestream read endpoints
- magic link callback route if implemented as `GET`

CSRF tokens should be bound to the authenticated session and rotated using the application's normal session lifecycle.

---

### 2.4 Authentication

All routes require authentication unless explicitly marked public.

Public routes:
- login form
- login request
- magic link callback
- public livestream view
- public livestream JSON endpoint

Public token-based routes must use generic failure responses and rate limiting.

---

### 2.5 Authorization

Authorization is always checked server-side.

Every write route must validate:
- authenticated user
- role
- team access
- resource ownership
- match status where relevant

---

### 2.6 Response Patterns

For HTML form routes:

On success:
- redirect to relevant page
- flash success message if useful

On validation failure:
- return same page
- show validation errors
- preserve submitted data where safe

On authorization failure:
- return 403 page or redirect with error

On missing resource:
- return 404 page

For JSON routes:

Success response:
```json
{
  "ok": true,
  "data": {}
}
```

Error response:
```json
{
  "ok": false,
  "error": {
    "code": "validation_failed",
    "message": "The lineup is incomplete."
  }
}
```

---

### 2.7 Text Field Length Validation

Every route accepting free-text input must enforce the canonical text limits from `BarePitch-v2-01-domain-model-and-schema-v1.0.md`.

Required enforcement layers:
- HTML form `maxlength` where applicable
- server-side request validation
- database column length or explicit storage constraint

Default route-level limits:

| Field category | Max length |
|---|---:|
| Person first name | 80 |
| Person last name | 80 |
| Person display name | 120 |
| Names and labels | 120 |
| Opponent name | 120 |
| Email address | 254 |
| Locale key | 10 |
| Short notes and live match notes | 500 |
| Long internal/admin notes | 2000 |

Validation behavior:
- trim user-submitted free text before validation unless a field explicitly preserves whitespace
- reject values that exceed the configured length
- normalize optional empty text fields to `NULL`
- escape output when rendering stored text
- do not rely on UI `maxlength` as the only enforcement

---

## 3. Route Naming Conventions

Routes use simple resource-oriented paths.

Examples:
- `/matches`
- `/matches/create`
- `/matches/{match_id}`
- `/matches/{match_id}/prepare`
- `/matches/{match_id}/live`
- `/players/{player_id}/edit`

Route parameters:
- use integer IDs
- must be validated server-side
- must never be trusted from URL alone

---

## 4. Authentication Routes

---

### GET /login

Purpose:
- show login form

Access:
- public

Response:
- HTML login page

---

### POST /login

Purpose:
- request magic login link

Access:
- public

Required fields:
- `email`
- `csrf_token`

Behavior:
1. Validate email format and 254-character maximum length
2. If email belongs to active user, create magic link
3. Send email
4. Always show neutral response

Security requirements:
- rate-limit by IP address
- rate-limit by email identifier
- invalidate or supersede older unused tokens for the same user
- store only a hash of the token server-side
- use cryptographically secure token generation

Success message:
- If this address is known, you will receive a login link shortly.

Security:
- do not reveal whether email exists

---

### GET /auth/magic

Purpose:
- consume magic login token

Access:
- public

Query parameters:
- `token`

Behavior:
1. Hash incoming token
2. Find unused valid token
3. Validate expiration
4. Mark token used
5. Regenerate session
6. Log user in
7. Redirect to a clean URL without the token

Failure:
- show safe invalid or expired link message

Security:
- token must be single-use
- token must expire after a short period
- repeated failed token consumption attempts should be rate-limited

If token delivery or consumption fails, the user-facing response must remain non-revealing.

---

### POST /logout

Purpose:
- log user out

Access:
- authenticated

Required fields:
- `csrf_token`

Behavior:
- destroy session
- redirect to login
- invalidate the server-side session immediately

---

## 5. Dashboard Routes

---

### GET /

Purpose:
- show dashboard for active team

Access:
- authenticated
- user must have at least one team role or be administrator

Response:
- HTML dashboard

Shows:
- active team context
- next match
- next training
- recent result
- incomplete preparation warning where relevant

---

### POST /context/team

Purpose:
- switch active team context

Access:
- authenticated

Required fields:
- `team_id`
- `csrf_token`

Validation:
- user must have access to team
- administrator may access all teams

Success:
- store active team in session
- redirect back or dashboard

---

## 6. Club Routes

MVP usage:
- administrator only

---

### GET /admin/clubs

Purpose:
- list clubs

Access:
- administrator

Response:
- HTML club list

---

### GET /admin/clubs/create

Purpose:
- show create club form

Access:
- administrator

---

### POST /admin/clubs

Purpose:
- create club

Access:
- administrator

Required fields:
- `name`
- `csrf_token`

Validation:
- name required
- name unique
- name max 120 characters

Success:
- redirect to club list

---

### GET /admin/clubs/{club_id}/edit

Purpose:
- edit club form

Access:
- administrator

---

### POST /admin/clubs/{club_id}/update

Purpose:
- update club

Access:
- administrator

Required fields:
- `name`
- `active`
- `csrf_token`

---

## 7. Season Routes

---

### GET /admin/seasons

Purpose:
- list seasons

Access:
- administrator

---

### GET /admin/seasons/create

Purpose:
- show create season form

Access:
- administrator

---

### POST /admin/seasons

Purpose:
- create season

Access:
- administrator

Required fields:
- `label`
- `starts_on`
- `ends_on`
- `csrf_token`

Validation:
- label required
- date range valid
- label unique
- label max 120 characters

---

### GET /admin/seasons/{season_id}/edit

Purpose:
- edit season form

Access:
- administrator

---

### POST /admin/seasons/{season_id}/update

Purpose:
- update season

Access:
- administrator

---

## 8. Phase Routes

---

### GET /admin/seasons/{season_id}/phases

Purpose:
- list phases for season

Access:
- administrator

---

### GET /admin/seasons/{season_id}/phases/create

Purpose:
- create phase form

Access:
- administrator

---

### POST /admin/seasons/{season_id}/phases

Purpose:
- create phase

Access:
- administrator

Required fields:
- `number`
- `label`
- `starts_on`
- `ends_on`
- `csrf_token`

Validation:
- number unique within season
- dates valid
- label max 120 characters

---

### GET /admin/phases/{phase_id}/edit

Purpose:
- edit phase

Access:
- administrator

---

### POST /admin/phases/{phase_id}/update

Purpose:
- update phase

Access:
- administrator

---

## 9. Team Routes

---

### GET /teams

Purpose:
- list accessible teams

Access:
- authenticated

Response:
- teams available to user

Administrator:
- sees all teams

Regular user:
- sees assigned teams only

---

### GET /admin/teams/create

Purpose:
- create team form

Access:
- administrator

---

### POST /admin/teams

Purpose:
- create team

Access:
- administrator

Required fields:
- `club_id`
- `season_id`
- `name`
- `max_match_players`
- `livestream_hours_after_match`
- `csrf_token`

Validation:
- club exists
- season exists
- name unique within club and season
- name max 120 characters
- max players at least 11
- livestream hours between 1 and 72

---

### GET /teams/{team_id}

Purpose:
- show team overview

Access:
- team role or administrator

Shows:
- players
- next match
- next training
- team settings summary

---

### GET /teams/{team_id}/edit

Purpose:
- edit team settings

Access:
- administrator
- team manager for limited team settings

---

### POST /teams/{team_id}/update

Purpose:
- update team settings

Access:
- administrator
- team manager for allowed fields

Allowed team manager fields:
- display name where permitted
- training days
- max match players if permitted by app policy
- livestream hours if permitted by app policy

---

## 10. User and Role Routes

---

### GET /admin/users

Purpose:
- list users

Access:
- administrator

---

### GET /admin/users/create

Purpose:
- create user form

Access:
- administrator

---

### POST /admin/users

Purpose:
- create user

Access:
- administrator

Required fields:
- `first_name`
- `last_name`
- `email`
- `locale`
- `is_administrator`
- `csrf_token`

Validation:
- first_name max 80 characters
- last_name max 80 characters
- email max 254 characters
- email unique
- locale max 10 characters
- locale supported

---

### GET /admin/users/{user_id}/edit

Purpose:
- edit user

Access:
- administrator

---

### POST /admin/users/{user_id}/update

Purpose:
- update user

Access:
- administrator

---

### GET /teams/{team_id}/roles

Purpose:
- show team role assignments

Access:
- administrator
- team manager

---

### POST /teams/{team_id}/roles

Purpose:
- assign role to user for team

Access:
- administrator
- team manager

Required fields:
- `user_id`
- `role_key`
- `csrf_token`

Validation:
- user exists
- role_key in allowed values
- team manager cannot assign administrator
- role assignment unique

---

### POST /teams/{team_id}/roles/{role_id}/remove

Purpose:
- remove team role

Access:
- administrator
- team manager

Validation:
- role belongs to team

---

## 11. Player Routes

---

### GET /players

Purpose:
- list players for active team

Access:
- team role or administrator

Response:
- HTML player list

---

### GET /players/create

Purpose:
- create player form

Access:
- administrator
- team manager

---

### POST /players

Purpose:
- create player for active team

Access:
- administrator
- team manager

Required fields:
- `first_name`
- `last_name`
- `squad_number`
- `preferred_foot`
- `preferred_line`
- `csrf_token`

Validation:
- first_name required
- first_name max 80 characters
- last_name max 80 characters
- preferred_foot valid if provided
- preferred_line valid if provided
- squad_number valid if provided

Behavior:
1. create player
2. create player profile if fields provided
3. create player season context for active team

---

### GET /players/{player_id}

Purpose:
- show player profile

Access:
- team role or administrator

Shows:
- player details
- season context
- basic statistics
- historical summary where implemented

---

### GET /players/{player_id}/edit

Purpose:
- edit player form

Access:
- administrator
- team manager

---

### POST /players/{player_id}/update

Purpose:
- update player

Access:
- administrator
- team manager

---

### POST /players/{player_id}/deactivate

Purpose:
- deactivate player

Access:
- administrator
- team manager

Behavior:
- soft deactivate
- do not remove historical data

---

## 12. External Guest Player Routes

---

### GET /guest-players/create

Purpose:
- create external guest player form

Access:
- coach
- team manager
- administrator

---

### POST /guest-players

Purpose:
- create reusable external guest player

Access:
- coach
- team manager
- administrator

Required fields:
- `first_name`
- `last_name`
- `squad_number`
- `preferred_line`
- `preferred_foot`
- `csrf_token`

Rules:
- first_name required
- first_name max 80 characters
- last_name max 80 characters
- team_id in season context is NULL
- profile fields optional

---

## 13. Training Routes

---

### GET /trainings

Purpose:
- list training sessions for active team

Access:
- team role or administrator

---

### GET /trainings/create

Purpose:
- create training form

Access:
- trainer
- team manager
- administrator

---

### POST /trainings

Purpose:
- create training session

Access:
- trainer
- team manager
- administrator

Required fields:
- `phase_id`
- `starts_at`
- `focus[]`
- `notes`
- `csrf_token`

Validation:
- phase belongs to active team season
- focus values valid
- notes max 500 characters

---

### GET /trainings/{training_id}

Purpose:
- show training detail

Access:
- team role or administrator

---

### GET /trainings/{training_id}/edit

Purpose:
- edit training

Access:
- trainer
- team manager
- administrator

---

### POST /trainings/{training_id}/update

Purpose:
- update training

Access:
- trainer
- team manager
- administrator

---

### POST /trainings/{training_id}/cancel

Purpose:
- cancel training

Access:
- trainer
- team manager
- administrator

---

### POST /trainings/{training_id}/attendance

Purpose:
- save training attendance

Access:
- trainer
- team manager
- administrator

Required fields:
- `attendance[player_id][status]`
- `attendance[player_id][absence_reason]`
- `attendance[player_id][injury_note]`
- `csrf_token`

Validation:
- status valid
- absence reason only required for absent where configured
- injury note optional

---

## 14. Match Routes

---

### GET /matches

Purpose:
- list matches for active team

Access:
- team role or administrator

Behavior:
- timeline view
- upcoming and past matches
- phase grouping

---

### GET /matches/create

Purpose:
- create match form

Access:
- coach
- administrator

---

### POST /matches

Purpose:
- create match

Access:
- coach
- administrator

Required fields:
- `phase_id`
- `date`
- `kick_off_time`
- `opponent`
- `home_away`
- `match_type`
- `regular_half_duration_minutes`
- `extra_time_half_duration_minutes`
- `csrf_token`

Validation:
- phase belongs to active team season
- opponent required
- opponent max 120 characters
- home_away valid
- match_type valid

---

### GET /matches/{match_id}

Purpose:
- show match detail

Access:
- team role or administrator

Response depends on status:
- planned: preparation entry
- prepared: preparation overview
- active: live match link
- finished: summary

---

### GET /matches/{match_id}/edit

Purpose:
- edit match metadata

Access:
- coach
- administrator

Allowed when:
- status is `planned` or `prepared`
- finished correction rules apply if status is `finished`

---

### POST /matches/{match_id}/update

Purpose:
- update match metadata

Access:
- coach
- administrator

Validation:
- match lock if editing
- status-specific allowed fields

---

### POST /matches/{match_id}/delete

Purpose:
- delete match

Access:
- coach
- administrator

Allowed:
- only when no live events exist

Recommended:
- prefer soft delete if historical risk exists

---

## 15. Match Preparation Routes

---

### GET /matches/{match_id}/prepare

Purpose:
- show match preparation screen

Access:
- coach
- administrator

Allowed:
- status `planned`
- status `prepared`

Shows:
- attendance
- guest player selector
- formation selector
- lineup grid
- bench list

---

### POST /matches/{match_id}/attendance

Purpose:
- save match attendance

Access:
- coach
- team manager
- administrator

Required fields:
- `attendance[player_id][status]`
- `attendance[player_id][absence_reason]`
- `attendance[player_id][injury_note]`
- `csrf_token`

Rules:
- coach may manage match attendance
- team manager may manage attendance
- trainer may not manage match attendance

---

### POST /matches/{match_id}/guest-players/add

Purpose:
- add guest player to match selection

Access:
- coach
- administrator

Required fields:
- `player_id`
- `guest_type`
- `shirt_number`
- `csrf_token`

Validation:
- player exists
- internal guest must belong to same club
- external guest must have no team context
- player not already selected for match

---

### POST /matches/{match_id}/formation

Purpose:
- set match formation

Access:
- coach
- administrator

Required fields:
- `formation_id`
- `csrf_token`

Validation:
- formation exists

Behavior:
- formation positions become required starting positions

---

### POST /matches/{match_id}/lineup

Purpose:
- save lineup grid state

Access:
- coach
- administrator

Required fields:
- `slots[position_id][match_selection_id]`
- `csrf_token`

Validation:
- players belong to match selection
- selected starters are present
- no duplicate players
- no duplicate occupied grid slots

---

### POST /matches/{match_id}/prepare/confirm

Purpose:
- transition match from planned to prepared

Access:
- coach
- administrator

Validation:
- minimum 11 present players
- maximum player limit not exceeded
- formation selected
- all starting positions filled
- all starters present
- no starter injured

Success:
- status becomes `prepared`
- bench auto-assigned
- redirect to preparation overview

---

## 16. Live Match Routes

---

### GET /matches/{match_id}/live

Purpose:
- show live match control screen

Access:
- coach
- administrator

Allowed:
- status `prepared`
- status `active`

Behavior:
- if prepared, show start match control
- if active, show live controls

---

### POST /matches/{match_id}/start

Purpose:
- transition prepared match to active

Access:
- coach
- administrator

Required fields:
- `csrf_token`
- confirmation value

Validation:
- status `prepared`
- user has coach permission
- match lock acquired or available

Behavior:
- first half starts
- score set to 0–0
- active_phase set to `regular_time`
- livestream starts
- period 1 created/started

---

### POST /matches/{match_id}/periods/{period_id}/end

Purpose:
- end current period

Access:
- coach
- administrator

Required fields:
- `csrf_token`
- confirmation value

Validation:
- match active
- period currently active

Behavior:
- set period ended_at
- stop playing time accumulation
- return next available actions

---

### POST /matches/{match_id}/periods/start-second-half

Purpose:
- start second half

Access:
- coach
- administrator

Validation:
- first half ended
- second half not started

---

### POST /matches/{match_id}/extra-time/start

Purpose:
- start extra time

Access:
- coach
- administrator

Validation:
- regular time ended
- match active

---

### POST /matches/{match_id}/penalty-shootout/start

Purpose:
- start penalty shootout

Access:
- coach
- administrator

Validation:
- regular time or extra time ended
- match active

---

### POST /matches/{match_id}/finish

Purpose:
- finish match

Access:
- coach
- administrator

Required fields:
- `csrf_token`
- confirmation value

Validation:
- finish point allowed

Behavior:
- status becomes `finished`
- finished_at set
- livestream_expires_at calculated
- match lock released

---

## 17. Match Event Routes

---

### POST /matches/{match_id}/events/goal

Purpose:
- register goal

Access:
- coach
- administrator

Required fields:
- `team_side`
- `player_selection_id`
- `assist_selection_id`
- `zone_code`
- `csrf_token`

Validation:
- match active
- scorer valid when own team
- assist optional
- assist not same as scorer
- zone valid if provided

Behavior:
- create match_event
- recalculate score
- update timeline

---

### POST /matches/{match_id}/events/penalty

Purpose:
- register penalty during match

Access:
- coach
- administrator

Required fields:
- `team_side`
- `player_selection_id`
- `penalty_outcome`
- `zone_code`
- `csrf_token`

Validation:
- match active
- outcome scored or missed
- no assist allowed
- zone only relevant when scored

Behavior:
- create penalty event
- recalculate score if scored
- update timeline

---

### POST /matches/{match_id}/events/yellow-card

Purpose:
- register yellow card

Access:
- coach
- administrator

Required fields:
- `team_side`
- `player_selection_id`
- `csrf_token`

---

### POST /matches/{match_id}/events/red-card

Purpose:
- register red card

Access:
- coach
- administrator

Required fields:
- `team_side`
- `player_selection_id`
- `csrf_token`

Behavior:
- create red card event
- mark player sent off
- set can_reenter false
- remove from field
- stop playing time
- reduce field count

---

### POST /matches/{match_id}/events/note

Purpose:
- add match note

Access:
- coach
- administrator

Required fields:
- `note_text`
- `csrf_token`

Validation:
- note text required
- note text max 500 characters
- output must be escaped when displayed

---

## 18. Substitution Routes

---

### POST /matches/{match_id}/substitutions

Purpose:
- perform substitution

Access:
- coach
- administrator

Required fields:
- `player_off_selection_id`
- `player_on_selection_id`
- `target_grid_row`
- `target_grid_col`
- `csrf_token`

Validation:
- match active
- outgoing player active on field
- incoming player on bench
- incoming player can re-enter
- sent-off players excluded
- target grid valid
- target grid not occupied unless move behavior handles it

Behavior:
- create substitution record
- update lineup state
- update playing time
- update timeline

---

## 19. Penalty Shootout Routes

---

### GET /matches/{match_id}/shootout

Purpose:
- show penalty shootout control screen

Access:
- coach
- administrator

Allowed:
- active_phase `penalty_shootout`

---

### POST /matches/{match_id}/shootout/attempts

Purpose:
- register shootout attempt

Access:
- coach
- administrator

Required fields:
- `team_side`
- `player_selection_id`
- `player_name_text`
- `outcome`
- `zone_code`
- `csrf_token`

Validation:
- active phase is penalty shootout
- own taker must be eligible
- sent-off players excluded
- player_name_text max 120 characters
- outcome valid
- zone valid if provided

Behavior:
- store attempt order
- store round number
- recalculate shootout score
- check automatic ending condition

---

### POST /matches/{match_id}/shootout/end

Purpose:
- manually end shootout

Access:
- coach
- administrator

Required fields:
- `csrf_token`
- confirmation value

Behavior:
- require confirmation
- present finish match action or finish immediately depending UI flow

---

## 20. Livestream Routes

---

### GET /live/{token}

Purpose:
- public livestream page

Access:
- public

Validation:
- token exists
- livestream started
- not expired
- not manually stopped

Response:
- HTML public livestream page

Failure:
- show generic unavailable message

Security:
- send `Cache-Control: no-store`
- send `Referrer-Policy: no-referrer`
- send `X-Robots-Tag: noindex, nofollow`
- rate-limit requests

---

### GET /live/{token}/data

Purpose:
- JSON polling endpoint for livestream

Access:
- public

Validation:
- token valid
- livestream active

Response:
```json
{
  "ok": true,
  "data": {
    "score": {
      "own": 2,
      "opponent": 1
    },
    "phase": "regular_time",
    "timeline": []
  }
}
```

Security:
- send `Cache-Control: no-store`
- send `Referrer-Policy: no-referrer`
- send `X-Robots-Tag: noindex, nofollow`
- rate-limit requests

---

### POST /matches/{match_id}/livestream/stop

Purpose:
- manually stop livestream

Access:
- coach
- administrator

Required fields:
- `csrf_token`

Behavior:
- set livestream_stopped_at

---

### POST /matches/{match_id}/livestream/rotate-token

Purpose:
- invalidate the current public livestream token and issue a replacement

Access:
- coach
- administrator

Required fields:
- `csrf_token`

Validation:
- authenticated user has access to the match
- match belongs to a team the user may manage

Behavior:
- invalidate existing public livestream token
- generate a new high-entropy token
- ensure the previous token no longer grants access

---

## 21. Finished Match Correction Routes

---

### GET /matches/{match_id}/correct

Purpose:
- show finished match correction interface

Access:
- coach
- administrator

Allowed:
- status `finished`

---

### POST /matches/{match_id}/events/{event_id}/update

Purpose:
- correct event

Access:
- coach
- administrator

Validation:
- match finished
- event belongs to match
- fields valid
- lock acquired

Behavior:
- update source event
- recalculate score
- write audit log
- keep match finished

---

### POST /matches/{match_id}/substitutions/{substitution_id}/update

Purpose:
- correct stored substitution data

Access:
- coach
- administrator

Validation:
- match finished
- substitution belongs to match
- lock acquired
- corrected players remain valid for the stored match selection

Behavior:
- update substitution source data
- recalculate derived lineup or playing-time data where needed
- write audit log

---

### POST /matches/{match_id}/shootout/attempts/{attempt_id}/update

Purpose:
- correct shootout attempt

Access:
- coach
- administrator

Behavior:
- update attempt
- recalculate shootout score
- write audit log

---

## 22. Rating Routes

Full-scope module:
- post-MVP, not required for MVP release

---

### GET /matches/{match_id}/ratings

Purpose:
- show post-match ratings

Access:
- coach
- administrator

---

### POST /matches/{match_id}/ratings

Purpose:
- save ratings

Access:
- coach
- administrator

Required fields:
- `ratings[player_selection_id][pace]`
- `ratings[player_selection_id][shooting]`
- `ratings[player_selection_id][passing]`
- `ratings[player_selection_id][dribbling]`
- `ratings[player_selection_id][defending]`
- `ratings[player_selection_id][physicality]`
- `csrf_token`

Behavior:
- store partial ratings
- set is_complete only when all fields valid

---

## 23. Statistics Routes

---

### GET /statistics

Purpose:
- show team statistics

Access:
- team role or administrator

Query parameters:
- `season_id`
- `phase_id`
- `from`
- `to`

---

### GET /players/{player_id}/statistics

Purpose:
- show player statistics

Access:
- team role or administrator

Query parameters:
- `season_id`
- `phase_id`
- `from`
- `to`

---

## 24. Lock Routes

---

### POST /matches/{match_id}/lock

Purpose:
- acquire edit lock

Access:
- authenticated team access

Required fields:
- `csrf_token`

Validation:
- authenticated user still has edit permission for the match
- match belongs to a team the user may access

Response:
```json
{
  "ok": true,
  "data": {
    "locked": true
  }
}
```

Failure:
```json
{
  "ok": false,
  "error": {
    "code": "locked",
    "message": "This match is currently being edited by another user."
  }
}
```

---

### POST /matches/{match_id}/lock/refresh

Purpose:
- refresh active lock

Access:
- lock owner

Required fields:
- `csrf_token`

Validation:
- authenticated user still has edit permission for the match
- lock is currently owned by the same user

---

### POST /matches/{match_id}/lock/release

Purpose:
- release lock

Access:
- lock owner or administrator

Required fields:
- `csrf_token`

Validation:
- authenticated user still has permission to release the relevant lock

---

## 25. Settings Routes

---

### GET /settings

Purpose:
- show settings menu

Access:
- authenticated

Visible sections depend on role.

---

### GET /settings/formations

Purpose:
- manage formations

Access:
- administrator
- team manager if allowed by policy

---

### POST /settings/formations

Purpose:
- create formation

Access:
- administrator
- team manager if allowed by policy

---

### GET /settings/language

Purpose:
- show language settings

Access:
- authenticated

---

### POST /settings/language

Purpose:
- update user locale

Access:
- authenticated

Required fields:
- `locale`
- `csrf_token`

Validation:
- locale supported
- locale max 10 characters

---

## 26. Error Codes

Recommended JSON error codes:

| Code | Meaning |
|---|---|
| unauthenticated | User is not logged in |
| unauthorized | User lacks permission |
| not_found | Resource does not exist |
| validation_failed | Input failed validation |
| locked | Resource is locked |
| invalid_state | Action not allowed in current state |
| csrf_failed | CSRF token invalid |
| rate_limited | Too many requests were made |
| server_error | Unexpected server error |

Public token-based endpoints should generally avoid detailed error codes in rendered responses even if the server records specific internal reasons.

---

## 27. Recommended Security Headers

Recommended response headers:

- `Content-Security-Policy` with a minimal allowlist appropriate for the server-rendered app
- `X-Frame-Options: DENY` or `Content-Security-Policy: frame-ancestors 'none'`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: no-referrer` for public token pages
- `Cache-Control: no-store` for public token pages and sensitive authenticated flows
- `Strict-Transport-Security` for production HTTPS deployments

Session cookies should use:
- `HttpOnly`
- `Secure`
- `SameSite=Lax`

Session policy should also define:
- idle timeout
- absolute session lifetime
- logout invalidation behavior

Recommended defaults:
- idle timeout: 30 minutes
- absolute session lifetime: 12 hours
- recent-authentication check for high-impact administrative actions

---

## 28. Production Safeguards

Production and staging environments must not expose:
- temporary developer login
- test authentication bypass routes
- debug-only impersonation helpers

If such mechanisms exist for local development, they must be disabled by configuration outside local development.

---

## 29. MVP Route Priority

Build routes in this order:

1. `/login`
2. `/`
3. `/players`
4. `/matches`
5. `/matches/{id}/prepare`
6. `/matches/{id}/start`
7. `/matches/{id}/live`
8. `/matches/{id}/events/goal`
9. `/matches/{id}/finish`
10. `/live/{token}`

Only after this:
- substitutions
- cards
- penalties
- corrections
- statistics
- ratings
- trainings

---

## 30. Summary

The BarePitch route structure is designed to be:

- server-rendered by default
- simple to implement in PHP
- compatible with shared hosting
- strict about authorization
- explicit about match state
- minimal where possible
- JSON-based only where useful

The route structure supports the BarePitch principle:

> Shows what matters, when it matters. Nothing more.

---

# End
