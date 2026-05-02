# Match Preparation (Minimal Slice)

# Purpose
Implement the minimal match preparation flow for the v0.1.0 vertical slice: attendance marking, formation selection, lineup placement, bench auto-assignment, and the prepare action. This is a simplified version — the full preparation flow with guests and complete validation is built in v0.4.0.

# Required Context
See `01-shared-context.md`. A planned match exists from `06-match-creation.md`. Schema includes `match_attendance`, `match_lineup`, `formation_positions`.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — preparation rules
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — preparation UI requirements

# Scope

## Routes (add to `routes/web.php`)

```php
$router->get('/matches/{id}/prepare',     [\App\Http\Controllers\MatchPreparationController::class, 'show']);
$router->post('/matches/{id}/attendance', [\App\Http\Controllers\MatchPreparationController::class, 'saveAttendance']);
$router->post('/matches/{id}/formation',  [\App\Http\Controllers\MatchPreparationController::class, 'saveFormation']);
$router->post('/matches/{id}/lineup',     [\App\Http\Controllers\MatchPreparationController::class, 'saveLineup']);
$router->post('/matches/{id}/prepare',    [\App\Http\Controllers\MatchPreparationController::class, 'prepare']);
```

## `GET /matches/{id}/prepare` — Preparation Screen

Show:
- List of all active squad players with attendance controls (present/absent/injured)
- Formation selector (dropdown of available formations)
- Lineup grid (10×11 — render as an HTML table or grid; positions shown per selected formation)
- Bench section (auto-populated after lineup save)

## `POST /matches/{id}/attendance`

- Body: `{ player_ids_present: [1,2,...], player_ids_absent: [...], player_ids_injured: [...] }`
- Validator: all player IDs must belong to the team/season
- `AttendanceService::save(int $matchId, array $present, array $absent, array $injured): void`
- Upsert attendance records in transaction
- CSRF stub (placeholder token)
- Coach/admin only (dev bypass in v0.1.0)

## `POST /matches/{id}/formation`

- Body: `{ formation_id: int }`
- Validator: formation exists
- Update `matches.formation_id`
- CSRF stub

## `POST /matches/{id}/lineup`

- Body: array of `{ position_id: int, player_id: int }` for starters
- Validator: no duplicate player; no duplicate position
- `LineupService::save(int $matchId, array $starters): void`
  - Transaction: delete existing lineup rows for this match, insert new starter rows (with coordinates from formation_positions), insert bench rows for present non-starters (null coordinates, is_starter=false)
- CSRF stub

## `POST /matches/{id}/prepare` — Prepare Action

`MatchPreparationService::prepare(int $matchId): void`

All checks in a single transaction:
1. Match state must be `planned`
2. At least 11 players marked present
3. Total present ≤ max players (per docs)
4. Formation selected
5. All formation starting positions filled
6. Every starter is present
7. No injured starter
8. No duplicate player or slot

If all pass: `UPDATE matches SET state = 'prepared' WHERE id = ?`

If any fail: rollback, return descriptive error, state remains `planned`.

Confirmation UI: show a confirmation step before submitting (simple JS confirm dialog or a two-step button).

## Views

**`app/Views/matches/prepare.php`**:
- Mobile-first
- Attendance section: player rows with present/absent/injured buttons
- Formation selector
- 10×11 grid rendered as a CSS grid or HTML table
- Bench list below grid
- Prepare button with confirmation behavior

# Out of Scope
- Guest player selection (v0.4.0)
- Full validation test coverage (v0.4.0)
- Drag-and-drop lineup (optional enhancement in v0.4.0)
- Full CSRF middleware (prompt 10)

# Architectural Rules
- `MatchPreparationService` owns the prepare transaction — controller only delegates
- Lineup bench auto-assignment is part of the same `LineupService::save()` transaction as starter insertion
- No SQL in controller

# Acceptance Criteria
- Coach can mark attendance for all players
- Coach can select a formation
- Coach can fill lineup positions
- Bench populated with non-starting present players after lineup save
- Preparing with < 11 present players: blocked with error message
- Preparing with no formation: blocked
- Preparing with unfilled positions: blocked
- Preparing with injured starter: blocked
- Valid prepare transitions state to `prepared`
- Match detail shows state=`prepared`

# Verification
- PHP syntax check all new files
- Manually walk through: mark 11+ players present → select formation → fill lineup → click Prepare → verify state=`prepared` in DB
- Test one blocking condition: mark only 9 players present → attempt prepare → verify error and state remains `planned`

# Handoff Note
`08-live-match.md` implements the live match screen and the start/goal/period actions for prepared matches.
