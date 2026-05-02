# Testing and Verification — v0.4.0

# Purpose
Implement the automated test suite for v0.4.0 match preparation. All prior prompts in this bundle must be complete.

# Required Context
See `01-shared-context.md`. PHPUnit available from v0.1.0.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — MP-01 through MP-06, LU-01 through LU-04, GP-01 through GP-03

# Scope

## Test coverage required

### Match Preparation Scenarios (Integration — `tests/Integration/MatchPreparationTest.php`)

Reference the exact scenario IDs from `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`. Implement all of:

**MP-01 through MP-06** (match preparation data integrity scenarios):
For each scenario:
- Set up the database in the described state
- Call `POST /matches/{id}/prepare` (or call `MatchPreparationService::prepare()` directly)
- Assert the expected outcome (blocked with correct error OR succeeded with correct state)

At minimum, the following must be explicitly tested:
- MP scenario: fewer than 11 present — assert `PreparationValidationException` thrown and match state = `planned`
- MP scenario: exactly 11 present — assert preparation succeeds
- MP scenario: more than max players present — assert blocked
- MP scenario: no formation selected — assert blocked
- MP scenario: all formation positions filled with valid present players — assert preparation succeeds
- MP scenario: one formation position unfilled — assert blocked

### Lineup Integrity Scenarios (Unit — `tests/Unit/LineupServiceTest.php` + Integration)

**LU-01 through LU-04**:
- LU scenario: duplicate player in two starting positions — assert validator rejects
- LU scenario: duplicate position slot used twice — assert validator rejects
- LU scenario: injured player in starting position — assert prepare blocked
- LU scenario: bench player has null coordinates — assert bench record stored with null x/y

### Guest Player Scenarios (Integration — `tests/Integration/GuestPlayerTest.php`)

**GP-01 through GP-03**:
- GP scenario: internal guest from same club — assert added successfully
- GP scenario: internal guest attempt from different club — assert server-side rejection with error
- GP scenario: external guest creation and reuse — assert guest persists, can be added to a second match

### Authorization Tests (`tests/Integration/PreparationAuthTest.php`)
- Trainer attempting `POST /matches/{id}/prepare` — assert 403
- Team manager attempting `POST /matches/{id}/prepare` — assert 403
- Coach attempting prepare — assert succeeds (with valid lineup)

### CSRF Tests
- `POST /matches/{id}/prepare` without CSRF token — assert 403
- `POST /matches/{id}/attendance` without CSRF token — assert 403

### State Guard Tests
- Attempt to `POST /matches/{id}/prepare` on a match in `prepared` state — assert `InvalidStateException`
- Attempt to `POST /matches/{id}/prepare` on a match in `active` state — assert `InvalidStateException`

## Test database setup
Each test that modifies the DB should use a transaction rolled back in `tearDown()`, or truncate affected tables in `setUp()`.

```php
protected function setUp(): void {
    parent::setUp();
    $this->db->beginTransaction();
    // seed minimum required data
}

protected function tearDown(): void {
    $this->db->rollBack();
    parent::tearDown();
}
```

## PHP syntax checks
```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

## Running tests
```bash
vendor/bin/phpunit --testdox tests/
```

# Out of Scope
- Live match events, substitutions, cards, penalties, livestream, corrections, or audit logging.
- Changing lineup, attendance, or preparation rules beyond documented fixes needed for this milestone.
- Adding UI features that are not required to verify preparation.

# Architectural Rules
- Preparation tests must assert server-side validation and authorization.
- The prepare action must remain transactional.
- Lineup and attendance integrity must be verified from persisted data, not inferred from UI state alone.

# Acceptance Criteria
- All MP-01 through MP-06 scenarios pass
- All LU-01 through LU-04 scenarios pass
- All GP-01 through GP-03 scenarios pass
- Authorization tests pass
- CSRF tests pass
- State guard tests pass
- PHP syntax check reports zero errors

# Verification
Run `vendor/bin/phpunit --testdox` and show the test result summary.
Run PHP syntax check and confirm zero errors.
Confirm no test bypasses authentication in a way that would mask real auth bugs.

# Handoff Note
v0.5.0 builds the live match core on top of the `prepared` state established in this milestone. The prepare validation rules are the gateway to live match execution.
