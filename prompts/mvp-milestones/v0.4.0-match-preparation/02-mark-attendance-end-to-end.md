# Mark Attendance End to End

# Purpose
Implement the match preparation screen and the attendance marking flow. Coaches can mark each squad player as present, absent, or injured for a specific match.

# Required Context
See `01-shared-context.md`. A planned match exists (from v0.3.0). Active squad players are loaded from `player_season_context` for the active team/season.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_attendance` or attendance columns in `match_lineup`; player status enum values
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — preparation screen UI requirements

# Scope

## Preparation Screen: `GET /matches/{id}/prepare`

Render the preparation screen. The screen shows:
1. **Attendance section** — list of all active squad players for the active team/season; for each player: name, jersey number, position, current attendance status (present/absent/injured)
2. **Guest players section** — any guests already added to this match
3. **Formation selector** — dropdown of available formations (populated in prompt 04)
4. **Lineup grid placeholder** — 10×11 grid rendered but empty until formation selected
5. **Bench placeholder** — empty bench section
6. **Prepare button** — disabled until minimum requirements met (client-side progressive disclosure; server validates regardless)

Authorization: authenticated; coach or administrator only. Trainer and team_manager: redirect to match detail (`GET /matches/{id}`).

If match state is not `planned`: redirect to match detail with an informational message.

## Attendance storage

Check `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` for how attendance is stored:
- If a dedicated `match_attendance` table exists: use it
- If attendance is stored in `match_lineup` columns: use those
- Do not invent a new storage structure; use exactly what the schema defines

Attendance status enum values: `present`, `absent`, `injured` — use exactly these values (verify against docs).

## `POST /matches/{id}/attendance` — Save attendance

Request body: array of `{ player_id: int, status: 'present'|'absent'|'injured' }` entries.

**AttendanceRequest validator**:
- Each `player_id` must belong to the active team's squad for this match (or be a guest added to this match)
- Each `status` must be a valid enum value
- No extra players beyond the current squad/guests

**AttendanceService**:
- Validate each player belongs to this match's team context
- Upsert attendance record for each player
- No partial writes — wrap in transaction if multiple rows

**AttendanceController**: calls `AttendanceRequest` validator, then `AttendancePolicy::canMark()` (coach/admin), then `AttendanceService`.

Response: redirect back to `GET /matches/{id}/prepare` with updated attendance shown, or JSON if the form uses AJAX.

## UI requirements (mobile-first)
- Each player row: name, jersey number, position label, three-button attendance control (Present | Absent | Injured)
- Selected state clearly highlighted (e.g., active class on button)
- Injured players visually distinct (e.g., different row color or indicator)
- Form submits via POST (standard form submission or progressive AJAX — server must work without JS)
- Validation errors displayed near the affected field

# Out of Scope
- Guest player addition (prompt 03)
- Formation and lineup grid (prompt 04)
- Prepare action validation (prompt 05)

# Architectural Rules
- Controller calls validator, then policy, then service
- `AttendanceService` wraps multi-player attendance updates in a transaction
- No SQL in the controller
- CSRF required on `POST /matches/{id}/attendance`

# Acceptance Criteria
- `GET /matches/{id}/prepare` loads without error for a planned match
- Each player in the active squad is listed with attendance controls
- Marking a player present/absent/injured persists the correct status value to the database
- Attendance status visible after page refresh
- Trainer/team-manager cannot reach the attendance POST route (403)
- CSRF missing on attendance POST returns 403
- Invalid status value (not present/absent/injured) rejected by validator

# Verification
- PHP syntax check all new files
- Manually open preparation screen for a planned match — confirm squad players listed
- Mark 3 players injured, 2 absent, rest present — save — refresh page — verify statuses persist
- Attempt `POST /matches/{id}/attendance` as a trainer — expect 403

# Handoff Note
`03-add-guests-to-match-end-to-end.md` adds internal and external guest player selection to the preparation screen, so those guests also appear in the attendance list.
