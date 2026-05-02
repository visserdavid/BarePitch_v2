# Create and Edit Match End to End

# Purpose
Implement the complete planned match management flow: list, create, edit, and detail. Harden the match creation from v0.1.0 with proper authorization, validation, and state guards.

# Required Context
See `01-shared-context.md`. v0.1.0 provides a minimal match creation. This prompt replaces and hardens it. Real auth from v0.2.0 is in place.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — match schema, match_type enum, state enum
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — match management permissions
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — match routes

# Scope

## Routes (update `routes/web.php` — replace v0.1.0 stubs)

```php
$router->get('/matches',            [\App\Http\Controllers\MatchController::class, 'index']);
$router->get('/matches/create',     [\App\Http\Controllers\MatchController::class, 'create']);
$router->post('/matches',           [\App\Http\Controllers\MatchController::class, 'store']);
$router->get('/matches/{id}',       [\App\Http\Controllers\MatchController::class, 'show']);
$router->get('/matches/{id}/edit',  [\App\Http\Controllers\MatchController::class, 'edit']);
$router->post('/matches/{id}/edit', [\App\Http\Controllers\MatchController::class, 'update']);
```

## Authorization

`MatchPolicy`:
```php
class MatchPolicy {
    public static function canCreate(): bool {
        return CurrentUser::hasAnyRole(['coach', 'administrator']);
    }
    public static function canEdit(): bool {
        return CurrentUser::hasAnyRole(['coach', 'administrator']);
    }
    public static function canView(): bool {
        return CurrentUser::hasAnyRole(['coach', 'trainer', 'administrator', 'team_manager']);
    }
}
```

Trainer and team_manager: can view, cannot create or edit.

## `MatchController`

### `index()` — `GET /matches`
- Load all matches for the active team, ordered by `scheduled_at` descending
- Show: state badge, date, opponent, home/away, match_type, phase name
- Authorization: `canView()`

### `create()` / `store()` — create match
- Authorization: `canCreate()` — 403 for trainer/team_manager
- Phase selector: only phases belonging to the active team's season
- Fields: `phase_id`, `opponent`, `home_away`, `match_type`, `half_duration`, `extra_time_duration` (optional), `scheduled_at` (optional)
- Server-side validation in `MatchService::create()`: phase must belong to active team's season (not just a client-side filter)

### `show()` — match detail
- Load match scoped to active team (404 if match belongs to different team)
- Show all match fields
- Conditional links: prepare link if `planned`; live link if `active`; summary link if `finished`
- Authorization: `canView()`

### `edit()` / `update()` — edit planned match
- Authorization: `canEdit()`
- State guard: match must be in `planned` state — if `prepared`, `active`, or `finished`, return safe error
- Editable fields: opponent, home_away, match_type, half_duration, extra_time_duration, scheduled_at, phase_id
- Server-side validation same as creation
- Non-editable when not `planned`: show read-only detail instead

## `MatchCreateRequest` / `MatchUpdateRequest`

Validate:
- `phase_id`: required, integer, must belong to active team's season (DB lookup in Service, not just validator)
- `opponent`: required, string, max length per docs
- `home_away`: required, enum `home` or `away`
- `match_type`: required, valid enum value from docs
- `half_duration`: required, positive integer (minutes)
- `extra_time_duration`: optional, positive integer or null
- `scheduled_at`: optional, valid datetime

Validation errors displayed near the relevant form field.

## `MatchService` (harden from v0.1.0)

```php
public function create(array $data, int $teamId): int {
    // Server-side phase validation
    if (!$this->phaseRepository->belongsToTeamSeason($data['phase_id'], $teamId)) {
        throw new \InvalidArgumentException('Phase does not belong to this team\'s season.');
    }
    // ... insert match
}

public function update(int $matchId, array $data, int $teamId): void {
    $match = $this->matchRepository->findById($matchId);
    if (!$match || $match['team_id'] !== $teamId) {
        throw new \InvalidArgumentException('Match not found.');
    }
    if ($match['state'] !== 'planned') {
        throw new \App\Domain\Exceptions\InvalidStateException('Only planned matches can be edited.');
    }
    // Server-side phase validation
    if (!$this->phaseRepository->belongsToTeamSeason($data['phase_id'], $teamId)) {
        throw new \InvalidArgumentException('Phase does not belong to this team\'s season.');
    }
    // ... update match
}
```

## Views

**`app/Views/matches/index.php`**: mobile-first match list; state badges; create button shown to coach/admin only.

**`app/Views/matches/create.php`** and **`edit.php`**: mobile-first forms with validation errors near fields; phase dropdown populated from active team's season phases only.

**`app/Views/matches/show.php`**: match detail with role-conditional links (prepare/live/summary based on state and role).

# Out of Scope
- Match deletion
- Match cloning
- Bulk match import

# Architectural Rules
- Phase-team validation in Service (not just validator)
- State guard for edit in Service
- CSRF on all POST routes (middleware handles this)
- Match detail queries scoped to active team (no cross-team data leakage)

# Acceptance Criteria
- Coach can create a planned match
- Coach can edit a planned match
- Trainer can view match list and detail, gets 403 on create/edit
- Team manager can view match list and detail, gets 403 on create/edit
- Phase from a different team's season rejected server-side
- Attempting to edit a `prepared` or `active` match returns a safe error
- Validation errors shown near fields

# Verification
- PHP syntax check all new files
- Create a match as coach → edit it → verify updates persist
- Attempt to edit a `prepared` match → verify safe error
- Log in as trainer → attempt `GET /matches/create` → verify 403
- Submit match with phase from wrong season → verify rejection

# Handoff Note
`06-authorization-enforcement.md` performs a cross-cutting authorization review for all v0.3.0 routes.
