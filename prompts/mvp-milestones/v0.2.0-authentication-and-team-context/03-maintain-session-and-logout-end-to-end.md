# Maintain Session and Logout End to End

# Purpose
Implement secure session creation, session lifetime enforcement (idle and absolute), logout, and proper cookie configuration. This completes the `SessionHelper::createSession()` stub from prompt 02.

# Required Context
See `01-shared-context.md`. Auth callback from `02-request-and-consume-magic-link-end-to-end.md` calls `SessionHelper::createSession($user)`.

# Required Documentation
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — session configuration requirements
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — session security rules

# Scope

## Session Helper: `app/Http/Helpers/SessionHelper.php`

### `SessionHelper::start()`
Called at the start of every request (in the front controller or a bootstrap file). Must:
1. Configure cookie parameters before `session_start()`:
   ```php
   session_set_cookie_params([
       'lifetime' => 0,           // browser session cookie; lifetime enforced server-side
       'path'     => '/',
       'domain'   => '',
       'secure'   => isHttps(),   // true when HTTPS, false in local dev
       'httponly' => true,
       'samesite' => 'Lax',
   ]);
   session_name('bp_session');    // non-default session name
   session_start();
   ```
2. Enforce idle timeout: if `$_SESSION['last_activity']` is set and `time() - $_SESSION['last_activity'] > IDLE_TIMEOUT`, call `SessionHelper::destroy()` and redirect to `/login`
3. Enforce absolute timeout: if `$_SESSION['session_started']` is set and `time() - $_SESSION['session_started'] > ABSOLUTE_TIMEOUT`, call `SessionHelper::destroy()` and redirect to `/login`
4. Update `$_SESSION['last_activity'] = time()` on every request

### `SessionHelper::createSession(array $user)`
Called from `AuthController::handleCallback()` after successful token validation. Must:
1. Call `session_regenerate_id(true)` to prevent session fixation
2. Set `$_SESSION['user_id'] = $user['id']`
3. Set `$_SESSION['session_started'] = time()`
4. Set `$_SESSION['last_activity'] = time()`
5. Store user roles in session: load from `user_team_roles` by `user_id` and store as `$_SESSION['user_roles']` — an array of `['team_id' => ..., 'role' => ...]` entries

### `SessionHelper::destroy()`
1. Unset all session variables: `$_SESSION = []`
2. Delete the session cookie: `setcookie(session_name(), '', time() - 3600, '/')`
3. Destroy the session: `session_destroy()`

### `SessionHelper::getCurrentUserId(): ?int`
Returns `$_SESSION['user_id'] ?? null`

## Session lifetime configuration
In `config/app.php` or read from `.env`:
- `SESSION_IDLE_TIMEOUT` — idle timeout in seconds (default: 1800 = 30 minutes; use value from docs if specified)
- `SESSION_ABSOLUTE_TIMEOUT` — absolute timeout in seconds (default: 28800 = 8 hours; use value from docs if specified)

Define named constants or config values — do not hardcode magic numbers.

## Logout route
- `POST /logout` — CSRF required
- Controller calls `SessionHelper::destroy()`
- Redirects to `GET /login`
- Add logout link to navigation

## Developer bypass update
In `app/Http/Helpers/CurrentUser.php` (from v0.1.0), update to:
```php
if (getenv('APP_ENV') === 'local' && isset($_GET['dev_login'])) {
    // Developer bypass still active in local env only
} else {
    $userId = SessionHelper::getCurrentUserId();
    // ... load user from DB
}
```
Ensure the bypass is ONLY reachable when `APP_ENV=local`. No other environment must reach this code path.

## Bootstrap integration
In `public/index.php` (front controller), add `SessionHelper::start()` as the first call after autoloading, before routing.

# Out of Scope
- Team context and active team selection (prompt 04)
- Route authentication guards (prompt 05)
- Magic-link token generation (prompt 02)

# Architectural Rules
- `SessionHelper` is a stateless helper class — no constructor dependencies
- No session manipulation outside of `SessionHelper` (controllers must not call `$_SESSION` directly)
- CSRF token generation: add `SessionHelper::getCsrfToken(): string` that generates and stores a CSRF token in session — used by forms; CSRF validation is the responsibility of prompt 05's middleware

# Acceptance Criteria
- Session uses `HttpOnly=true`, `SameSite=Lax` cookie flags
- Session cookie uses a non-default name (`bp_session`)
- Session is destroyed and user redirected to `/login` after idle timeout passes
- Session is destroyed and user redirected to `/login` after absolute timeout passes
- `POST /logout` destroys session and redirects to `/login`
- `session_regenerate_id(true)` is called after successful login
- Developer bypass is wrapped in `APP_ENV=local` guard and not reachable otherwise
- `$_SESSION` is not accessed directly outside of `SessionHelper`

# Verification
- PHP syntax check all new/modified files
- Manually log in, wait past the idle timeout window (lower timeout temporarily for testing), verify redirect to login
- Manually test `POST /logout` — verify session cleared and redirect to `/login`
- Set `APP_ENV=production` in `.env`, verify developer bypass URL returns login page not app

# Handoff Note
`04-select-team-context-end-to-end.md` builds team context switching and role loading on top of the session established here, updating `SessionHelper::createSession()` to load roles and the team context flow.
