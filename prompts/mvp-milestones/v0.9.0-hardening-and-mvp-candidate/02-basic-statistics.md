# Basic Statistics — v0.9.0

# Purpose
Implement the minimal statistics layer required by MVP scope: per-player statistics and per-team statistics, both derived exclusively from data already present in the MVP schema. No new source-of-truth columns are invented. Statistics are read-only derived views over existing event and lineup data.

---

# Required Context
See `01-shared-context.md`. All prior milestones (v0.1.0 through v0.8.0) must be complete. Statistics are computed from `match_events`, `match_lineup`, `match_attendance`, and `matches` tables established in earlier milestones.

---

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — canonical schema; statistics queries must use only columns that exist in this document
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — who may view statistics
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — layer rules; statistics follow same architecture
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — derived data policy; statistics are derived, never source-of-truth
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — confirm statistics are in MVP scope; confirm which statistics are required
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — route definitions for `/stats/players` and `/stats/team`

---

# Scope

## Guiding Principle

Statistics are computed from existing MVP event data. Do not invent new database columns, new event types, or new tables to store pre-aggregated statistics. All values must be derivable from:

- `match_events` (goals, assists, yellow cards, red cards, with `player_id` and `match_id`)
- `match_lineup` (playing time: entry minute, exit minute or match end)
- `match_attendance` (attendance per match per player)
- `matches` (match outcomes: score, state, season_id, phase_id, team_id, opponent)

If a statistic cannot be derived from the above tables without adding new source-of-truth columns, do not implement it. Document it as a post-MVP gap instead.

---

## 1. StatisticsRepository

**File**: `app/Repositories/StatisticsRepository.php`

### 1.1 Player Statistics Query

Method: `getPlayerStats(int $teamId, ?int $seasonId = null, ?int $phaseId = null): array`

Returns one row per player who participated in at least one match for the team, with these columns:

| Column | Source | Notes |
|---|---|---|
| `player_id` | `match_attendance.player_id` | |
| `player_name` | `players.name` | JOIN on `players` |
| `matches_played` | COUNT of `match_attendance` rows with `status = 'present'` | Only finished matches |
| `goals` | COUNT of `match_events` rows with `event_type = 'goal'` for this player | |
| `assists` | COUNT of `match_events` rows with `event_type = 'assist'` for this player | |
| `yellow_cards` | COUNT of `match_events` rows with `event_type = 'yellow_card'` | |
| `red_cards` | COUNT of `match_events` rows with `event_type = 'red_card'` | |
| `playing_time_seconds` | SUM of `match_lineup.playing_time_seconds` for this player | Only finished matches |
| `attendance_percentage` | (matches_played / total_finished_matches_for_team) * 100 | Rounded to one decimal |

Filter rules:
- Only include matches with `state = 'finished'`
- Filter by `season_id` if provided
- Filter by `phase_id` if provided (within the season filter if both given)
- Scope to `team_id`

Use a single SQL query with LEFT JOINs and GROUP BY. Do not perform aggregation in PHP. Use PDO prepared statements with named parameters for all filter values.

Example query structure (adapt column names to match actual schema):
```sql
SELECT
    p.id AS player_id,
    p.name AS player_name,
    COUNT(DISTINCT CASE WHEN ma.status = 'present' THEN ma.match_id END) AS matches_played,
    COUNT(DISTINCT CASE WHEN me.event_type = 'goal' THEN me.id END) AS goals,
    COUNT(DISTINCT CASE WHEN me.event_type = 'assist' THEN me.id END) AS assists,
    COUNT(DISTINCT CASE WHEN me.event_type = 'yellow_card' THEN me.id END) AS yellow_cards,
    COUNT(DISTINCT CASE WHEN me.event_type = 'red_card' THEN me.id END) AS red_cards,
    COALESCE(SUM(ml.playing_time_seconds), 0) AS playing_time_seconds
FROM players p
JOIN match_attendance ma ON ma.player_id = p.id
JOIN matches m ON m.id = ma.match_id
    AND m.team_id = :team_id
    AND m.state = 'finished'
    AND (:season_id IS NULL OR m.season_id = :season_id)
    AND (:phase_id IS NULL OR m.phase_id = :phase_id)
LEFT JOIN match_events me ON me.player_id = p.id AND me.match_id = m.id
LEFT JOIN match_lineup ml ON ml.player_id = p.id AND ml.match_id = m.id
GROUP BY p.id, p.name
ORDER BY goals DESC, matches_played DESC, p.name ASC
```

Verify actual column names against `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` before implementing. Adjust the query to match the real schema exactly.

### 1.2 Total Finished Matches for Attendance Percentage

Method: `getTotalFinishedMatchCount(int $teamId, ?int $seasonId = null, ?int $phaseId = null): int`

Returns the count of finished matches for the team, optionally scoped to season and phase. Used to compute attendance percentage in the controller or a value object.

### 1.3 Team Statistics Query

Method: `getTeamStats(int $teamId, ?int $seasonId = null, ?int $phaseId = null): array`

Returns one row per team (typically one row — the current team's aggregated record) with these columns:

| Column | Source | Notes |
|---|---|---|
| `matches_played` | COUNT of finished matches | |
| `wins` | COUNT where team scored more goals than opponent in normal time | |
| `draws` | COUNT where team scored equal goals to opponent in normal time | |
| `losses` | COUNT where team scored fewer goals than opponent in normal time | |
| `goals_for` | SUM of team's goals from goal events | |
| `goals_against` | SUM of opponent goals (from `matches.opponent_score` if stored, else derived) | |
| `goal_difference` | `goals_for - goals_against` | Computed in query |

Filter rules:
- Only finished matches
- Filter by `season_id` if provided
- Filter by `phase_id` if provided

Do not include shootout goals in wins/draws/losses/goals_for/goals_against. Shootout outcomes are stored separately per the schema. Normal-time result determination uses normal-time score only.

If `matches.home_score` and `matches.away_score` columns do not exist in the schema (because score is derived from events), derive the score from `match_events` in the query. Check the schema document before deciding.

---

## 2. StatisticsController

**File**: `app/Http/Controllers/StatisticsController.php`

### 2.1 `GET /stats/players`

```php
public function players(): void {
    // 1. Authenticate — redirect to /login if no session
    // 2. Authorize — any team role may view; reject if no active team context
    // 3. Read optional filters from query string: season_id, phase_id
    // 4. Validate filters: must be positive integers if provided; reject unknown values
    // 5. Call StatisticsRepository::getPlayerStats()
    // 6. Call StatisticsRepository::getTotalFinishedMatchCount() for attendance pct
    // 7. Compute attendance_percentage for each player row
    // 8. Pass data to view
    render('stats/players.php', [
        'players' => $playerStats,
        'seasons' => $seasons,   // for filter dropdown
        'phases'  => $phases,    // for filter dropdown
        'filters' => $activeFilters,
    ]);
}
```

No business logic in the controller beyond assembling data for the view. All query logic stays in `StatisticsRepository`.

### 2.2 `GET /stats/team`

```php
public function team(): void {
    // 1. Authenticate
    // 2. Authorize — any team role
    // 3. Read optional filters: season_id, phase_id
    // 4. Validate filters
    // 5. Call StatisticsRepository::getTeamStats()
    // 6. Pass data to view
    render('stats/team.php', [
        'stats'   => $teamStats,
        'seasons' => $seasons,
        'phases'  => $phases,
        'filters' => $activeFilters,
    ]);
}
```

---

## 3. Authorization

Authorization for statistics routes follows `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`.

- `GET /stats/players`: accessible to coach, trainer, administrator, team_manager — any authenticated user with an active team role
- `GET /stats/team`: same as above
- Unauthenticated users: redirect to `/login`
- Authenticated user with no active team: redirect to `/teams/select` or no-access page

These are read-only GET routes. No CSRF token is required on GET routes. No policy mutation.

Create `app/Policies/StatisticsPolicy.php`:
```php
class StatisticsPolicy {
    public static function canView(): bool {
        return CurrentUser::hasActiveTeamRole();
    }
}
```

---

## 4. Views

**File**: `app/Views/stats/players.php`

- Table with columns: Name, Matches Played, Goals, Assists, Yellow Cards, Red Cards, Playing Time, Attendance %
- Season and phase filter dropdowns (GET form, no CSRF needed)
- All output escaped with `htmlspecialchars()`
- All visible labels use the translation helper `__()` (see `03-internationalization-foundation.md`)
- Playing time displayed as hours and minutes (e.g., "1h 23m"), not raw seconds — convert in the view
- Empty state message if no data returned

**File**: `app/Views/stats/team.php`

- Summary table: Matches Played, Wins, Draws, Losses, Goals For, Goals Against, Goal Difference
- Season and phase filter dropdowns
- All output escaped
- All labels use `__()`
- Empty state message if no finished matches

---

## 5. Routes

Add to the router:
```
GET /stats/players  → StatisticsController::players
GET /stats/team     → StatisticsController::team
```

Both routes must be behind the authentication middleware. No write routes are added.

---

# Out of Scope

- Player ratings or performance scores
- Advanced match-by-match breakdown tables
- Opponent statistics
- Statistics for matches in non-`finished` state
- Statistics requiring new database columns not in the current schema
- Export (CSV, PDF) of statistics
- Statistics for archived or deleted players (unless already trivially included by the query)
- Training statistics

---

# Architectural Rules

- `StatisticsRepository` is the only class that executes statistics SQL
- `StatisticsController` does not contain SQL or business aggregation logic
- Views do not perform calculations beyond display formatting (seconds to h/m conversion is acceptable in the view)
- No new tables created; no schema migrations in this prompt
- Statistics are always computed on-the-fly from event data; no pre-aggregation cache tables
- If a statistic is not derivable from existing MVP tables, document the gap and do not implement it

---

# Acceptance Criteria

- `GET /stats/players` returns correct player statistics for a seeded dataset of finished matches
- `GET /stats/team` returns correct wins/draws/losses/GF/GA for a seeded dataset
- Season filter correctly excludes matches outside the selected season
- Phase filter correctly excludes matches outside the selected phase
- Combining season and phase filters returns only matches in that phase within that season
- A player with no goals shows 0 goals (not null, not blank)
- Playing time of 0 seconds shows "0m" or equivalent, not blank
- Attendance percentage is correct to one decimal place
- Shootout goals are NOT included in goals_for or normal score totals
- Unauthenticated access to `/stats/players` redirects to `/login`
- Access with no active team redirects to team selection or no-access
- All view output is escaped with `htmlspecialchars()`
- All visible labels use `__()`

---

# Verification

1. PHP syntax check all new files:
   ```bash
   find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
2. Seed a test dataset with known outcomes (e.g., 3 wins, 1 draw, 1 loss, 5 goals for, 3 against) and verify the team stats view matches exactly.
3. Seed a player with known goals/assists and verify the player stats view matches.
4. Manually test season filter: create matches in two seasons; verify filter returns only one season's data.
5. Verify unauthenticated access redirects.
6. Verify output escaping: inject `<script>alert(1)</script>` as a player name in the test database and confirm it is rendered as escaped text, not executed.

---

# Handoff Note

After this prompt, the statistics routes are implemented and the views display correct data. `03-internationalization-foundation.md` requires that all labels in these views (and all other touched MVP screens) use the `__()` translation helper — confirm the views written here already use `__()`, or update them immediately after completing prompt 03.
