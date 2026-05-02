# Match Creation Flow

# Purpose
Implement the match list and the minimal match creation flow. Coaches can create planned matches for the active team, specifying opponent, phase, match type, and period durations.

# Required Context
See `01-shared-context.md`. Team context and player list from `05-team-context.md` must work. The `matches` table and `phases` table exist in the schema.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — match table columns, match_type enum, state enum
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — match routes
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — who can create matches

# Scope

## Routes (add to `routes/web.php`)

```php
$router->get('/matches',            [\App\Http\Controllers\MatchController::class, 'index']);
$router->get('/matches/create',     [\App\Http\Controllers\MatchController::class, 'create']);
$router->post('/matches',           [\App\Http\Controllers\MatchController::class, 'store']);
$router->get('/matches/{id}',       [\App\Http\Controllers\MatchController::class, 'show']);
```

## `MatchController`

### `index()` — `GET /matches`
- Load all matches for the active team
- Pass to view: matches array, team name
- Show: state badge, date, opponent, match_type, phase name

### `create()` — `GET /matches/create`
- Load phases available for the active team's season (query `phases` joined to `team_season`)
- Render match creation form

### `store()` — `POST /matches`
1. Validate via `MatchCreateRequest`
2. Check policy: `MatchPolicy::canCreate()` — coach or administrator only (dev bypass: always true in v0.1.0)
3. Call `MatchService::create()`
4. Redirect to `GET /matches/{id}` on success

### `show()` — `GET /matches/{id}`
- Load match by ID, verify it belongs to the active team (scoped query)
- Show: state, opponent, phase, match_type, home/away, half_duration, extra_time_duration
- Show link to `GET /matches/{id}/prepare` if state is `planned`
- Show link to `GET /matches/{id}/live` if state is `active`
- Show link to `GET /matches/{id}/summary` if state is `finished`

## `MatchCreateRequest` (`app/Http/Requests/MatchCreateRequest.php`)

Validate:
- `phase_id`: required, integer; verify the phase belongs to the active team's season (DB lookup)
- `opponent`: required, string, max 100 chars
- `home_away`: required, enum: `home` or `away` (verify against docs)
- `match_type`: required, valid enum value from docs
- `half_duration`: required, positive integer (minutes)
- `extra_time_duration`: optional, positive integer (minutes) or null
- `scheduled_at`: optional, datetime

If validation fails: return to form with errors displayed near the relevant fields.

## `MatchPolicy` (`app/Policies/MatchPolicy.php`)

```php
class MatchPolicy {
    public static function canCreate(): bool {
        // v0.1.0: dev bypass always true; v0.2.0 will check real role
        return CurrentUser::hasRole('coach') || CurrentUser::hasRole('administrator');
    }
}
```

## `MatchService` (`app/Services/MatchService.php`)

```php
public function create(array $data, int $teamId): int {
    // Verify phase belongs to team's season (server-side, not just form validation)
    $phase = $this->phaseRepository->findById($data['phase_id']);
    if (!$this->phaseRepository->belongsToTeamSeason($data['phase_id'], $teamId)) {
        throw new \InvalidArgumentException('Invalid phase for this team.');
    }

    return $this->matchRepository->insert([
        'team_id'              => $teamId,
        'phase_id'             => $data['phase_id'],
        'opponent'             => $data['opponent'],
        'home_away'            => $data['home_away'],
        'match_type'           => $data['match_type'],
        'half_duration'        => (int)$data['half_duration'],
        'extra_time_duration'  => isset($data['extra_time_duration']) ? (int)$data['extra_time_duration'] : null,
        'scheduled_at'         => $data['scheduled_at'] ?? null,
        'state'                => 'planned',
        'created_at'           => date('Y-m-d H:i:s'),
    ]);
}
```

## `MatchRepository` (`app/Repositories/MatchRepository.php`)

- `findById(int $id): ?array` — scoped to active team
- `findAllForTeam(int $teamId): array`
- `insert(array $data): int` — returns new match ID

## Views

**`app/Views/matches/index.php`**: list of matches per team; show state, date, opponent.

**`app/Views/matches/create.php`**: creation form with all fields; CSRF token (stub for now — a hidden `_csrf` field with a placeholder value; full CSRF validation added in prompt 10); validation errors displayed near fields.

**`app/Views/matches/show.php`**: match detail; conditional navigation links based on state.

# Out of Scope
- Match editing (v0.3.0)
- Match deletion
- Authorization beyond the dev bypass (v0.2.0)
- Trainer/team-manager view-only enforcement (v0.3.0)

# Architectural Rules
- Phase-team validation happens in the Service, not just the validator
- Controller calls Policy before Service
- No direct SQL in controller
- CSRF token placeholder in form (full validation in prompt 10)

# Acceptance Criteria
- `GET /matches` shows the match list (empty initially, then populated after creation)
- `GET /matches/create` shows the creation form with phase selector
- Valid form POST creates a `planned` match and redirects to detail
- Match detail shows state=`planned`
- Missing required fields return validation errors near fields
- Phase from a different team/season is rejected server-side

# Verification
- PHP syntax check all new files
- Manually create a match through the UI
- Verify `SELECT * FROM matches;` returns one row with `state='planned'`
- Attempt to submit form with missing `opponent` — verify error shown near field

# Handoff Note
`07-match-preparation.md` implements the preparation flow starting from the planned match created here.
