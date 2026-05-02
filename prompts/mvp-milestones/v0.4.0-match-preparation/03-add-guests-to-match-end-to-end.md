# Add Guests to Match End to End

# Purpose
Implement internal and external guest player selection for a specific match. Guests appear in the attendance list once added, and can be removed while the match is still in planned state.

# Required Context
See `01-shared-context.md`. External guest player entity exists (created in v0.3.0). Attendance infrastructure from `02-mark-attendance-end-to-end.md`.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_guests` table or equivalent; internal/external guest distinction
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — guest player rules (internal: same club, different team; external: persistent entity)

# Scope

## Internal Guest Player Selection

**Rule**: Internal guests must come from a different team within the same club as the active team. They cannot be from a different club.

### `GET /matches/{id}/guests/internal/search`
- Query string: `q` (partial name search)
- Returns: JSON array of players from other teams in the same club (not the active team)
- Authorization: coach/admin only
- Response: `[{ "id": 1, "name": "Player Name", "team": "Team B", "jersey": 7, "position": "MF" }]`

### `POST /matches/{id}/guests/internal`
- Body: `{ "player_id": int }`
- Validation:
  - Player exists
  - Player belongs to a team in the same club as the active team
  - Player does NOT belong to the active team (would be a regular squad member)
  - Player not already added as a guest to this match
  - Match is in `planned` state
- Action: insert into `match_guests` (or equivalent) with `type=internal`, attach player to this match
- Player then appears in the attendance section
- CSRF required; coach/admin only

## External Guest Player Selection

**Rule**: External guests are persisted independently (created in v0.3.0) and reused across matches.

### `GET /matches/{id}/guests/external/search`
- Query string: `q` (partial name search)
- Returns: JSON array of external guest players
- Authorization: coach/admin only

### `POST /matches/{id}/guests/external`
- Body: `{ "player_id": int }` (existing external guest) OR creation fields if creating inline
- Validation:
  - Player exists and has `is_external_guest=true` flag
  - Player not already added as a guest to this match
  - Match is in `planned` state
- Action: insert into `match_guests` with `type=external`
- CSRF required; coach/admin only

### Inline external guest creation
If the coach does not find an existing external guest, they can create one inline:
- `POST /matches/{id}/guests/external/create`
- Body: `{ "name": string, "position": string }` (minimum fields per domain model)
- Creates new external guest player record (delegates to `PlayerService::createExternalGuest()` from v0.3.0)
- Then adds the new guest to the match
- CSRF required; coach/admin only

## Remove Guest

### `DELETE /matches/{id}/guests/{player_id}` (or `POST` with `_method=DELETE`)
- Validation: match is in `planned` state (cannot remove guests after preparation)
- Removes guest record from `match_guests`
- CSRF required; coach/admin only

## Attendance integration
After a guest is added, they appear in the attendance list in `GET /matches/{id}/prepare` alongside regular squad players with the same present/absent/injured controls.

# Out of Scope
- Guests participating after match preparation (lineup placement is handled in prompt 04)
- Guest statistics (v0.9.0)

# Architectural Rules
- Server validates the club membership constraint for internal guests — not just UI filtering
- `GuestService::addInternalGuest(matchId, playerId, activeTeamId)` must query: does player's team share the same club_id as the active team?
- CSRF on all POST/DELETE routes

# Acceptance Criteria
- Internal guest can be searched and added only from the same club
- Attempting to add a player from a different club as an internal guest returns a server-side error
- External guest can be added from existing records
- External guest can be created inline and immediately added
- Added guest appears in the attendance list
- Guest can be removed while match is in planned state
- Attempting to remove a guest from a non-planned match returns an error
- Trainer/team-manager write attempts return 403

# Verification
- PHP syntax check all new files
- Seed a player from a different club; attempt to add as internal guest — expect rejection
- Add an internal guest from the same club — verify they appear in attendance
- Add an external guest — verify they appear in attendance
- Remove the guest — verify they disappear from attendance list

# Handoff Note
`04-select-formation-and-lineup-end-to-end.md` implements the formation selector and the 10x11 lineup grid where both squad players and guests can be assigned to positions.
