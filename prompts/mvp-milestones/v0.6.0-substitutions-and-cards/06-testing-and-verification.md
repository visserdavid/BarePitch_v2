# Testing and Verification — v0.6.0

# Purpose
Automated test suite for v0.6.0 substitutions and cards. All prior prompts must be complete.

# Required Context
See `01-shared-context.md`. PHPUnit and test database configured.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — SU-01 through SU-04, RC-01 through RC-03

# Scope

## Test coverage required

### Substitution scenarios (`tests/Integration/SubstitutionTest.php`)

**SU-01 through SU-04** from test scenarios doc. At minimum:
- SU-01: Valid substitution — outgoing moves to bench, incoming moves to field
- SU-02: Outgoing player not on field → `InvalidArgumentException`
- SU-03: Incoming player not on bench → `InvalidArgumentException`
- SU-04: Incoming player already on field (duplicate) → `InvalidArgumentException`
- Extra: Sent-off player as incoming → `InvalidArgumentException`
- Extra: Substitution is atomic — simulate failure of playing time stop; verify lineup not partially updated

### Red Card Scenarios (`tests/Integration/CardTest.php`)

**RC-01 through RC-03** from test scenarios doc. At minimum:
- RC-01: Red card issued → player's `sent_off = 1` in `match_lineup`; player removed from active field player list
- RC-02: Red card issued → `red_card` event in `match_events`
- RC-03: Red-carded player attempted as penalty taker → `getEligiblePenaltyTakers()` excludes them

### Lineup Duplicate Prevention (`tests/Unit/SubstitutionServiceTest.php`)
- Attempt substitution where incoming is already an active field player → exception
- After successful substitution, verify no player appears twice in `is_starter=1` lineup

### Playing Time Start/Stop (`tests/Unit/PlayingTimeServiceTest.php`)
- Start player at minute 0; stop at minute 45; verify `seconds_played = 2700`
- Start player at minute 60; stop at minute 90; verify `seconds_played = 1800`
- Stop player who has no active entry → no exception (graceful handling)

### Unauthorized Writes (`tests/Integration/CardAuthorizationTest.php`)
- Trainer `POST /matches/{id}/events/yellow-card` → 403
- Team manager `POST /matches/{id}/events/red-card` → 403
- Coach `POST /matches/{id}/events/yellow-card` on active match → 200

### CSRF Rejection
- `POST /matches/{id}/events/substitution` without `_csrf` → 403
- `POST /matches/{id}/events/red-card` without `_csrf` → 403

### Red Card Transaction Test (`tests/Unit/CardServiceTest.php`)
- If `sent_off` update fails mid-transaction, verify `red_card` event is also rolled back

## PHP syntax check and test run

```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
vendor/bin/phpunit --testdox
```

# Out of Scope
- Penalties, extra time, shootouts, livestream, corrections, or audit logging.
- Changing event semantics outside substitutions, playing time, cards, and red-card restrictions.
- Adding analytics beyond the playing-time checks required here.

# Architectural Rules
- Substitution and card behavior must be enforced server-side.
- Playing time must be derived consistently and stored in seconds where persisted.
- Red-card restrictions must affect lineup, substitution, and eligibility checks consistently.

# Acceptance Criteria
- SU-01 through SU-04 pass
- RC-01 through RC-03 pass
- Lineup duplicate prevention tests pass
- Playing time start/stop accuracy verified
- Authorization tests pass
- CSRF tests pass
- PHP syntax check: zero errors

# Verification
Run `vendor/bin/phpunit --testdox` and show summary. Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.7.0 adds extra time, penalties during match, and the penalty shootout flow. The `sent_off` flag and `getEligiblePenaltyTakers()` method established here are used directly by penalty taker selection in that milestone.
