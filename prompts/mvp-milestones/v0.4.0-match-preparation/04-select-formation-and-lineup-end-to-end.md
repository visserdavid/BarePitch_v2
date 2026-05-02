# Select Formation and Lineup End to End

# Purpose
Implement formation selection, the 10-row × 11-column lineup grid, and bench auto-assignment. Coaches assign present players to starting positions; non-starting present players are automatically placed on the bench.

# Required Context
See `01-shared-context.md`. Formations and `formation_positions` exist in schema (from v0.1.0). Attendance and guest players are set up from prompts 02–03.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `formations`, `formation_positions`, `match_lineup` schema
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — lineup grid UI spec (10×11 grid)

# Scope

## Formation Selection

### `POST /matches/{id}/formation`
- Body: `{ "formation_id": int }`
- Validation: formation exists; formation is valid (per docs — check formation belongs to valid set for this context)
- Action: set `match.formation_id = :formation_id`
- After save: return the formation's position slots so the lineup grid can render the correct positions
- CSRF required; coach/admin only

## Lineup Grid: `GET /matches/{id}/prepare` (update)
After formation is selected, render the lineup grid:
- **Grid**: 10 rows × 11 columns representing the pitch
- Each cell corresponds to a `formation_position` slot from the selected formation
- Active position cells show a player name (if assigned) or an empty slot
- Available present players (squad + guests, not yet assigned as starters) shown in a sidebar/list
- Drag-and-drop OR click-to-assign interaction using vanilla JavaScript
- The grid state is visualized client-side but submitted to the server as a form POST

## Lineup Submission: `POST /matches/{id}/lineup`
- Body: array of `{ position_id: int, player_id: int }` for each filled starting position
- **Validator (LineupRequest)**:
  - Each `position_id` must belong to the currently selected formation
  - Each `player_id` must be a present (not absent, not injured) player for this match
  - No duplicate `player_id` across multiple positions
  - No duplicate `position_id` (one player per slot)
  - Total starters must not exceed maximum (per docs)
- **LineupService**:
  - Wrap in transaction
  - Delete existing `match_lineup` records for this match (full replace on each save)
  - Insert one row per starting position: `{ match_id, player_id, position_id, x_coord, y_coord, is_starter: true }`
  - For `x_coord`/`y_coord`: derive from `formation_positions` row or use the submitted grid coordinates — per schema definition in docs
  - **Bench auto-assignment**: after inserting starters, find all present players NOT in the starter list and insert bench rows: `{ match_id, player_id, position_id: null, x_coord: null, y_coord: null, is_starter: false }`
  - Return updated lineup state (starter list + bench list)
- CSRF required; coach/admin only

## Lineup data returned
After a successful lineup save, the preparation screen should reflect:
- Assigned starters in their grid positions
- Bench list showing non-starting present players (including guests marked present)
- Absent/injured players shown in a separate "unavailable" list (not on bench)

## JavaScript hints (optional, non-authoritative)
The frontend may use drag-and-drop or click-to-place for a better mobile experience. The server does not depend on or trust the client layout calculations — only the submitted `position_id` / `player_id` pairs matter.

# Out of Scope
- The prepare action validation (prompt 05)
- Substitution logic (v0.6.0)

# Architectural Rules
- `LineupService` owns the transaction that deletes and re-inserts the full lineup
- No business validation in the controller — only delegate to validator and service
- CSRF required on all POST routes
- Bench auto-assignment is done by the service as part of the same transaction as starter insertion

# Acceptance Criteria
- Formation can be selected and the match's `formation_id` persists
- Lineup positions can be filled and submitted
- Submitted lineup is stored in `match_lineup` with correct `is_starter` flags
- Non-starting present players are automatically inserted as bench records (no coordinates)
- Absent and injured players are NOT automatically placed on bench
- Duplicate player in multiple positions rejected by validator
- Duplicate position slot rejected by validator
- Bench is visible on the preparation screen after lineup save

# Verification
- PHP syntax check all new files
- Select a formation, assign 11 players to positions, submit — verify DB rows in `match_lineup`
- Verify bench rows created for unassigned present players
- Verify absent player does not appear on bench
- Submit lineup with same player in two positions — expect validation error
- Submit lineup with same position used twice — expect validation error

# Handoff Note
`05-prepare-match-end-to-end.md` implements the final prepare action, which re-validates all lineup rules atomically and transitions the match from `planned` to `prepared`.
