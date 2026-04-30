# BarePitch — Implementation Planning
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the implementation plan for BarePitch.

This document is the source of truth for:
- build order
- phase boundaries
- vertical slice sequencing
- release preparation order

This document does not define:
- final MVP scope
- route contracts
- authoritative business behavior

It translates the existing documentation into a practical build sequence.

The goal is to prevent:
- building too broadly too early
- premature optimization
- feature drift
- unclear completion criteria
- unfinished core flows

BarePitch must be built vertically first.

A vertical slice means:
- one complete usable flow
- from data setup
- through interaction
- to persistence
- to visible result

---

## 2. Guiding Principle

BarePitch must first prove one thing:

Can a coach run a complete match flow in the system?

The first implementation target is:

- create a team
- create players
- create a match
- prepare lineup
- start match
- register key events
- finish match
- view result

---

## 3. Implementation Strategy

### 3.1 Build Vertically, Not Horizontally

Do not build all database tables first and then all screens.

Do not build all modules partially.

Instead, build one thin but complete flow.

Recommended first vertical slice:

1. authentication stub or temporary login
2. team context
3. player list
4. match creation
5. match preparation
6. lineup grid
7. start match
8. register goal
9. finish match
10. view match summary

### 3.2 Avoid Premature Feature Completion

The first version may be incomplete in breadth.

That is acceptable.

It must not be incomplete in flow.

A user must be able to complete the selected flow without manual database intervention.

### 3.3 Keep Technical Choices Boring

The implementation must remain compatible with:
- shared hosting
- PHP
- MySQL
- no Node.js server
- no heavy framework
- no build pipeline requirement

---

## 4. Phase 0 — Repository and Project Foundation

### Goal

Create the basic project structure and make the application run.

### Scope

Includes:
- repository structure
- public entry point
- bootstrap file
- configuration loading
- router
- database connection
- base layout
- basic CSS loading
- error handling skeleton

### Deliverables

- `/public/index.php`
- `/app/bootstrap.php`
- `/app/Core/Router.php`
- `/app/Core/Request.php`
- `/app/Core/Response.php`
- `/app/Core/Database.php`
- `/app/Config/routes.php`
- first working page

### Done Criteria

Phase 0 is done when:
- visiting the root URL returns a rendered BarePitch page
- database connection can be tested successfully
- routing works for at least one route
- CSS is loaded from `/public/css`
- errors are handled without exposing stack traces to users

---

## 5. Phase 1 — Database Foundation

### Goal

Create the database schema and confirm the core data model works.

### Scope

Includes core tables:
- club
- season
- phase
- team
- user
- user_team_role
- player
- player_profile
- player_season_context
- formation
- formation_position
- match

### Excluded

Do not yet implement:
- livestream
- audit logging
- ratings
- penalty shootout
- full statistics
- training flows

### Deliverables

- SQL schema installed
- seed data for one club, season, phase, team, admin user, coach role, formation and sample players

### Done Criteria

Phase 1 is done when:
- schema can be installed cleanly
- seed data loads without errors
- player season context works
- team roles can be queried
- one team can be selected as working context

---

## 6. Phase 2 — Authentication and Team Context

### Goal

Allow users to enter the application and operate within a team context.

### Scope

Includes:
- session handling
- temporary login or magic link login
- user lookup
- active team selection
- role loading
- permission checks

### Recommended Approach

Start with a temporary developer login only if needed.

Then replace with magic link authentication.

Do not block early build progress on email delivery.

Temporary developer login is a local development aid only.

It must not be enabled in staging or production.

### Deliverables

- login route
- logout route
- authenticated session
- current user helper
- current team helper
- role check helper
- no-role user handling

### Done Criteria

Phase 2 is done when:
- user can log in
- user can log out
- user can select a team if multiple teams exist
- user without role has no team access
- protected routes reject unauthenticated users
- server-side policy checks exist for write routes

---

## 7. Phase 3 — Player and Team Management

### Goal

Allow basic team setup.

### Scope

Includes:
- list team players
- create player
- edit player name
- set squad number
- set preferred line
- set preferred foot
- deactivate player
- create external guest player
- assign player season context

### Roles

Allowed:
- administrator
- team manager

View-only:
- coach
- trainer

### Excluded

Do not implement:
- advanced player statistics
- ratings history
- player photos
- parent contact data

### Deliverables

- player list screen
- player create form
- player edit form
- player profile view
- player repository
- player service
- player validator
- player policy

### Done Criteria

Phase 3 is done when:
- team manager can add a player
- player appears in team list
- player has season context
- external guest player can be created without team
- coach can view but not manage players
- invalid player data is rejected server-side

---

## 8. Phase 4 — Match Creation

### Goal

Allow the coach to create and view planned matches.

### Scope

Includes:
- create match
- edit match metadata
- assign phase
- set opponent
- set home/away
- set match type
- set half duration
- set optional extra-time duration
- list matches
- view match detail

### Roles

Allowed to manage:
- coach
- administrator

View:
- trainer
- team manager

### Deliverables

- match list
- match create form
- match detail screen
- match repository
- match service
- match validator
- match policy

### Done Criteria

Phase 4 is done when:
- coach can create a planned match
- match appears in list
- match belongs to team and phase
- invalid phase/team combinations are rejected
- trainer can view but not edit match

---

## 9. Phase 5 — Match Preparation

### Goal

Allow the coach to prepare a match for kickoff.

### Scope

Includes:
- match attendance
- guest player selection
- formation selection
- lineup grid
- bench auto-assignment
- planned to prepared transition

### Required Rules

A match can become prepared only when:
- at least 11 players are present
- player limit is not exceeded
- formation is selected
- all starting positions are filled
- all starters are present
- no starter is injured

### Deliverables

- preparation screen
- attendance section
- guest player selector
- formation selector
- lineup grid
- bench list
- prepare match action

### Done Criteria

Phase 5 is done when:
- coach can mark players present, absent, injured
- coach can add internal or external guest players
- coach can select formation
- coach can fill every grid position
- non-starting present players automatically appear on bench
- incomplete lineup blocks preparation
- valid lineup transitions match to prepared

---

## 10. Phase 6 — Live Match Core

### Goal

Allow the coach to run a basic live match.

### Scope

Includes:
- start match
- start first half
- display score
- display timer
- display current lineup
- register regular goal
- register optional assist
- register optional goal zone
- recalculate score
- show timeline
- end first half
- start second half
- end second half
- finish match

### Excluded

Do not yet implement:
- extra time
- penalty shootout
- red card restrictions
- livestream
- ratings

### Deliverables

- live match screen
- score header
- live timeline
- goal registration modal
- 3x3 goal zone matrix
- period controls
- finish match action

### Done Criteria

Phase 6 is done when:
- coach can start prepared match
- first half starts immediately
- score starts at 0–0
- coach can register own goal
- coach can register opponent goal
- score updates from event source
- goal appears in timeline
- coach can end both halves manually
- coach can finish match
- finished match remains viewable

---

## 11. Phase 7 — Substitutions and Playing Time

### Goal

Add basic live lineup changes and playing time tracking.

### Scope

Includes:
- substitution flow
- outgoing player selection
- incoming player selection
- update current lineup
- update bench
- playing time seconds
- current on-field state

### Rules

- only active field players can be substituted off
- only eligible bench players can be substituted on
- sent-off players cannot be substituted on
- playing time stops for outgoing player
- playing time starts for incoming player

### Done Criteria

Phase 7 is done when:
- coach can substitute players during active match
- lineup updates immediately after server success
- bench updates correctly
- playing time is stored in seconds
- player cannot exist twice on field
- substitution appears in timeline

---

## 12. Phase 8 — Cards and Red Card Behavior

### Goal

Add card registration and red card restrictions.

### Scope

Includes:
- yellow card event
- red card event
- sent-off state
- remove player from field
- block re-entry
- stop playing time
- reduce field count

### Done Criteria

Phase 8 is done when:
- coach can register yellow card
- coach can register red card
- red-carded player leaves field
- red-carded player cannot return
- red-carded player cannot be selected for penalty shootout
- field count is reduced by one
- red card appears in timeline

---

## 13. Phase 9 — Penalties During Match

### Goal

Allow scored and missed penalties during regular time and extra time.

### Scope

Includes:
- penalty event type
- scored outcome
- missed outcome
- taker selection
- optional zone if scored
- no assist option

### Done Criteria

Phase 9 is done when:
- coach can register scored penalty
- coach can register missed penalty
- scored penalty updates score
- missed penalty does not update score
- penalty appears in timeline
- assist cannot be added to penalty

---

## 14. Phase 10 — Extra Time and Penalty Shootout

### Goal

Support match flows beyond regular time.

### Scope

Includes:
- start extra time
- end extra-time periods
- start penalty shootout
- register shootout attempts
- scored/missed outcomes
- attempt order
- round number
- sudden death marker
- automatic ending detection
- manual ending confirmation

### Done Criteria

Phase 10 is done when:
- coach can choose extra time after regular time
- coach can choose penalties after regular time
- coach can choose penalties after extra time
- shootout attempts are stored separately
- shootout score does not affect match score
- sent-off players cannot take penalties
- shootout can end automatically with confirmation
- shootout can end manually with confirmation

---

## 15. Phase 11 — Livestream

### Goal

Provide public match viewing without login.

### Scope

Includes:
- livestream token
- public livestream route
- score display
- timeline display
- phase display
- polling refresh
- expiration
- manual stop

### Rules

- livestream starts when match becomes active
- default expiration is 24 hours after finish
- maximum expiration is 72 hours
- coach can stop livestream manually
- corrections appear while livestream is active
- expired links deny access

### Done Criteria

Phase 11 is done when:
- public link works without login
- livestream shows current score
- timeline updates after polling
- expired link is inaccessible
- manually stopped link is inaccessible
- finished match remains visible internally

---

## 16. Phase 12 — Finished Match Corrections and Audit

### Goal

Allow authorized corrections after a match is finished.

### Scope

Includes:
- edit finished match events
- edit finished match substitutions
- edit scorer
- edit assist
- edit zone
- edit penalty outcome
- edit shootout attempts
- score recalculation
- audit log

### Rules

Allowed:
- coach
- administrator

Not allowed:
- trainer
- team manager

### Done Criteria

Phase 12 is done when:
- coach can correct finished match
- administrator can correct finished match
- unauthorized roles cannot correct
- corrections are logged
- cached scores are recalculated from source
- match remains finished

---

## 17. Phase 13 — Ratings

### Goal

Add optional post-match player ratings.

### Scope

Includes:
- six skill ratings
- completion detection
- rating edit
- rating averages

### Rules

Rating counts only when all fields are filled:
- pace
- shooting
- passing
- dribbling
- defending
- physicality

Partial ratings do not count.

### Done Criteria

Phase 13 is done when:
- coach can rate player
- partial rating is stored but incomplete
- complete rating counts in averages
- incomplete rating does not count
- ratings can be edited later

---

## 18. Phase 14 — Training and Attendance

### Goal

Add the non-match attendance flow used by dashboard and attendance statistics.

### Scope

Includes:
- create training
- edit training
- cancel training
- record attendance
- absence reasons
- injured status

### Rules

- cancelled sessions are excluded from attendance calculations
- injured is excluded from attendance denominator
- absence reasons follow the functional scope guide

### Done Criteria

Phase 14 is done when:
- trainer can create training
- trainer or team manager can record attendance
- cancelled training is excluded from statistics inputs
- attendance percentages can be calculated from persisted data

---

## 19. Phase 15 — Statistics

### Goal

Add useful statistics without turning BarePitch into an analytics platform.

### Scope

Includes:
- player goals
- assists
- playing time
- match count
- attendance percentage
- cards
- rating averages
- season filter
- phase filter

### Rules

- shootout goals are separate
- injured is excluded from attendance denominator
- cancelled training sessions are excluded
- partial ratings excluded

### Done Criteria

Phase 15 is done when:
- player statistics show correct values
- statistics can filter by season
- statistics can filter by phase
- attendance formula is correct
- shootout data is separated

---

## 20. Phase 16 — Dashboard

### Goal

Create a minimal dashboard that shows what matters now.

### Scope

Includes:
- active team context
- next match
- next training
- latest result
- incomplete preparation indicator

### Rule

Dashboard must not become a statistics dashboard.

### Done Criteria

Phase 16 is done when:
- dashboard shows next actionable item
- dashboard links to match preparation when needed
- dashboard remains uncluttered

---

## 21. Phase 17 — Internationalization

### Goal

Support multiple languages.

### Scope

Includes:
- language files
- translation helper
- user locale
- fallback language
- date/time formatting

### Rules

- no hardcoded user-facing text
- system keys remain English
- UI labels translated through helper

### Done Criteria

Phase 17 is done when:
- user can select language
- fallback language works
- all visible UI text uses translation helper
- dates format according to locale

---

## 22. Phase 18 — Hardening and Security Review

### Goal

Prepare the system for real use.

### Scope

Includes:
- CSRF verification
- output escaping
- input validation review
- session settings
- magic link security
- permission review
- error handling review
- route protection review
- rate limiting
- security headers review
- public token endpoint review
- HSTS and HTTPS deployment review
- audit-log access and retention review

### Done Criteria

Phase 18 is done when:
- all write routes require CSRF token
- all write routes check policy
- SQL uses prepared statements
- no stack traces visible to users
- sessions use secure settings
- magic links are one-time and expire
- magic-link tokens are stored hashed
- login and public livestream endpoints are rate-limited
- public token pages send no-store and noindex protections
- public token failures use generic messaging
- no temporary developer login path remains enabled outside local development
- production HTTPS deployments send HSTS
- session idle and absolute lifetime rules are implemented
- audit-log access and retention behavior are documented and enforced
- invalid access attempts fail safely

---

## 23. Phase 19 — MVP Release

### Goal

Release the first usable BarePitch version.

Release target:
- `v1.0.0`

### MVP Includes

The MVP must include:
- authentication
- team context
- administrator setup for club, season, phase, team, and role assignment
- player management
- match creation
- match preparation
- lineup grid
- live match start
- goal registration
- substitutions
- cards
- match finish
- basic summary
- livestream
- corrections
- audit log

### MVP Excludes

The MVP may exclude:
- advanced statistics
- ratings
- training management
- training attendance
- full multilingual completion beyond core UI
- extended dashboard behavior
- extra visual polish
- complex export functions

### Release Criteria

MVP is ready when:
- a coach can run a complete match in BarePitch
- an administrator can set up the minimum club, season, phase, team, and role structure required for that coach
- data survives refresh
- score remains consistent
- red card rules work
- corrections work
- livestream works
- authorization works
- no manual database intervention is needed

When these release criteria are met, the release version must be:
- `v1.0.0`

---

## 24. Build Discipline

Every new feature must answer:

1. Does this support the current build phase?
2. Does this help the coach now?
3. Can this wait until after the vertical slice?
4. Does this preserve BarePitch minimalism?

If the answer is unclear:
- do not build it yet

---

## 25. Recommended Immediate Next Step

Start Phase 0 and Phase 1.

Then build toward the first vertical slice:

1. team
2. players
3. match
4. lineup
5. start match
6. register goal
7. finish match

Do not start with:
- ratings
- statistics
- dashboard refinements
- multilingual polish
- advanced livestream display
- training workflows

These are valuable later, but not needed to prove the core.

---

## End
