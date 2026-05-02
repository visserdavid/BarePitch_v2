# Activate Player and Add Guest End to End

# Purpose
Implement player season context management (ensuring players are properly linked to team/season contexts) and external guest player creation and reuse.

# Required Context
See `01-shared-context.md`. Player management from `03-manage-player-end-to-end.md` creates `player_season_context` rows on player creation. This prompt handles edge cases and the external guest entity.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `player_season_context` schema, external guest flags
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — guest player rules

# Scope

## Player Season Context

### Context creation on player add
Verify that `PlayerService::create()` (from prompt 03) creates a `player_season_context` row for the active team/season. If missing, add it.

### Adding existing player to a new season
When a new season is created and a team needs to carry over players:
- `POST /players/{id}/season-context` — create a new `player_season_context` for the player in the new season
- Body: `{ season_id, jersey_number, position }` — jersey_number may change between seasons
- Validator: player must belong to the active team; season must be a valid season
- Authorization: administrator or team_manager

### Season context display
In `GET /players` and `GET /players/{id}`, show the jersey_number and position from `player_season_context` for the active season. If no context exists for the current season, show player with a "no season context" indicator (not an error).

## External Guest Players

External guest players are persistent players who are not permanently attached to any team. They can be invited to a match and reused in future matches.

### External guest flag
The schema must have a way to mark a player as an external guest (check `docs/-01` for the exact field — likely `is_external_guest = 1` or a `player_type` enum). Use exactly what is documented.

### Routes

```php
$router->get('/players/guests',        [\App\Http\Controllers\GuestPlayerController::class, 'index']);
$router->get('/players/guests/create', [\App\Http\Controllers\GuestPlayerController::class, 'create']);
$router->post('/players/guests',       [\App\Http\Controllers\GuestPlayerController::class, 'store']);
```

### `GET /players/guests` — External guest list
- Show all external guest players (filtered by club or global — check docs)
- These are separate from the regular squad list

### `POST /players/guests` — Create external guest

```php
public function store(): void {
    if (!PlayerPolicy::canManage()) {
        http_response_code(403); render('errors/403.php'); return;
    }
    // validate
    $this->playerService->createExternalGuest($data);
    redirect('/players/guests?created=1');
}
```

`PlayerService::createExternalGuest(array $data): int`:
- Insert player with `is_external_guest = 1` (or equivalent)
- Do NOT create a `player_season_context` (external guests are not squad members)
- Return new player ID

### External guest must NOT appear in regular squad lists
- `PlayerRepository::getActiveForTeamSeason()` must filter out external guests
- Add `AND p.is_external_guest = 0` (or equivalent) to the query
- External guests are only retrieved by `GuestPlayerRepository::getExternalGuests()`

### External guest reuse
The `GuestPlayerRepository::getExternalGuests()` method returns all external guest players for selection during match preparation (prompt 03 in v0.4.0 bundle). They persist across seasons and matches.

## Validation for external guests

`GuestPlayerCreateRequest`:
- `name`: required, max length per docs
- `position`: required, valid position enum
- No jersey_number (external guests don't have a permanent jersey)

# Out of Scope
- Internal guest selection (v0.4.0, prompt 03 of that bundle)
- Guest player appearance in match preparation UI (v0.4.0)

# Architectural Rules
- External guests must not appear in regular squad selection queries
- External guest creation does not create `player_season_context`
- Authorization: administrator or team_manager to create external guests

# Acceptance Criteria
- External guest can be created with name and position
- External guest does NOT appear in `GET /players` regular squad list
- External guest appears in `GET /players/guests` list
- External guest can be retrieved later for reuse (persists across sessions)
- Invalid position for external guest rejected by validator

# Verification
- PHP syntax check all new files
- Create an external guest → verify does NOT appear in `/players`
- Verify appears in `/players/guests`
- Query `SELECT * FROM players WHERE is_external_guest = 1` → verify row exists

# Handoff Note
`05-create-and-edit-match-end-to-end.md` implements the full match creation, list, edit, and detail flows.
