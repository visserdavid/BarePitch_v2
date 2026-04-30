# BarePitch — MVP Scope
Version 1.0 — April 2026

---

# 1. Purpose

This document defines the exact scope of the BarePitch MVP.

The MVP release version is:
- `v1.0.0`

This document is the source of truth for:
- what is in the MVP
- what is outside the MVP
- what must be true before MVP can be considered complete

This document does not define:
- implementation order
- route contracts
- low-level live match behavior

The MVP exists to prove the core BarePitch concept.

The MVP is successful when:

> A coach can manage a complete football match flow from preparation to completion inside BarePitch without manual intervention.

The MVP is not intended to:
- be feature complete
- support every edge case
- become visually perfect
- become statistically advanced

The MVP exists to validate:
- workflow
- architecture
- usability
- live match flow
- data consistency
- coaching experience

---

# 2. MVP Philosophy

The MVP follows the BarePitch principle:

> BarePitch shows what matters, when it matters. Nothing more.

This means the MVP must prioritize:
- core usability
- clarity
- reliability
- speed
- low cognitive load

The MVP must avoid:
- unnecessary features
- decorative complexity
- analytics overload
- premature optimization

---

# 3. MVP Success Criteria

The MVP is considered successful when a coach can:

1. log in
2. select a team
3. manage players
4. create a match
5. prepare a lineup
6. add guest players
7. start a match
8. register live events
9. perform substitutions
10. handle cards
11. finish the match
12. share a livestream
13. correct finished match data
14. view basic statistics

without:
- direct database edits
- developer intervention
- broken score states
- broken lineup states

---

# 4. Included MVP Modules

The MVP includes the following modules:

1. Authentication
2. Team Context
3. Team and Player Management
4. Guest Players
5. Match Management
6. Match Preparation
7. Formation and Lineup Grid
8. Live Match Management
9. Goal Registration
10. Substitutions
11. Cards
12. Penalties During Match
13. Extra Time
14. Penalty Shootout
15. Livestream
16. Finished Match Corrections
17. Basic Statistics
18. Audit Logging
19. Locking and Concurrency
20. Basic Internationalization Support

Supporting setup required for the MVP but not counted as end-user modules:

- club setup
- season setup
- phase setup
- team setup
- user and role assignment

---

# 5. Authentication

## Included

- magic link login
- logout
- session handling
- server-side authorization
- role-based access

## Excluded

- passwords
- public registration
- social login
- multi-factor authentication

---

# 6. Team Context

## Included

- active team selection
- role loading
- multiple team support
- multiple roles per user

## Rules

- users without role access cannot use the app
- all permissions are server-side enforced

---

# 7. Team and Player Management

## Included

- create player
- edit player
- deactivate player
- player season context
- preferred line
- preferred foot
- squad number

## Excluded

- player photos
- medical records
- parent contact information

---

# 8. Guest Players

## Included

### Internal Guest Players

- selection from other teams within same club

### External Guest Players

- create reusable external guest players
- optional profile data
- optional squad number

## Rules

- guest status exists in match context
- guest players may appear in statistics
- external guest players persist between matches

---

# 9. Match Management

## Included

- create match
- edit match
- opponent text input
- home/away selection
- phase assignment
- regular duration
- extra-time duration
- match statuses

## Match States

Included:
- planned
- prepared
- active
- finished

---

# 10. Match Preparation

## Included

- attendance selection
- injured state
- guest player selection
- formation selection
- lineup grid
- automatic bench assignment

## Rules

A match may become prepared only when:
- at least 11 players are present
- maximum player count is not exceeded
- formation is selected
- all starting positions are filled
- all starters are present
- no starter is injured

---

# 11. Formation and Lineup Grid

## Included

- 10 row grid
- 11 column grid
- draggable or selectable placement
- current lineup state
- bench display

## Rules

- only one player per active field slot
- bench players have no coordinates
- lineup stores current state only
- no historical positional replay

---

# 12. Live Match Management

## Included

- start match
- end first half
- start second half
- finish regular time
- start extra time
- finish extra time
- start penalty shootout
- finish match

## Interaction Rules

Critical actions require:
- swipe interaction
- confirmation modal

---

# 13. Goal Registration

## Included

- own goals
- opponent goals
- optional assist
- optional 3 × 3 goal zone matrix
- score recalculation
- timeline integration

## Rules

- assists optional
- assists unavailable for penalties
- scorer editable after match
- assist editable after match

---

# 14. Substitutions

## Included

- outgoing player selection
- incoming player selection
- current lineup update
- bench update
- playing time tracking

## Rules

- outgoing player must be active
- incoming player must be eligible
- duplicate field players prevented

---

# 15. Cards

## Included

- yellow cards
- red cards
- timeline integration
- sent-off restrictions

## Red Card Rules

A sent-off player:
- leaves field
- cannot return
- cannot take penalties
- cannot be substituted back in
- reduces field player count by one

---

# 16. Penalties During Match

## Included

- scored penalties
- missed penalties
- optional zone selection
- timeline integration

## Rules

- no assist possible
- missed penalties do not affect score

---

# 17. Extra Time

## Included

- two extra-time periods
- configurable duration
- manual start
- manual ending

---

# 18. Penalty Shootout

## Included

- scored attempts
- missed attempts
- attempt order
- round number
- sudden death support
- automatic ending detection
- manual ending confirmation

## Rules

- shootout score separate from match score
- sent-off players excluded
- confirmation required for ending

---

# 19. Livestream

## Included

- public livestream URL
- polling refresh
- score display
- timeline display
- phase display
- expiration
- manual stop

## Rules

- starts when match becomes active
- default expiration 24 hours
- maximum expiration 72 hours
- corrections visible while active

---

# 20. Finished Match Corrections

## Included

- edit scorer
- edit assist
- edit zones
- edit penalties
- edit substitutions
- edit shootout attempts

## Permissions

Allowed:
- coach
- administrator

Not allowed:
- trainer
- team manager

---

# 21. Basic Statistics

## Included

### Player Statistics

- matches played
- goals
- assists
- cards
- playing time
- attendance percentage

### Team Statistics

- wins
- draws
- losses
- goals scored
- goals conceded

## Filters

Included:
- season
- phase

## Rules

- shootout goals excluded from normal goal totals
- injured excluded from attendance denominator

---

# 22. Audit Logging

## Included

- correction logging
- value change logging
- user logging
- timestamp logging

## Rules

Every finished match correction must be logged.

---

# 23. Locking and Concurrency

## Included

- match edit locking
- timeout expiration
- lock refresh
- conflict prevention

## Rules

- only one active editor per match
- recommended timeout: 2 minutes
- no silent overwrite allowed

---

# 24. Internationalization

## Included

- translation files
- translation helper
- locale selection
- fallback language

Minimum MVP requirement:
- at least English support is complete
- the translation system exists for future locales
- full multi-language coverage is not required before first release

## Rules

- no hardcoded user-facing strings
- all visible labels translatable

---

# 25. Explicit MVP Exclusions

The MVP explicitly excludes:

- advanced analytics
- heatmaps
- tactical whiteboards
- AI recommendations
- push notifications
- chat systems
- social interaction
- external league integrations
- automatic fixture imports
- advanced dashboard widgets
- parent portals
- player photos
- advanced media uploads
- realtime websocket infrastructure
- mobile apps
- offline-first architecture
- advanced exports
- tournament systems
- training session management
- training attendance workflows
- post-match ratings

Minimal dashboard behavior is allowed in MVP only as navigation support.

It is not required as a separate MVP success criterion.

---

# 26. Technical MVP Constraints

The MVP must run on:
- shared hosting
- PHP
- MySQL
- plain CSS
- vanilla JavaScript

The MVP must not require:
- Node.js
- Laravel
- Docker
- Redis
- build pipelines
- frontend frameworks

---

# 27. MVP UI Priorities

The MVP UI must prioritize:
- mobile-first layout
- low interaction friction
- outdoor readability
- fast event registration
- calm visual hierarchy
- icon-first controls for repeated live-match actions where clarity is preserved
- a bottom navigation bar that keeps currently relevant sections reachable by thumb
- a stable bottom navigation bar for top-level destinations, with contextual in-screen controls for live-match subareas

The MVP UI must avoid:
- decorative animation
- dashboard overload
- excessive simultaneous information
- text-heavy control bars for common match actions when intuitive iconography can reduce clutter
- persistent low-value navigation items that occupy bottom-bar space in contexts where they are not needed
- treating volatile live-match actions as if they were permanent top-level navigation items

---

# 28. MVP Security Requirements

The MVP must include:
- prepared SQL statements
- CSRF protection
- output escaping
- server-side authorization
- secure sessions
- one-time magic links
- expiring authentication tokens
- hashed magic-link token storage
- secure session cookie flags
- HTTPS in production
- HSTS in production HTTPS deployments
- rate limiting for login and public livestream endpoints
- generic failure responses for public token-based endpoints
- no-store and noindex protections for public livestream responses
- concrete inactivity and absolute session lifetime rules

---

# 29. MVP Performance Targets

Target behavior:
- fast page loads
- responsive mobile interaction
- low server load
- lightweight assets
- minimal JavaScript footprint

The MVP should remain usable on:
- older smartphones
- unstable mobile networks

---

# 30. MVP Completion Definition

The MVP is complete when:

- the complete match flow works
- all included modules function together
- score consistency is reliable
- lineup consistency is reliable
- permissions work correctly
- corrections work correctly
- livestream works correctly
- no critical manual recovery is required

The MVP is not complete when:
- features merely exist in isolation
- core flows still break
- data consistency remains unreliable

When these completion criteria are satisfied for a formal release, that release must be versioned as `v1.0.0`.

---

# 31. Recommended First Public Test

Recommended first real-world test:

- one coach
- one team
- one live match
- real smartphone usage
- real mobile network
- real substitutions
- real corrections after match

The goal is not feature validation.

The goal is:
- friction discovery
- cognitive load discovery
- workflow validation

---

# 32. Final MVP Principle

The MVP should feel:
- fast
- calm
- focused
- reliable

The coach should never feel:
- overloaded
- slowed down
- distracted by the system

The match remains central.

The app remains supportive.

Nothing more.

---

# End
