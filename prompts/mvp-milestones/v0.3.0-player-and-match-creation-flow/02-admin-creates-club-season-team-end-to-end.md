# Admin Creates Club, Season, and Team End to End

# Purpose
Implement the administrator setup screens needed for the MVP: club, season, phase, team, user, and role assignment. These screens allow an administrator to configure the minimum required entities before a coach can work.

# Required Context
See `01-shared-context.md`. Real authentication from v0.2.0 is in place. Role checking uses `CurrentUser::hasRole('administrator')`.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — entity schemas and constraints
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — admin-only routes and recent-auth requirements

# Scope

## Routes (add to `routes/web.php`)

```php
// Admin — all require administrator role
$router->get('/admin/clubs',                    [\App\Http\Controllers\Admin\ClubController::class, 'index']);
$router->get('/admin/clubs/create',             [\App\Http\Controllers\Admin\ClubController::class, 'create']);
$router->post('/admin/clubs',                   [\App\Http\Controllers\Admin\ClubController::class, 'store']);

$router->get('/admin/seasons',                  [\App\Http\Controllers\Admin\SeasonController::class, 'index']);
$router->post('/admin/seasons',                 [\App\Http\Controllers\Admin\SeasonController::class, 'store']);

$router->get('/admin/phases',                   [\App\Http\Controllers\Admin\PhaseController::class, 'index']);
$router->post('/admin/phases',                  [\App\Http\Controllers\Admin\PhaseController::class, 'store']);

$router->get('/admin/teams',                    [\App\Http\Controllers\Admin\TeamController::class, 'index']);
$router->post('/admin/teams',                   [\App\Http\Controllers\Admin\TeamController::class, 'store']);

$router->get('/admin/users',                    [\App\Http\Controllers\Admin\UserController::class, 'index']);
$router->post('/admin/users',                   [\App\Http\Controllers\Admin\UserController::class, 'store']);

$router->get('/admin/users/{id}/roles',         [\App\Http\Controllers\Admin\RoleController::class, 'index']);
$router->post('/admin/users/{id}/roles',        [\App\Http\Controllers\Admin\RoleController::class, 'assign']);
$router->post('/admin/users/{id}/roles/remove', [\App\Http\Controllers\Admin\RoleController::class, 'remove']);
```

## Authorization

All admin routes: `AdminPolicy::isAdministrator()` — check in each controller before any action.

Recent-authentication requirement: check `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — if role assignment requires recent auth, implement a `recentAuthRequired()` check that verifies the session was authenticated within the last N minutes (use value from docs). If recent auth is not satisfied, redirect to a re-authentication page.

## Entity creation pattern

Each entity follows the same controller pattern:
1. Check `AdminPolicy::isAdministrator()` — 403 if not
2. Validate via `{Entity}Request`
3. Call `{Entity}Service::create(array $data)`
4. Redirect with success message

### Clubs
- Fields: `name` (required, max 100 chars), `country` (optional), other fields per schema
- `ClubRepository::insert()`, `ClubService::create()`

### Seasons
- Fields: `name` (required), `start_date` (required, date), `end_date` (required, date)
- Validation: `end_date` > `start_date`

### Phases
- Fields: `name` (required), `season_id` (required, valid season), `type` (required, valid phase type enum)
- Phase types: use exact values from domain model doc

### Teams
- Fields: `name` (required), `club_id` (required, valid club)
- After creating team: create `team_season` association if season/phase provided

### Users
- Fields: `email` (required, valid email format, unique), `name` (required)
- No password — authentication is via magic link

### Role Assignment (`RoleController`)
- `GET /admin/users/{id}/roles` — show current roles and assignment form
- `POST /admin/users/{id}/roles` — body: `{ team_id, role }` — insert into `user_team_roles`
  - Validate: `team_id` exists; `role` is valid enum (coach/trainer/administrator/team_manager)
  - Validate: no duplicate role for same user/team
  - Recent-auth check if required by docs
- `POST /admin/users/{id}/roles/remove` — body: `{ role_id }` — remove role assignment
  - Recent-auth check if required by docs

## Validators

Create a `Request` class for each entity. Each validator checks:
- Required fields present
- String length limits (per domain model doc — do NOT invent limits; use exactly what is documented)
- Enum values are valid members of the documented set

## Views

Mobile-first admin list + form pages for each entity. Pattern:
- `app/Views/admin/{entity}/index.php` — list with "Create new" link
- `app/Views/admin/{entity}/create.php` — creation form

No edit/delete UI in this milestone unless explicitly needed for MVP flows (check docs).

# Out of Scope
- Bulk import, advanced search
- Audit logging for admin setup changes (only for finished-match corrections in v0.8.0)
- Edit/delete for all entities (unless required by MVP — check docs)

# Architectural Rules
- All admin controllers call `AdminPolicy::isAdministrator()` before any action
- All writes go through Service classes
- CSRF on all POST routes (middleware handles this)
- Recent-auth check per docs requirements

# Acceptance Criteria
- Administrator can create a club, season, phase, team, user
- Administrator can assign a role to a user for a specific team
- Non-administrator user attempting any admin route receives 403
- Duplicate role assignment rejected
- Invalid enum values (role type, phase type) rejected by validator
- Text fields over documented length limits are rejected

# Verification
- PHP syntax check all new files
- Log in as administrator; create club → season → phase → team → user → assign role
- Log in as coach; attempt `GET /admin/clubs` → verify 403
- Attempt to assign an invalid role value → verify rejection

# Handoff Note
`03-manage-player-end-to-end.md` builds the player management flow using the team and season entities created through these admin screens.
