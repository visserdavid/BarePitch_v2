# Testing and Verification — v0.8.0

# Purpose
Automated test suite for v0.8.0 livestream, corrections, and audit. All prior prompts (02–07) must be complete before running these tests.

# Required Context
See `01-shared-context.md`. PHPUnit configured, test database available. `LockService`, `AuditService`, `ScoreRecalculationService`, and `CorrectionService` must all be implemented.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — LS-01 through LS-04, AU-01 through AU-03, LK-01 through LK-04, SC-06

# Scope

## Livestream Tests (`tests/Integration/LivestreamTest.php`)

**LS-01: Public link works without login**
- Create a match; transition to active (which creates a token)
- Load the raw token value from test setup
- Make a GET request to `/live/{token}` without a session
- Assert HTTP 200 and that the response body contains the current score or match state

**LS-02: Expired token is inaccessible**
- Create a token with `expires_at = NOW() - 1 second`
- GET `/live/{token}` → assert generic failure (HTTP 200 with error page, or 404/410 — verify which per docs)
- Assert the response does not reveal whether the token ever existed

**LS-03: Stopped token is inaccessible**
- Create a valid token; set `stopped_at = NOW()`
- GET `/live/{token}` → assert generic failure
- Assert response is identical to the expired token response (no detail difference)

**LS-04: Polling returns corrected data after correction**
- Start a match; register a goal; finish the match
- Correct the goal's `team_side` from `own` to `opponent` via `CorrectionService`
- GET `/live/{token}/data` → assert the response now reflects the corrected score (goals_scored decreased, goals_conceded increased)

**Additional: Public headers**
- GET `/live/{token}` for a valid token → assert response headers include `Cache-Control: no-store` and `X-Robots-Tag: noindex`

## Audit Logging Tests (`tests/Integration/AuditLogTest.php`)

**AU-01: Correction by coach writes audit entry**
- Set up finished match with a goal event
- Correct `team_side` of the goal via `CorrectionService` as coach user
- Assert `audit_log` contains one row with: correct `user_id`, `match_id`, `entity_type = match_event`, `field_name = team_side`, non-null `old_value_json`, non-null `new_value_json`

**AU-02: Trainer cannot correct (authorization check)**
- Set up finished match
- Attempt correction via `CorrectionService` as trainer user (or via HTTP POST as trainer)
- Assert HTTP 403 (or `AuthorizationException` at the service layer)
- Assert zero rows added to `audit_log`

**AU-03: Audit entry is in same transaction as correction**
- Inject a mock `AuditService` that throws on `log()`
- Attempt correction via `CorrectionService`
- Assert exception propagates
- Assert the source event row was NOT modified (transaction rolled back)

## Locking Tests (`tests/Integration/LockServiceTest.php`)

**LK-01: Lock acquisition when no lock exists**
- No lock row for the match
- Call `LockService::acquireOrRefresh($matchId, $userId)`
- Assert lock row created with correct `user_id` and `expires_at` in the future

**LK-02: Second user blocked while lock is active**
- User A acquires lock
- User B calls `LockService::acquireOrRefresh($matchId, $userBId)` within the lock lifetime
- Assert `LockConflictException` thrown for User B
- Assert lock row still owned by User A

**LK-03: Expired lock replaced**
- Lock row exists with `expires_at = NOW() - 1 second`
- User B calls `LockService::acquireOrRefresh($matchId, $userBId)`
- Assert no exception; assert lock row now owned by User B

**LK-04: Lock refresh extends expiry**
- User A acquires lock; record initial `expires_at`
- Wait 1 second (or mock time)
- User A calls `LockService::acquireOrRefresh($matchId, $userAId)` again
- Assert `expires_at` updated to be later than the initial value

## Score Recalculation Tests (`tests/Unit/ScoreRecalculationServiceTest.php`)

**SC-06: Score correction recalculates correctly**
- Insert a match with 2 own goals and 1 opponent goal in `match_event`
- Assert initial `goals_scored = 2`, `goals_conceded = 1`
- Change one `match_event.team_side` from `own` to `opponent`
- Call `ScoreRecalculationService::recalculateMatchScore()`
- Assert `goals_scored = 1`, `goals_conceded = 2`
- Assert `match.status` remains `finished`

**Shootout recalculation**
- Insert 4 own scored and 2 opponent scored in `penalty_shootout_attempt`
- Call `ScoreRecalculationService::recalculateShootoutScore()`
- Assert shootout score columns: own = 4, opponent = 2
- Change one attempt outcome from `scored` to `missed`; recalculate
- Assert own = 3, opponent = 2

**Zero-event match**
- No events in `match_event`; call `recalculateMatchScore()`
- Assert no error; `goals_scored = 0`, `goals_conceded = 0`

## Unauthorized Correction Tests (`tests/Integration/CorrectionAuthTest.php`)

- POST `/matches/{id}/events/{event_id}/update` as trainer → 403, source row unchanged, no audit entry
- POST `/matches/{id}/events/{event_id}/update` as team_manager → 403, source row unchanged, no audit entry
- POST `/matches/{id}/events/{event_id}/update` unauthenticated → redirect to login (not 500)

## Token Generic Failure Test (`tests/Integration/LivestreamPublicTest.php`)

- Expired token response must be identical to non-existent token response
- Stopped token response must be identical to non-existent token response
- Assert none of these responses include any of: "expired", "stopped", "not found", "invalid" in the response body (or whatever strings would reveal token state — verify which are safe per docs)

## PHP Syntax Check and Test Run

```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
vendor/bin/phpunit --testdox
```

# Out of Scope
- New correction types beyond those implemented in prompts 02-07.
- MVP hardening tasks reserved for v0.9.0 unless required to make v0.8.0 tests pass.
- Replacing audit logging with mutable history or deleting audit rows.

# Architectural Rules
- Tests must verify token safety, locking, correction transactions, score recalculation, and audit persistence from server-side state.
- Correction behavior must preserve finished match status.
- Audit logs are append-only and must not expose sensitive token material.

# Acceptance Criteria
- LS-01 through LS-04 pass
- AU-01 through AU-03 pass
- LK-01 through LK-04 pass
- SC-06 passes
- Unauthorized correction blocked (403) for trainer and team_manager
- Public token generic failure confirmed — responses indistinguishable across expired/stopped/non-existent
- No-store and noindex headers confirmed on public livestream routes
- PHP syntax check: zero errors
- All prior milestone tests remain passing (no regressions)

# Verification
Run `vendor/bin/phpunit --testdox` and show the summary. Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.9.0 closes security, consistency, and authorization gaps across the full codebase. The `AuditService`, `LockService`, and `ScoreRecalculationService` established here are prerequisites for the v0.9.0 data consistency review in `05-data-consistency-review.md`.
