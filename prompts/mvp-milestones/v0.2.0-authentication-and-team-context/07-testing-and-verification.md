# Testing and Verification — v0.2.0

# Purpose
Implement the automated test suite for v0.2.0 authentication and team context. All prior prompts in this bundle must be complete before running these tests.

# Required Context
See `01-shared-context.md`. PHPUnit must be available (`composer.json` with `phpunit/phpunit` dependency, from v0.1.0).

# Required Documentation
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — test scenario identifiers
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — testing requirements

# Scope

## Test coverage required

### Token Hashing (Unit — `tests/Unit/AuthServiceTest.php`)
- **Token hashing**: call `AuthService::generateToken()` against a test user; verify the value stored in `magic_login_tokens.token_hash` is the SHA-256 hash of the returned token, not the token itself
- **Hash comparison**: verify `AuthService::validateToken($token)` uses `hash_equals()` (not `===`) for comparison

### Token Expiry (Integration — `tests/Integration/AuthCallbackTest.php`)
- **Expired token**: create a token with `expires_at` in the past; call `GET /login/callback?token=...`; assert HTTP response is not a redirect and shows no token-specific error detail (generic failure)
- **Unexpired token**: create a valid token; call `GET /login/callback?token=...`; assert redirect to `/`

### Token One-Time Use (Integration — `tests/Integration/AuthCallbackTest.php`)
- **First use**: token consumed successfully, `used_at` set in DB
- **Second use**: same token used again; assert generic failure response; assert `used_at` not changed

### No-Role Access Denial (Integration — `tests/Integration/RouteProtectionTest.php`)
- Create a user with no entries in `user_team_role`
- Log in the user (simulate session with `user_id` set but `user_roles` empty)
- Attempt `GET /players`
- Assert redirect to `/no-access` (or the no-access response)

### Team Context Switching (Integration — `tests/Integration/TeamContextTest.php`)
- Create a user with roles for two teams
- Log in; verify redirect to `/teams/select`
- `POST /teams/select` with the first valid team — verify active team set in session
- `POST /teams/select` with a team the user has NO role for — verify server-side rejection (not just UI)

### Unauthenticated Protected Route Rejection (Integration — `tests/Integration/RouteProtectionTest.php`)
- Without a session: `GET /players` → assert redirect to `/login`
- Without a session: `POST /matches` → assert redirect to `/login`

### Unauthorized Write Rejection (Integration — `tests/Integration/RouteProtectionTest.php`)
- Log in as a user with trainer role
- Attempt `POST /matches` (coach/admin only route)
- Assert HTTP 403

### CSRF Protection (Integration — `tests/Integration/CsrfTest.php`)
- Submit `POST /matches` with a valid session but missing/invalid `_csrf` field
- Assert HTTP 403

### Developer Bypass Gate (Unit — `tests/Unit/CurrentUserTest.php`)
- Set `APP_ENV=production` in test environment
- Assert that the dev bypass code path is unreachable (mock or inspect the condition)

## Test database
- Use a separate test database configured via `DB_*_TEST` env vars or a test `.env`
- Each test that writes to DB must clean up (use transactions that are rolled back, or a `setUp`/`tearDown` truncation)

## Running tests
```bash
vendor/bin/phpunit --testdox
```

## PHP syntax checks
Run on all PHP files changed or added in this milestone:
```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```
All files must report "No syntax errors detected."

# Out of Scope
- Adding new authentication methods beyond the documented magic-link flow.
- Expanding role permissions or team-context behavior beyond v0.2.0.
- Later milestone player, match preparation, live match, or livestream features.

# Architectural Rules
- Authentication and authorization tests must assert server-side access control.
- Token, session, CSRF, and rate-limit behavior must fail safely.
- Test data must be isolated and repeatable.

# Acceptance Criteria
- All listed test scenarios pass with `vendor/bin/phpunit`
- PHP syntax check passes on all new/modified files
- Developer bypass not reachable when `APP_ENV` is not `local`
- No test relies on skipping authentication in a way that would hide real auth bugs

# Verification
Run `vendor/bin/phpunit --testdox` and show the test result summary.
Run PHP syntax check and confirm zero errors.

# Handoff Note
v0.3.0 builds player management and match creation flows on top of the stable authentication and team context established in this milestone. The magic-link login, session management, team context, and route protection are now the production authentication path.
