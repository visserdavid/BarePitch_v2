# BarePitch — Functional Scope Guide
Version 1.0 — April 2026

---

## 1. Purpose

This document describes all functionality BarePitch should ultimately support.

This document is the source of truth for:
- full product scope
- feature boundaries
- role intent
- non-MVP modules

This document does not define:
- implementation order
- route paths
- regression coverage
- authoritative live match state behavior

Each function is described in terms of:
- purpose
- users
- behavior
- rules
- boundaries

The document is intended as a functional reference for development.

BarePitch follows this principle:

> BarePitch shows what matters, when it matters. Nothing more.

---

## 2. Functional Modules

BarePitch consists of the following functional modules:

1. Authentication
2. Clubs
3. Seasons
4. Phases
5. Teams
6. Users and roles
7. Players
8. Player profiles
9. Guest players
10. Training sessions
11. Training attendance
12. Matches
13. Match preparation
14. Lineups
15. Live match management
16. Match events
17. Substitutions
18. Cards
19. Penalty shootouts
20. Livestream
21. Finished match corrections
22. Ratings
23. Statistics
24. Dashboard
25. Settings
26. Internationalization
27. Audit logging
28. Concurrency locking
29. Security and validation
30. Styling and interaction patterns

Some modules listed here are intentionally outside MVP.

MVP inclusion is defined only in `BarePitch-v2-06-mvp-scope-v1.0.md`.

---

# 3. Authentication

## 3.1 Purpose

Authentication gives authorized users access to BarePitch without using passwords.

BarePitch uses magic links by email.

---

## 3.2 User Flow

1. User enters email address
2. System checks if the email belongs to an active user
3. If valid, a one-time login link is generated
4. User receives email
5. User opens link
6. Token is validated
7. User is logged in
8. Token becomes invalid

---

## 3.3 Rules

- Unknown email addresses must receive the same neutral response as known addresses
- Tokens are single-use
- Tokens expire after a short period
- Tokens must be generated with cryptographically secure randomness
- Tokens must be stored hashed, not in plaintext
- Only one active unused login token per user should remain valid at a time
- Login requests must be rate-limited by IP address and by email identifier
- Login creates a secure session
- Session is regenerated after login
- Failed email delivery must still return the same neutral login-request response to the user

---

## 3.4 Boundaries

BarePitch does not support:
- passwords
- public registration
- social login

---

# 4. Clubs

## 4.1 Purpose

A club is the highest organizational entity.

Example:
- VRC

A club groups teams, seasons, users, and players.

---

## 4.2 Users

Used by:
- administrator

---

## 4.3 Behavior

Administrator can:
- create club
- edit club name
- deactivate club

---

## 4.4 Boundaries

Club management is not available to normal team roles.

---

# 5. Seasons

## 5.1 Purpose

A season is a time container for team activity.

Example:
- 2025-2026

---

## 5.2 Users

Used by:
- administrator

---

## 5.3 Behavior

Administrator can:
- create season
- define start date
- define end date
- mark season active/inactive

---

## 5.4 Rules

- A season contains phases
- Teams are season-bound
- Player statistics can be filtered by season

---

# 6. Phases

## 6.1 Purpose

A phase represents a competition period within a season.

Examples:
- Phase 1
- Phase 2
- Phase 3

Each phase may have its own competition and ranking context.

---

## 6.2 Users

Used by:
- administrator
- team manager for viewing
- coach and trainer for filtering

---

## 6.3 Behavior

A phase contains:
- label
- start date
- end date
- optional focus text

Matches and training sessions are assigned to a phase.

---

## 6.4 Rules

- Teams remain the same across phases
- Matches change per phase
- Statistics can be filtered by phase

---

# 7. Teams

## 7.1 Purpose

A team is the working context for coaches, trainers, and team managers.

Example:
- VRC JO15-4

---

## 7.2 Users

Used by:
- administrator
- trainer
- coach
- team manager

---

## 7.3 Behavior

A team:
- belongs to one club
- belongs to one season
- has an editable name
- may be based on a previous season team

---

## 7.4 Rules

- A team is recreated every season
- A team from a previous season is not the same database team
- A team can inherit players and roles from a previous season if selected

---

# 8. Users and Roles

## 8.1 Purpose

User roles determine what a user may see and do.

Roles are assigned per team.

---

## 8.2 Roles

### Administrator

Global role.

Can:
- manage all clubs
- manage all seasons
- manage all teams
- manage all users
- access all data

### Trainer

Team-level role.

Can:
- manage trainings
- manage training attendance
- view matches
- view players
- view statistics

Cannot:
- manage matches
- change lineups
- register match events

### Coach

Team-level role.

Can:
- manage matches
- prepare matches
- create lineups
- start and manage live matches
- register match events
- correct finished matches
- view trainings
- view players
- view statistics

### Team Manager

Team-level role.

Can:
- manage players
- manage attendance
- support administrative team tasks
- view matches and trainings

Cannot:
- create tactical lineups
- execute substitutions
- control live match flow

---

## 8.3 Rules

- Roles are cumulative
- A user without team role has no functional access
- Server-side authorization is required for every write action

---

# 9. Players

## 9.1 Purpose

A player is a persistent identity across seasons.

A player is not recreated every season.

---

## 9.2 Users

Used by:
- administrator
- team manager
- coach and trainer for viewing

---

## 9.3 Behavior

A player can:
- be linked to one team in a season
- have no team in a season
- appear as a guest player in another team match

---

## 9.4 Rules

- One player has exactly one season context per season
- A player can have historical data across multiple seasons
- Players are not identified by photo

---

# 10. Player Profiles

## 10.1 Purpose

Player profiles provide lightweight context.

---

## 10.2 Profile Fields

Optional fields:
- preferred foot
- preferred line
- squad number per season context

---

## 10.3 Rules

Profile data supports selection and lineup decisions.

Profile data must never block match participation.

---

## 10.4 Boundaries

BarePitch does not store:
- player photos
- parent contact details
- private medical files

---

# 11. Guest Players

## 11.1 Purpose

Guest players allow a team to use players outside the normal team selection.

---

## 11.2 Types

### Internal Guest Player

A player from another team within the same club.

### External Guest Player

A player without a primary team in the season.

---

## 11.3 Behavior

When preparing a match, the coach can:
- select internal guest players
- select existing external guest players
- create a new external guest player

---

## 11.4 Rules

- Guest player status is match-context based
- Guest players can be included in match statistics
- Guest players are not shown in the regular team list by default
- External guest players are reusable across seasons and matches

---

# 12. Training Sessions

## 12.1 Purpose

Training sessions record attendance and training focus.

---

## 12.2 Users

Used by:
- trainer
- team manager
- administrator

Coach may view training sessions.

---

## 12.3 Behavior

A training session includes:
- date and time
- phase
- focus tags
- notes
- attendance

---

## 12.4 Focus Tags

Allowed focus:
- attacking
- defending
- transitioning

Multiple focus tags may be selected.

---

## 12.5 Rules

- Cancelled sessions do not count in attendance calculations
- Attendance remains editable

---

# 13. Training Attendance

## 13.1 Purpose

Training attendance tracks player participation.

---

## 13.2 Statuses

Allowed statuses:
- present
- absent
- injured

---

## 13.3 Absence Reasons

Allowed absence reasons:
- sick
- holiday
- school
- other

---

## 13.4 Rules

- Injured does not count as present
- Injured does not count as absent
- Injured is excluded from attendance percentage calculations

---

# 14. Matches

## 14.1 Purpose

Matches represent fixtures played by a team.

---

## 14.2 Users

Used by:
- coach
- team manager for administrative support
- trainer for viewing
- administrator

---

## 14.3 Match Data

A match includes:
- team
- phase
- date
- kickoff time
- opponent
- home/away
- match type
- regular half duration
- optional extra time duration
- status
- livestream data
- period data

---

## 14.4 Match Statuses

Allowed statuses:
- planned
- prepared
- active
- finished

Detailed state-transition behavior is defined in `BarePitch-v2-05-critical-behavior-specifications-v1.0.md`.

---

# 15. Match Preparation

## 15.1 Purpose

Match preparation ensures a match is ready before kickoff.

---

## 15.2 Required Conditions

A match may become prepared only when:
- at least 11 players are present
- the maximum player limit is not exceeded
- formation is selected
- all starting positions are filled

---

## 15.3 Maximum Players

Default maximum:
- 18

The maximum is configurable.

---

## 15.4 Bench Handling

Players who are present but not in the starting lineup are automatically assigned to the bench.

---

# 16. Formations

## 16.1 Purpose

A formation defines starting positions on the lineup grid.

---

## 16.2 Behavior

A formation contains:
- name
- position labels
- grid rows
- grid columns
- line classification

---

## 16.3 Rules

Formation positions must fit inside:
- 10 rows
- 11 columns

---

# 17. Lineups

## 17.1 Purpose

A lineup shows the current field and bench state.

---

## 17.2 Behavior

The lineup:
- is based on a fixed grid
- stores current state only
- changes when substitutions occur
- supports live match control

---

## 17.3 Rules

- No historical minute-by-minute lineup snapshots
- Substitutions are stored as events/records
- Current lineup state is always authoritative

---

# 18. Live Match Management

## 18.1 Purpose

Live match management supports the coach during the match.

---

## 18.2 Actions

Coach can:
- start match
- end halves
- start second half
- start extra time
- start penalty shootout
- register goals
- register penalties
- register cards
- register substitutions
- add notes
- finish match

---

## 18.3 Critical Actions

Critical actions require confirmation:
- start match
- end half
- start extra time
- end extra time
- end penalty shootout
- finish match

Swipe interaction is recommended for critical transitions.

---

# 19. Match Events

## 19.1 Purpose

Match events form the source of truth for match history and statistics.

---

## 19.2 Event Types

Allowed event types:
- goal
- penalty
- yellow card
- red card
- note

---

## 19.3 Goal Events

Goal events include:
- team side
- player
- optional assist
- optional zone

---

## 19.4 Penalty Events During Match

Penalty events include:
- team side
- player
- outcome scored/missed
- zone if scored

---

## 19.5 Notes

Notes are free text entries.

Notes must remain secondary information.

Notes must have an explicit length limit.

Default:
- live match notes: 500 characters

Longer internal notes are allowed only where the domain model explicitly defines them.

---

# 20. Substitutions

## 20.1 Purpose

Substitutions update the current lineup and playing time.

---

## 20.2 Behavior

A substitution includes:
- outgoing player
- incoming player
- match second
- period

---

## 20.3 Rules

- outgoing player moves to bench
- incoming player enters field
- playing time updates
- sent-off players cannot re-enter

---

# 21. Cards

## 21.1 Yellow Card

A yellow card records:
- player
- match time
- team side

---

## 21.2 Red Card

A red card means:
- player leaves field immediately
- player cannot return
- field count decreases by one
- playing time stops

---

# 22. Penalty Shootouts

## 22.1 Purpose

Penalty shootouts decide a match after regular time or extra time.

They do not affect the regular match score.

---

## 22.2 Behavior

Each attempt records:
- order
- round
- team side
- player
- outcome
- zone if scored
- sudden death marker

---

## 22.3 Ending

A shootout may end:
- automatically when mathematically decided
- manually with confirmation

Both endings require confirmation.

---

# 23. Livestream

## 23.1 Purpose

The livestream allows followers to view match progress without login.

---

## 23.2 Start

Livestream starts when a match becomes active.

---

## 23.3 Content

Livestream displays:
- score
- phase
- timeline
- key events

---

## 23.4 Duration

Default duration after match finish:
- 24 hours

Maximum:
- 72 hours

---

## 23.5 Rules

- Coach can stop livestream manually
- Expired livestream links deny access
- Corrections are reflected while livestream remains active
- Livestream tokens must be high-entropy bearer secrets
- Public livestream responses must not be indexed by search engines
- Public livestream responses must avoid cache persistence on shared devices and intermediaries
- Public livestream failures should use generic unavailable messaging
- If a livestream token is suspected to be exposed, the system should support token rotation or forced invalidation

---

# 24. Finished Match Corrections

## 24.1 Purpose

Finished matches remain correctable.

Corrections are expected because live registration may be incomplete.

---

## 24.2 Permissions

Allowed:
- coach
- administrator

Not allowed:
- trainer
- team manager

---

## 24.3 Rules

- Match remains finished
- Corrections trigger score recalculation
- Corrections are logged

---

# 25. Ratings

## 25.1 Purpose

Ratings allow optional post-match player evaluation.

---

## 25.2 Skills

Rated skills:
- pace
- shooting
- passing
- dribbling
- defending
- physicality

---

## 25.3 Rules

- Scale: 1–5
- Rating counts only when complete
- Partial ratings do not count in averages

---

# 26. Statistics

## 26.1 Purpose

Statistics support coaching reflection without becoming the main purpose of the app.

---

## 26.2 Player Statistics

May include:
- matches played
- playing time
- goals
- assists
- cards
- attendance percentage
- completed rating averages

---

## 26.3 Team Statistics

May include:
- wins
- draws
- losses
- goals scored
- goals conceded

---

## 26.4 Filters

Statistics may be filtered by:
- season
- phase
- custom date range

---

## 26.5 Rules

- Shootout goals are stored separately
- Injured status excluded from attendance calculations
- Partial ratings excluded from averages

---

# 27. Dashboard

## 27.1 Purpose

The dashboard shows what matters now.

---

## 27.2 Content

Dashboard may show:
- next match
- next training
- recent result
- active team context
- urgent incomplete tasks

---

## 27.3 Rules

Dashboard must not become a statistics overview.

Dashboard is full-scope functionality.

A minimal dashboard may exist in MVP, but full dashboard behavior is not required for MVP completion.

Dashboard is a top-level destination candidate in the stable bottom navigation model.

---

# 28. Settings

## 28.1 Purpose

Settings manage system and team configuration.

---

## 28.2 Configurable Items

May include:
- maximum match players
- livestream duration
- training days
- formations
- language
- season data

---

## 28.3 Rules

Only authorized roles may access relevant settings.

Detailed route permissions for settings are defined in `BarePitch-v2-08-route-api-specification-v1.0.md`.

---

# 29. Internationalization

## 29.1 Purpose

BarePitch supports multiple languages.

---

## 29.2 Behavior

All user-facing text:
- stored in language files
- not hardcoded in templates

---

## 29.3 Rules

- User has preferred locale
- Fallback language required
- Dates and times follow user locale

---

# 30. Audit Logging

## 30.1 Purpose

Audit logging creates traceability for important corrections.

---

## 30.2 Logged Data

Audit records:
- entity type
- entity id
- user
- field
- old value
- new value
- timestamp

---

## 30.3 Rules

Finished match corrections must be logged.

The audit trail should also include:
- role assignment changes
- team access changes
- livestream manual stop actions
- suspicious or rate-limited authentication events where implemented

Audit records should be append-only for normal application users.

They must not be silently editable through normal product workflows.

Audit governance should define:
- who may read audit records
- how long records are retained
- whether export is allowed and by whom

---

# 31. Concurrency Locking

## 31.1 Purpose

Locking prevents simultaneous match edits.

---

## 31.2 Behavior

When a match is being edited:
- lock is assigned to one user
- other users receive read-only access or denial

---

## 31.3 Rules

Recommended lock timeout:
- 2 minutes

Recommended refresh:
- 30 seconds

Locks prevent conflicting edits.

Locks do not replace authorization checks.

---

# 32. Security and Validation

## 32.1 Purpose

Security protects users, data, and public livestream links.

---

## 32.2 Rules

Backend must enforce:
- authentication
- authorization
- CSRF protection
- input validation
- maximum length validation for all free-text fields
- output escaping
- prepared SQL statements
- secure sessions
- secure cookie settings
- HTTPS in production
- HSTS in production HTTPS deployments
- rate limiting for public authentication and public livestream endpoints
- generic failure responses for public token-based endpoints
- security headers for authenticated and public responses
- defined session lifetime policy
- production-only deployment safeguards for development login shortcuts

Default session policy should define:
- inactivity timeout: 30 minutes
- absolute session lifetime: 12 hours
- session regeneration after login
- logout invalidation on the server

Sensitive actions should require recent authentication or step-up confirmation where appropriate.

Examples:
- role or permission changes
- livestream token rotation
- other high-impact administrative actions

Free-text validation rules:
- names and labels must use the canonical limits from the domain model
- email must be limited to 254 characters and validated as an email address
- locale keys must use a short documented limit
- short notes must default to 500 characters
- long internal/admin notes must default to 2000 characters
- frontend limits are helpful but never authoritative
- server-side validators and database column definitions are the source of enforcement
- optional free-text fields should normalize empty strings to `NULL`

---

## 32.3 Boundaries

Security must not rely on hidden UI elements alone.

Every write action requires server-side authorization.

Authorization should prefer resource-scoped lookup patterns where possible.

Example:
- load a match through the user's allowed team scope instead of loading by ID first and denying later

Locks are consistency controls, not permission grants.

Production environments must not expose developer bypass authentication mechanisms.

---

# 33. Styling and Interaction Patterns

## 33.1 Purpose

Styling supports clarity and speed.

---

## 33.2 Rules

Use:
- plain CSS
- CSS variables
- semantic classes
- mobile-first layout
- intuitive icon-first controls for repeated high-frequency actions where this reduces clutter without reducing clarity
- a thumb-reachable bottom navigation bar for smartphone layouts that exposes only currently relevant sections

Avoid:
- Tailwind
- Bootstrap
- heavy animation
- build pipelines

Icon usage must remain accessible and must not depend on color alone.

---

# 34. Explicit Non-Goals

BarePitch does not include:

- player photos
- parent contact details
- push notifications
- realtime sockets
- external league integrations
- tactical whiteboards
- heatmaps
- AI recommendations
- complex analytics dashboards

---

# 35. Summary

BarePitch ultimately supports:

- club and team structure
- season and phase management
- player identity across seasons
- training administration
- match preparation
- live match management
- event registration
- penalty shootouts
- livestream viewing
- statistics
- corrections
- audit logging
- multilingual UI

Every function must serve the BarePitch principle:

> Shows what matters, when it matters. Nothing more.

---

# End
