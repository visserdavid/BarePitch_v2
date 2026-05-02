# Testing and Verification — v0.5.0

# Purpose
Automated test suite for v0.5.0 live match core. All prior prompts must be complete.

# Required Context
See `01-shared-context.md`. PHPUnit and test database configured from v0.1.0.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — MS-01 through MS-05, SC-01 through SC-02

# Scope

## Test coverage required

### Match State Transitions (`tests/Integration/LiveMatchStateTest.php`)

**MS-01 through MS-05** from test scenarios doc. At minimum:
- MS scenario: start `planned` match → rejected, state unchanged
- MS scenario: start `prepared` match → succeeds, state=`active`, first period created
- MS scenario: start `active` match → rejected
- MS scenario: finish `active` match → succeeds, state=`finished`
- MS scenario: finish `finished` match → rejected
- MS scenario: all event routes reject `finished` match

### Score Calculation (`tests/Unit/ScoreServiceTest.php`)

**SC-01, SC-02** from test scenarios doc. At minimum:
- Insert 2 `goal_own` events and 1 `goal_opponent` event for a match
- `ScoreService::calculate($matchId)` returns `['home' => 2, 'away' => 1]`
- Insert 0 events: returns `['home' => 0, 'away' => 0]`
- Verify no score column is updated in `match_events` (query the column directly)

### Score Recalculation (`tests/Unit/ScoreServiceTest.php`)
- Register 3 goals via `GoalEventService::register()`
- Call `ScoreService::calculate()` → verify correct result
- Simulate "page reload" by clearing any in-memory state and calling `ScoreService::calculate()` again → same result

### Invalid Period Transitions (`tests/Integration/PeriodManagementTest.php`)
- Attempt `endFirstHalf()` when no `first_half` period exists → exception
- Attempt `startSecondHalf()` when `first_half` period not yet ended → exception
- Attempt `endSecondHalf()` when no `second_half` period → exception
- Attempt `startSecondHalf()` when `second_half` already exists → exception

### Authorization (`tests/Integration/LiveMatchAuthorizationTest.php`)
- Trainer: `POST /matches/{id}/events/goal` → 403
- Team manager: `POST /matches/{id}/start` → 403
- Coach: `POST /matches/{id}/events/goal` on active match → 200

### CSRF Rejection
- `POST /matches/{id}/events/goal` without `_csrf` field → 403
- `POST /matches/{id}/start` without `_csrf` field → 403

### Goal Validation
- Own goal with assist = scorer → `InvalidArgumentException`
- Opponent goal with assist provided → `InvalidArgumentException`
- Own goal with scorer not on field → `InvalidArgumentException`
- Own goal with invalid zone value → validator rejects

## PHP syntax check
```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

## Running tests
```bash
vendor/bin/phpunit --testdox
```

# Out of Scope
- Substitutions, cards, penalties, extra time, shootouts, livestream, corrections, or audit logging.
- Adding score storage shortcuts or manual score mutation.
- Broad UI redesign beyond fixes needed for v0.5.0 behavior.

# Architectural Rules
- Tests must prove score is derived from event records.
- Invalid state transitions must be rejected by server-side logic.
- State-changing routes must require authentication, authorization, and CSRF.

# Acceptance Criteria
- MS-01 through MS-05 scenarios pass
- SC-01, SC-02 scenarios pass
- Score recalculation test passes
- Invalid period transitions rejected correctly
- Authorization and CSRF tests pass
- PHP syntax check: zero errors

# Verification
Run `vendor/bin/phpunit --testdox` and show summary. Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.6.0 adds substitutions, playing time tracking, yellow cards, and red cards to the live match flow.
