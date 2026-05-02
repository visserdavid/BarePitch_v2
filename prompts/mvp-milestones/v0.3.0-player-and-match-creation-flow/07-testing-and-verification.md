# Testing and Verification — v0.3.0

# Purpose
Implement the automated test suite for v0.3.0 player and match creation. All prior prompts must be complete.

# Required Context
See `01-shared-context.md`. PHPUnit from v0.1.0. Test database configured.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — test scenario identifiers

# Scope

## Test coverage required

### Player Validation (`tests/Unit/PlayerServiceTest.php`)
- Missing player name: `PlayerService::create()` throws validation error
- Invalid position (not in docs enum): validator rejects
- Duplicate jersey number for same team/season: rejected with descriptive error
- Valid player data: player created; `player_season_context` row created; player appears in team list

### Player Authorization (`tests/Integration/PlayerAuthorizationTest.php`)
- Team manager `POST /players`: 200 (success)
- Coach `POST /players`: 403
- Trainer `POST /players`: 403
- Team manager `POST /players/{id}/deactivate`: success
- Coach `POST /players/{id}/deactivate`: 403

### Deactivation Behavior (`tests/Integration/PlayerDeactivationTest.php`)
- Create player; create a fake `match_event` referencing that player
- Deactivate the player
- Assert: `players.active = 0` for that player
- Assert: `match_events` row still exists and references the player
- Assert: player does not appear in `PlayerRepository::getActiveForTeamSeason()`

### Season Context Creation (`tests/Unit/PlayerServiceTest.php`)
- Create a player via `PlayerService::create()`
- Assert: `player_season_context` row created for the active team/season with correct jersey_number and position

### External Guest Creation (`tests/Integration/GuestPlayerTest.php`)
- Create external guest via `PlayerService::createExternalGuest()`
- Assert: `is_external_guest = 1` on the player row
- Assert: external guest does NOT appear in `PlayerRepository::getActiveForTeamSeason()`
- Assert: external guest appears in `GuestPlayerRepository::getExternalGuests()`

### Match Creation Authorization (`tests/Integration/MatchAuthorizationTest.php`)
- Coach `POST /matches`: success
- Trainer `POST /matches`: 403
- Team manager `POST /matches`: 403
- Administrator `POST /matches`: success

### Invalid Phase/Team Rejection (`tests/Integration/MatchCreationTest.php`)
- Create a phase for a different team's season
- Submit `POST /matches` with that phase_id
- Assert: server-side rejection with error message

### Trainer View-Only (`tests/Integration/MatchAuthorizationTest.php`)
- Trainer `GET /matches`: 200
- Trainer `GET /matches/{id}`: 200
- Trainer `POST /matches/{id}/edit`: 403

### Team Manager View-Only for Matches (`tests/Integration/MatchAuthorizationTest.php`)
- Team manager `GET /matches`: 200
- Team manager `POST /matches`: 403

### Admin Setup Authorization (`tests/Integration/AdminAuthorizationTest.php`)
- Administrator `GET /admin/clubs`: 200
- Coach `GET /admin/clubs`: 403
- Trainer `GET /admin/clubs`: 403
- Team manager `GET /admin/clubs`: 403

## PHP syntax check
```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

## Running tests
```bash
vendor/bin/phpunit --testdox
```

# Out of Scope
- Match preparation, live match behavior, substitutions, cards, or later milestone features.
- Adding permissions not listed in the authorization matrix.
- Broad UI redesign beyond corrections required for testable behavior.

# Architectural Rules
- Tests must cover authorization, validation, and data integrity for each write path.
- Test setup must use documented domain entities and preserve historical references.
- Fixes made during testing must keep controllers, services, repositories, and policies in their documented layers.

# Acceptance Criteria
- All listed test scenarios pass
- PHP syntax check: zero errors
- `vendor/bin/phpunit --testdox` shows all green
- Deactivation test confirms historical data preservation

# Verification
Run `vendor/bin/phpunit --testdox` and show test result summary.
Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.4.0 implements the complete match preparation flow using the players and matches established in this milestone. The `prepared` state transition, lineup grid, guest selection, and all preparation validation rules are built on top of what exists here.
