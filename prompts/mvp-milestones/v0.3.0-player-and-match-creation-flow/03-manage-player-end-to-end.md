# Manage Player End to End

# Purpose
Implement the full player management flow: list, create, edit, detail, and deactivate. Player deactivation must be a soft delete that preserves all historical match data.

# Required Context
See `01-shared-context.md`. Admin setup from `02-admin-creates-club-season-team-end-to-end.md` provides the team/season context. Authorization: administrator and team_manager can write; coach and trainer are view-only.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — player table schema, position enum values, status fields
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — player management role permissions

# Scope

## Routes (update `routes/web.php`)

```php
$router->get('/players',                [\App\Http\Controllers\PlayerController::class, 'index']);
$router->get('/players/create',         [\App\Http\Controllers\PlayerController::class, 'create']);
$router->post('/players',               [\App\Http\Controllers\PlayerController::class, 'store']);
$router->get('/players/{id}',           [\App\Http\Controllers\PlayerController::class, 'show']);
$router->get('/players/{id}/edit',      [\App\Http\Controllers\PlayerController::class, 'edit']);
$router->post('/players/{id}',          [\App\Http\Controllers\PlayerController::class, 'update']);  // POST with _method override if needed
$router->post('/players/{id}/deactivate', [\App\Http\Controllers\PlayerController::class, 'deactivate']);
```

## Authorization

`PlayerPolicy`:
```php
class PlayerPolicy {
    public static function canManage(): bool {
        return CurrentUser::hasAnyRole(['administrator', 'team_manager']);
    }
    public static function canView(): bool {
        return CurrentUser::hasAnyRole(['administrator', 'team_manager', 'coach', 'trainer']);
    }
}
```

Write routes (`store`, `update`, `deactivate`): check `PlayerPolicy::canManage()` — return 403 if false.
Read routes: check `PlayerPolicy::canView()`.

## `PlayerController`

### `index()` — `GET /players`
- Load active players for active team/season (with `player_season_context` join)
- Order by jersey_number
- Show: name, jersey, position, status

### `create()` / `store()` — create player
- Form fields per schema: name, date_of_birth (optional), position (required, valid enum)
- After creating player: create `player_season_context` for active team/season with the jersey_number

### `show()` — player detail
- Show: name, position, jersey_number, date_of_birth, status (active/inactive)
- If inactive: show a banner indicating the player is deactivated

### `edit()` / `update()` — edit player
- Can only edit active players (inactive players show read-only view)
- Validate same fields as creation; jersey_number changes update `player_season_context`

### `deactivate()` — soft deactivate
- Check `canManage()`
- `PlayerService::deactivate(int $playerId, int $teamId, int $seasonId): void`
  - Validate: player belongs to active team
  - Set `players.active = 0` (or equivalent soft-delete flag from schema)
  - Do NOT delete the player row
  - Do NOT delete `player_season_context` row (keeps historical reference)
  - Do NOT delete any `match_events` or `match_lineup` rows referencing this player
- Deactivated player must NOT appear in:
  - Squad selection lists (attendance, lineup assignment)
  - The default `GET /players` list
- Deactivated player MUST still appear in:
  - Historical match summaries referencing them
  - Match event timeline entries

## `PlayerCreateRequest` / `PlayerUpdateRequest`

Validate:
- `name`: required, max length per docs
- `position`: required, valid enum value from domain model (e.g., GK, DEF, MID, FWD — use exact values from docs)
- `jersey_number`: required, positive integer, unique within active team/season
- `date_of_birth`: optional, valid date, not in the future, reasonable age range per docs if specified

If jersey_number conflicts: return error "Jersey number already in use."

## `PlayerService`

```php
public function create(array $data, int $teamId, int $seasonId): int {
    $db = Database::connection();
    $db->beginTransaction();
    try {
        // Insert player
        $playerId = $this->playerRepository->insert([
            'name'          => $data['name'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'active'        => 1,
        ]);
        // Insert player_season_context
        $this->playerRepository->insertSeasonContext([
            'player_id'    => $playerId,
            'team_id'      => $teamId,
            'season_id'    => $seasonId,
            'jersey_number'=> $data['jersey_number'],
            'position'     => $data['position'],
        ]);
        $db->commit();
        return $playerId;
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
```

## Views

**`app/Views/players/index.php`**: mobile-first list; active players only; create button visible to team_manager/admin only.

**`app/Views/players/create.php`** and **`edit.php`**: mobile-first forms with validation errors near fields; edit/deactivate buttons visible to team_manager/admin only.

**`app/Views/players/show.php`**: player detail; deactivate button visible to team_manager/admin only with confirmation.

# Out of Scope
- External guest players (prompt 04)
- Player statistics (v0.9.0)
- Player photos, parent/contact data

# Architectural Rules
- Deactivation is soft delete only — never hard delete player rows
- `player_season_context` rows persist even after deactivation
- Historical match data (events, lineup records) must remain intact after deactivation

# Acceptance Criteria
- Team manager can create a player with a unique jersey number
- Player appears in team player list with jersey number and position
- Duplicate jersey number rejected with field-level error
- Invalid position value rejected by validator
- Team manager can deactivate a player
- Deactivated player does not appear in active player list
- Match event timeline entries still reference the deactivated player correctly
- Coach attempting POST /players returns 403
- Trainer attempting POST /players returns 403

# Verification
- PHP syntax check all new files
- Create a player → verify appears in list
- Deactivate the player → verify disappears from list
- Query `SELECT * FROM players WHERE id = ?` → verify row still exists with `active=0`
- Query `SELECT * FROM match_events WHERE player_id = ?` (if any events exist) → verify still present
- Coach attempts POST /players → verify 403

# Handoff Note
`04-activate-player-and-add-guest-end-to-end.md` handles season context management details and external guest player creation.
