# Authorization Enforcement Review

# Purpose
Cross-cutting review and enforcement of authorization across all v0.3.0 routes. Verify every write route has a Policy check, every POST has CSRF, and no role can perform an action it should not.

# Required Context
See `01-shared-context.md`. All prior prompts in this bundle (02–05) must be complete.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — complete role permission matrix

# Scope

## Audit: Player management routes

For each route, verify:

| Route | Required role | CSRF | Policy class | Check present? |
|---|---|---|---|---|
| `POST /players` | admin, team_manager | ✓ | `PlayerPolicy::canManage()` | ? |
| `POST /players/{id}` (update) | admin, team_manager | ✓ | `PlayerPolicy::canManage()` | ? |
| `POST /players/{id}/deactivate` | admin, team_manager | ✓ | `PlayerPolicy::canManage()` | ? |
| `POST /players/{id}/season-context` | admin, team_manager | ✓ | `PlayerPolicy::canManage()` | ? |
| `POST /players/guests` | admin, team_manager | ✓ | `PlayerPolicy::canManage()` | ? |
| `GET /players` | all roles | — | `PlayerPolicy::canView()` | ? |
| `GET /players/{id}` | all roles | — | `PlayerPolicy::canView()` | ? |

For any "?" cells: open the controller method, add the missing check.

## Audit: Match management routes

| Route | Required role | CSRF | Policy class | Check present? |
|---|---|---|---|---|
| `POST /matches` | coach, admin | ✓ | `MatchPolicy::canCreate()` | ? |
| `POST /matches/{id}/edit` | coach, admin | ✓ | `MatchPolicy::canEdit()` | ? |
| `GET /matches` | all roles | — | `MatchPolicy::canView()` | ? |
| `GET /matches/{id}` | all roles | — | `MatchPolicy::canView()` | ? |

## Audit: Admin setup routes

| Route | Required role | CSRF | Policy | Check present? |
|---|---|---|---|---|
| All `POST /admin/*` | administrator | ✓ | `AdminPolicy::isAdministrator()` | ? |
| All `GET /admin/*` | administrator | — | `AdminPolicy::isAdministrator()` | ? |

## Fix pattern

For any missing policy check:
```php
public function store(): void {
    if (!PlayerPolicy::canManage()) {
        http_response_code(403);
        render('errors/403.php');
        return;
    }
    // ... rest of controller
}
```

For any missing CSRF: verify the CSRF middleware from v0.2.0 runs before all POST routes. If it does not, add `CsrfHelper::validate()` at the top of the store/update/delete action.

## Verify no SQL injection paths

In each Repository changed or added in this milestone, confirm:
- All query parameters use `?` or `:name` placeholders with `prepare()` + `execute()`
- No string interpolation of user input in SQL

## Output escaping verification

In all new views, confirm every variable output uses `htmlspecialchars()` or the `e()` helper. Fix any that do not.

## Produce a written audit result

Write a brief audit note here or in a comment in the affected controller file:
```
Authorization audit v0.3.0: [date]
- All player write routes: PlayerPolicy::canManage() present ✓
- All match write routes: MatchPolicy::canCreate/canEdit() present ✓
- All admin routes: AdminPolicy::isAdministrator() present ✓
- CSRF: middleware covers all POST routes ✓
- Prepared statements: all repositories confirmed ✓
- Output escaping: all new views confirmed ✓
```

# Out of Scope
- Authorization for preparation and live match routes (those are covered in their respective milestones)

# Architectural Rules
- Authorization is enforced server-side in Policy classes before any Service call mutates state.
- CSRF protection applies to every POST route through middleware or explicit validation.
- Repositories must use prepared statements only; do not interpolate user input into SQL.
- Views must escape all dynamic output.

# Acceptance Criteria
- Every write route in this milestone has a Policy check in the controller before the Service call
- Every POST route is covered by CSRF middleware
- No raw string interpolation in SQL in any repository in this milestone
- Every view variable output uses `htmlspecialchars()` or equivalent
- Audit note recorded

# Verification
- PHP syntax check all files in this milestone
- Manual test: trainer attempts `POST /players` → expect 403
- Manual test: team_manager attempts `POST /matches` → expect 403
- Manual test: remove `_csrf` from a form → expect 403
- Review all new Repository files for string interpolation

# Handoff Note
`07-testing-and-verification.md` adds the automated test suite for v0.3.0.
