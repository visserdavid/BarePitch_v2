# Authorization and CSRF Audit — v0.5.0

# Purpose
Cross-cutting security review for all v0.5.0 live match routes. Verify every write route has CSRF and Policy checks, score is never blindly incremented, and failed writes leave no partial state.

# Required Context
See `01-shared-context.md`. All prior prompts (02–05) complete.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`

# Scope

## Route audit table

For each live match route, verify:

| Route | Auth | CSRF | Policy | State check | Notes |
|---|---|---|---|---|---|
| `POST /matches/{id}/start` | ✓ | ✓ | `canRegisterEvent()` | state=prepared | |
| `POST /matches/{id}/events/goal` | ✓ | ✓ | `canRegisterEvent()` | state=active | |
| `POST /matches/{id}/periods/end-first` | ✓ | ✓ | `canRegisterEvent()` | state=active + period check | |
| `POST /matches/{id}/periods/start-second` | ✓ | ✓ | `canRegisterEvent()` | state=active + period check | |
| `POST /matches/{id}/periods/end-second` | ✓ | ✓ | `canRegisterEvent()` | state=active + period check | |
| `POST /matches/{id}/finish` | ✓ | ✓ | `canFinish()` | state=active | |

For any cell that is not confirmed: open the controller method and add the missing check.

## Score integrity check

Grep the codebase for any of:
- `score_home`, `score_away`, `home_score`, `away_score` column updates in `UPDATE` statements
- Any SQL like `SET score = score + 1`

There must be zero such SQL updates. Score is derived from events only. If any are found, remove them and add a comment explaining the recalculation approach.

## Partial state check

For goal registration (`GoalEventService::register()`):
- Verify the method either succeeds fully or throws without writing any row
- Single `INSERT INTO match_events` — no partial state possible from a single-statement insert
- Confirm exception handling does not leave the event inserted without the calling context knowing

For start/finish (multi-step):
- Verify both `startMatch()` and `finishMatch()` use `beginTransaction()` + `commit()` / `rollBack()`
- Verify `rollBack()` is called in the catch block

## Output escaping spot check

Review all new `.php` view files in this milestone:
- Every `<?= $variable ?>` must be `<?= e($variable) ?>` or `<?= htmlspecialchars($variable, ENT_QUOTES, 'UTF-8') ?>`
- Fix any raw output

## Produce audit note

At the end of this review, add a comment to `app/Http/Controllers/LiveMatchController.php`:
```php
// Security audit v0.5.0 [date]: all routes verified for CSRF, auth, state checks, score integrity.
```

# Out of Scope
- New live match features beyond the routes implemented in prompts 02-05.
- Substitutions, cards, extra time, penalties, shootouts, livestream, corrections, and audit logging.
- UI redesign beyond fixing escaping or security-related defects found during the audit.

# Architectural Rules
- Controllers must enforce Policy checks before calling Services.
- CSRF protection must cover every state-changing route.
- Score remains derived from match events; do not persist manual score increments.
- Multi-step state transitions must be transactional and roll back on failure.

# Acceptance Criteria
- Every live match POST route has CSRF validation (via middleware) and Policy check (in controller)
- Zero SQL UPDATE statements that modify a score column
- `startMatch()` and `finishMatch()` use database transactions
- All view output uses output escaping

# Verification
- PHP syntax check all files
- Manually remove CSRF token from goal registration form → verify 403
- Manually log in as trainer → attempt POST /matches/{id}/events/goal → verify 403
- Grep for score column updates → verify zero results

# Handoff Note
`07-testing-and-verification.md` adds the automated test suite for v0.5.0.
