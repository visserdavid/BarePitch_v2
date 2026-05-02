# Testing and Verification — v0.9.0

# Purpose
Complete the test suite for v0.9.0 and perform the manual MVP acceptance walk-through. All prior prompts (02–07) must be complete. This is the discipline gate: every security checklist item (S-01 through S-14) and data consistency checklist item (D-01 through D-07) from the shared context must pass.

# Required Context
See `01-shared-context.md`. PHPUnit configured. Test database available. All services from v0.1.0 through v0.9.0 implemented.

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — SE-01 through SE-04 and the full MVP acceptance scenario
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — authorization matrix for spot-check

# Scope

## Security Scenario Tests (`tests/Integration/SecurityTest.php`)

**SE-01: CSRF rejection**
- POST to any write route without `_csrf` field → assert 400 or redirect with CSRF error
- POST with wrong `_csrf` value → same result
- POST with correct `_csrf` value → proceeds normally

**SE-02: Unauthenticated route rejection**
- GET `/matches` without session → assert redirect to `/login`
- POST `/matches/{id}/start` without session → assert redirect to `/login`
- Verify no data mutation occurred

**SE-03: Unauthorized role rejection**
- Trainer attempts POST `/matches/{id}/events/goal` → assert 403
- Team manager attempts POST `/players` → assert 403
- Assert no data mutation in either case

**SE-04: Magic-link token is one-time use**
- Generate a magic-link token via `AuthService`
- Consume it via GET `/login/callback?token={raw}` → assert redirect to authenticated state
- Attempt to consume the same raw token again → assert generic failure (not 200 + authenticated)
- Assert token row has `used_at IS NOT NULL`

**SE-05: Magic-link token expiry**
- Generate a token; set `expires_at` to the past in the DB
- GET `/login/callback?token={raw}` → assert generic failure
- Assert response does not reveal token details

**SE-06: Dev bypass gated on APP_ENV**
- Set `APP_ENV = production` in test env
- Attempt to access any dev-bypass route (if one exists)
- Assert 404 or redirect — the bypass is unreachable

## Regression Tests (`tests/Integration/RegressionTest.php`)

**Score integrity regression**
- Register 3 goals; verify score = 3–0
- Register 1 opponent goal; verify score = 3–1
- Finish match; correct one goal's team_side to opponent; verify score = 2–2
- Assert `match.status` remains `finished`

**Lineup integrity regression**
- Start match; perform substitution (player A out, player B in)
- Assert player A no longer on field in lineup; player B on field
- Register red card for player B; assert player B marked sent_off; field count decreases
- Assert sent-off player cannot be selected as penalty taker

**Livestream expiry regression**
- Create token; finish match; set `expires_at` in the past
- GET `/live/{token}` → generic failure
- GET `/live/{token}/data` → same generic failure

**Correction audit regression**
- Correct a match event as coach; assert `audit_log` row written with correct fields
- Correct another event as coach; assert second `audit_log` row written
- Assert `audit_log` has no update or delete paths reachable

**Lock conflict regression**
- User A acquires lock; User B attempts to acquire → `LockConflictException`; source data unchanged

## Authorization Spot-Check (`tests/Integration/AuthorizationMatrixTest.php`)

For each role (coach, administrator, trainer, team_manager), verify at least one write route that they should be blocked from returns 403:

| Route | Blocked roles |
|---|---|
| POST /players | trainer, team_manager |
| POST /matches/{id}/events/goal | trainer, team_manager |
| POST /matches/{id}/substitutions | trainer, team_manager |
| POST /matches/{id}/events/{id}/update (correction) | trainer, team_manager |
| GET /admin/users | coach, trainer, team_manager |

At minimum 11 spot checks total (mix of blocked and allowed).

## Statistics Tests (`tests/Unit/StatisticsServiceTest.php`)

- Player stats: register 2 goals and 1 assist for a player in a finished match; assert `getPlayerStats()` returns goals=2, assists=1 for that player
- Team stats: 2 matches with known scores; assert team wins/draws/losses/goals correct
- Season filter: stats for season A do not include season B data

## i18n Tests (`tests/Unit/TranslationHelperTest.php`)

- `__(string $key)` returns the English string for a known key
- `__(string $key)` returns the key itself (not an error) for an unknown key
- Locale switching works: when locale is changed, `__()` returns the new locale's value

## Manual MVP Acceptance Walk-Through

Perform the following scenario manually (or automate as a Selenium/Playwright test if tools are available; otherwise document results inline):

1. Register and log in via magic link
2. Select a team (team context)
3. Create a season, phase, and match as administrator
4. Add players and manage lineup
5. Prepare a match (attendance, formation, lineup grid)
6. Start the match
7. Register a goal (scorer + assist + zone)
8. Register a missed penalty (score unchanged)
9. Perform a substitution
10. Issue a yellow card
11. Issue a red card (confirm; verify sent-off restrictions)
12. End first half; start second half
13. Register another goal
14. End second half; start extra time
15. End extra time; start penalty shootout
16. Register 5 rounds of shootout attempts (own + opponent)
17. Finish the shootout and match
18. View match summary (normal score + shootout score separate)
19. Share livestream link; view as unauthenticated user
20. Correct a goal's scorer as coach; verify audit log and score recalculation
21. View basic player statistics
22. View basic team statistics
23. Verify all i18n labels on touched screens use `__()` helper (spot-check 5 screens)

Record pass/fail for each step. Any failed step is a blocking issue for v0.9.0 completion.

## PHP Syntax Check and Full Test Run

```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
vendor/bin/phpunit --testdox
```

# Out of Scope
- New MVP features beyond fixes required by the hardening acceptance criteria.
- Release packaging and release notes; those are handled in v1.0.0.
- Rewriting documentation unrelated to behavior changed or verified in this milestone.

# Architectural Rules
- Regression tests must cover security, authorization, data consistency, and user-facing MVP flows.
- Fixes discovered during testing must preserve documented source-of-truth and derived-data policies.
- Public endpoints must keep safe headers and generic failure behavior.

# Acceptance Criteria
- SE-01 through SE-06 pass
- Score integrity, lineup integrity, livestream expiry, correction audit, lock conflict regression tests pass
- Authorization spot-check: all 11+ checks match the matrix
- Statistics tests pass (player and team)
- i18n tests pass
- Manual MVP acceptance walk-through: all 23 steps pass
- PHP syntax check: zero errors
- PHPUnit: zero failures, zero errors

# Verification
Show the `vendor/bin/phpunit --testdox` summary. Show the PHP syntax check result. Record the manual walk-through result table (step + pass/fail). If any step fails, document the failure before closing this prompt.

# Handoff Note
When all acceptance criteria above pass, the codebase is the MVP candidate. `v1.0.0-mvp-release/` is the final milestone: pre-release verification, release-blocking fixes, signed-off checklist, and release notes.
