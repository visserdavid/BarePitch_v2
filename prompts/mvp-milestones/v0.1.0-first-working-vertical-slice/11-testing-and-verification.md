# Testing and Verification — v0.1.0

# Purpose
Implement the automated test suite for the complete v0.1.0 vertical slice. All prior prompts (02–10) must be complete. Tests must cover the key correctness properties of the slice.

# Required Context
See `01-shared-context.md`. PHPUnit is declared in `composer.json` (from prompt 02). A separate test database must be configured.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — test scenario identifiers
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — testing requirements

# Scope

## Test database configuration

Add to `.env.example`:
```
DB_NAME_TEST=barepitch_test
```

Create a `phpunit.xml` or `phpunit.xml.dist`:
```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

`tests/bootstrap.php`:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
putenv('APP_ENV=testing');
// Load test DB config
require_once __DIR__ . '/../config/app.php';
```

`tests/TestCase.php` base class:
```php
abstract class TestCase extends \PHPUnit\Framework\TestCase {
    protected \PDO $db;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \App\Repositories\Database::connection();
        $this->db->beginTransaction();
        // Run migrations on test DB if not already run
    }

    protected function tearDown(): void {
        $this->db->rollBack();
        parent::tearDown();
    }
}
```

## Required test cases

### Schema install and seed (`tests/Integration/SchemaAndSeedTest.php`)
- Migrations run without SQL errors
- Seed creates exactly 1 club, 1 season, 1 phase, 1 team, 1 user, at least 1 formation, 18 players
- `SELECT COUNT(*) FROM players` returns 18 after seed

### Authorization (`tests/Integration/AuthorizationTest.php`)
- `POST /matches` without Policy approval (simulate non-coach role) returns 403
- CSRF missing on `POST /matches` returns 403

### Match preparation (`tests/Unit/MatchPreparationServiceTest.php`)
- Less than 11 players present: `prepare()` throws `PreparationValidationException`
- No formation selected: `prepare()` throws
- Unfilled starting position: `prepare()` throws
- Injured starter: `prepare()` throws
- Valid lineup: `prepare()` succeeds and match state becomes `prepared`
- Duplicate player in two positions: `prepare()` throws

### Start planned match rejection (`tests/Integration/LiveMatchTest.php`)
- `POST /matches/{id}/start` on a `planned` match returns error (state remains `planned`)
- `POST /matches/{id}/start` on a `prepared` match succeeds (state becomes `active`)

### Goal score integrity (`tests/Unit/ScoreServiceTest.php`)
- Register 2 own goals and 1 opponent goal in `match_events`
- `ScoreService::calculate($matchId)` returns `['home' => 2, 'away' => 1]`
- Score correct after simulated page reload (re-query from events)

### Finished match no-restart (`tests/Integration/FinishedMatchTest.php`)
- Set match state to `finished` in DB
- `POST /matches/{id}/start` returns safe error
- `POST /matches/{id}/prepare` returns safe error
- `POST /matches/{id}/events/goal` returns safe error
- `POST /matches/{id}/finish` returns safe error (already finished)

## PHP syntax check

```bash
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -v "No syntax errors"
```

Zero output = all files pass.

## Running tests

```bash
vendor/bin/phpunit --testdox
```

Expected: all tests green, no fatal errors.

# Out of Scope
- Adding new product behavior beyond fixes needed to make the v0.1.0 acceptance tests pass.
- Broad refactors, new frameworks, or features reserved for later milestones.
- Replacing documented manual verification with unreviewed assumptions.

# Architectural Rules
- Tests must verify server-side behavior, not only UI visibility.
- Any implementation fix made during this prompt must preserve the documented Route → Controller → Validator → Policy → Service → Repository → View layering.
- Use isolated test data and keep tests repeatable.

# Acceptance Criteria
- `vendor/bin/phpunit --testdox` runs without fatal errors
- All listed test scenarios pass
- PHP syntax check returns zero errors
- No test bypasses security checks in a way that hides real vulnerabilities
- Test teardown rolls back DB changes (no test pollution between tests)

# Verification
Run `vendor/bin/phpunit --testdox` and capture the output. All tests must pass. Run PHP syntax check and confirm zero errors.

# Handoff Note
`12-final-integration-review.md` performs the end-to-end manual walk-through and confirms the vertical slice is complete and ready for v0.2.0.
