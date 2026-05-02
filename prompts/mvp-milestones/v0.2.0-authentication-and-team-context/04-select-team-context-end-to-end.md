# Select Team Context End to End

# Purpose
Implement active team selection, role loading from `user_team_role`, the team selection UI for multi-team users, and safe no-role/no-access handling.

# Required Context
See `01-shared-context.md`. Authenticated session is established by `03-maintain-session-and-logout-end-to-end.md`. Roles: coach, trainer, administrator, team_manager.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `user_team_role` schema, role enum values
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — role definitions and access rules
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — team selection routes

# Scope

## Role Loading (update `SessionHelper::createSession()`)
After successful login callback, load the authenticated user's team roles:
```php
// In SessionHelper::createSession() or AuthService
$roles = $authRepository->getRolesForUser($user['id']);
// $roles = [['team_id' => 1, 'role' => 'coach'], ['team_id' => 2, 'role' => 'trainer'], ...]
$_SESSION['user_roles'] = $roles;
```
Store the full role array in session. This allows checking role for any team without additional DB queries on every request.

## `app/Http/Helpers/TeamContext.php`

### `TeamContext::getActiveTeam(): ?array`
- Returns the currently active team from `$_SESSION['active_team']`
- Returns `null` if no active team is selected

### `TeamContext::setActiveTeam(int $teamId): void`
- Validates that the authenticated user has a role for `$teamId` (check `$_SESSION['user_roles']`)
- If valid: set `$_SESSION['active_team'] = ['id' => $teamId, ...]` (store team id and name at minimum)
- If invalid: throw an authorization exception or return false — server-side check, not just UI

### `TeamContext::getActiveRole(): ?string`
- Returns the user's role for the currently active team
- Returns `null` if no active team or no role

### `TeamContext::getUserTeams(): array`
- Returns distinct teams the user has roles for, from `$_SESSION['user_roles']`

## Auto-selection for single-team users
In `TeamContext::autoSelect()` (called after login in `AuthController::handleCallback()`):
- If user has exactly one team role: call `TeamContext::setActiveTeam($teamId)` automatically
- If user has zero team roles: do nothing (no-role path handles this)
- If user has multiple team roles: do nothing (user must explicitly select)

## Team selection routes (for multi-team users)
- `GET /teams/select` — show list of teams the user has roles for; each shows team name and user's role
- `POST /teams/select` — set active team:
  - Validate: `team_id` in POST body
  - Validate server-side: user has a role for this team (check `$_SESSION['user_roles']`)
  - Call `TeamContext::setActiveTeam($teamId)`
  - Redirect to `/` (or originally requested URL if stored in session)
  - CSRF required

## Updated `CurrentUser` helper (`app/Http/Helpers/CurrentUser.php`)
Update to read from session (not developer bypass, except when `APP_ENV=local`):
```php
public static function get(): ?array {
    if (getenv('APP_ENV') === 'local' && /* dev bypass condition */) {
        // local dev bypass
    }
    $userId = SessionHelper::getCurrentUserId();
    if (!$userId) return null;
    // Load user from DB or session cache
    return $user;
}

public static function hasRole(string $role): bool {
    $activeRole = TeamContext::getActiveRole();
    return $activeRole === $role;
}

public static function hasAnyRole(array $roles): bool {
    $activeRole = TeamContext::getActiveRole();
    return in_array($activeRole, $roles, true);
}
```

## No-role handling
- If authenticated user has zero entries in `user_team_role`: render a safe "no access" view — e.g., `app/Views/auth/no-access.php`
- The no-access view shows: "Your account does not have access to any team. Contact your administrator."
- The no-access view must NOT expose team names, role structures, or any other app data
- All protected routes must check for no-role state before processing

## No-team-context handling
- If authenticated user has roles but no active team selected: redirect to `GET /teams/select`
- This redirect logic lives in the route protection middleware (prompt 05) but TeamContext must expose `TeamContext::hasActiveTeam(): bool`

# Out of Scope
- Admin screens for managing role assignments (v0.3.0)
- Role-based write authorization for specific resources (those live in their feature prompts)
- Route protection middleware (prompt 05)

# Architectural Rules
- `TeamContext` is a stateless helper class reading from `$_SESSION`
- Role checking for authorization must always use `$_SESSION['user_roles']` (loaded at login) — do not re-query the DB on every request for role checks
- `setActiveTeam()` must validate server-side that the user has a role for the selected team

# Acceptance Criteria
- After login, user with one team automatically has it selected as active
- User with multiple teams is directed to `GET /teams/select` and must choose
- `POST /teams/select` with a team the user does not have a role for returns an error (server-side rejection)
- User with no team roles sees the no-access view and cannot reach any protected route
- `TeamContext::getActiveRole()` returns the correct role string for the active team
- Role values loaded from `user_team_role` are: coach, trainer, administrator, team_manager (per schema)

# Verification
- PHP syntax check all new/modified files
- Seed a second user with no roles; log in as that user; verify no-access page shown
- Seed a user with two teams; log in; verify team selection prompt shown; select a team; verify app loads
- Attempt `POST /teams/select` with a `team_id` the user has no role for; verify server-side rejection

# Handoff Note
`05-access-protected-route-end-to-end.md` implements the request-level middleware that enforces authentication and team context for every protected route, using `TeamContext::hasActiveTeam()` and `SessionHelper::getCurrentUserId()`.
