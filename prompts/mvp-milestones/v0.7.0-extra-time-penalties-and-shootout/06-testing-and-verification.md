# Testing and Verification — v0.7.0

# Purpose
Automated test suite for v0.7.0 extra time, penalties, and shootout. All prior prompts must be complete.

# Required Context
See `01-shared-context.md`. PHPUnit and test database configured.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — SC-03 through SC-05, PS-01 through PS-05, RC-03

# Scope

## Test coverage required

### Score Calculation with Penalties (`tests/Unit/ScoreServiceTest.php`)

**SC-03, SC-04, SC-05** from test scenarios doc:
- SC-03: Scored in-match penalty — score increases by 1 (via `ScoreService::calculate()`)
- SC-04: Missed in-match penalty — score unchanged
- SC-05: Normal score after shootout attempts unchanged (add 3 shootout goals, verify `ScoreService::calculate()` still shows pre-shootout score)

### Shootout Scenarios (`tests/Integration/ShootoutTest.php`)

**PS-01 through PS-05** from test scenarios doc. At minimum:
- PS-01: Shootout starts after regular time ends
- PS-02: Shootout attempt recorded with correct round, order, taker, outcome
- PS-03: Shootout goals do NOT alter `ScoreService::calculate()` result
- PS-04: Sent-off player rejected as shootout taker
- PS-05: Duplicate attempt order in same round rejected

### Red Card + Penalty Restriction (`tests/Integration/RedCardPenaltyTest.php`)

**RC-03**: Issue red card; attempt to use the red-carded player as an in-match penalty taker → rejected

### Invalid Shootout Attempt Order (`tests/Unit/ShootoutServiceTest.php`)
- Insert attempt with `round=1, attempt_order=1` for own team
- Attempt to insert another with `round=1, attempt_order=1` for own team → exception

### No-Assist Penalty Validation (`tests/Unit/PenaltyEventServiceTest.php`)
- Call `PenaltyEventService::register()` with `assist_player_id` set → `InvalidArgumentException`

### Invalid State Transitions (`tests/Integration/ExtraTimeStateTest.php`)
- Attempt `startExtraTime()` before regular halves end → `InvalidStateException`
- Attempt to record shootout attempt before shootout started → `InvalidStateException`
- Attempt `startExtraTime()` when `extra_time_duration` is null → `InvalidArgumentException`

### Score Isolation Test (`tests/Unit/ScoreIsolationTest.php`)
- Start a match; register 2 goals via `GoalEventService`
- Start shootout; register 3 scored shootout attempts via `ShootoutService::recordAttempt()`
- `ScoreService::calculate()` returns home=2, away=0 (not affected by shootout)
- `ShootoutService::getShootoutScore()` returns own=3, opponent=0

## PHP syntax check and test run

```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
vendor/bin/phpunit --testdox
```

# Out of Scope
- Livestream, finished-match corrections, audit logging, or v0.9.0 hardening work.
- Combining shootout score into normal match score.
- Changing the documented match state machine.

# Architectural Rules
- Normal score and shootout score must remain computationally separate.
- Invalid state transitions must be covered by automated or manual tests.
- Sent-off player restrictions must be tested through server-side eligibility logic.

# Acceptance Criteria
- SC-03 through SC-05 pass
- PS-01 through PS-05 pass
- RC-03 pass
- Score isolation confirmed: shootout goals do not affect normal score
- No-assist validation confirmed
- Invalid state transitions rejected
- PHP syntax check: zero errors

# Verification
Run `vendor/bin/phpunit --testdox` and show summary. Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.8.0 adds the public livestream and finished-match correction flows. The `sent_off` flag and separate score tracking established in this milestone are prerequisites for the correction score recalculation in v0.8.0.
