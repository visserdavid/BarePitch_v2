# Database Foundation

# Purpose
Install the complete core database schema for all MVP entities needed by the v0.1.0 vertical slice. Every table required from project start through a finished match summary must be created in the correct dependency order.

# Required Context
See `01-shared-context.md`. The PDO connection from `02-project-foundation.md` must be working. Run migrations on the target database before proceeding to later prompts.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — **canonical schema source; use exact column names, types, and constraints from this document**
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — derived data columns and state fields

# Scope

## Migration Files

Create ordered migration files in `database/migrations/`. Each file is a plain SQL file executed in numeric order. Name format: `001_create_clubs.sql`, `002_create_seasons.sql`, etc.

**Do not invent columns or tables not in the schema doc.** If a column exists in the docs, include it. If it is not in the docs, omit it.

### Required entities and dependency order:

1. `001_create_clubs.sql` — clubs table (no dependencies)
2. `002_create_seasons.sql` — seasons (no dependencies)
3. `003_create_phases.sql` — phases (references seasons)
4. `004_create_teams.sql` — teams (references clubs)
5. `005_create_team_season.sql` — team-season-phase association (references teams, seasons, phases)
6. `006_create_users.sql` — users (no dependencies)
7. `007_create_user_team_roles.sql` — user team role assignments (references users, teams)
8. `008_create_formations.sql` — formations
9. `009_create_formation_positions.sql` — formation positions (references formations)
10. `010_create_players.sql` — players
11. `011_create_player_season_context.sql` — player team/season context (references players, teams, seasons)
12. `012_create_matches.sql` — matches (references teams, phases, formations)
13. `013_create_match_periods.sql` — match periods/halves (references matches)
14. `014_create_match_attendance.sql` — player attendance per match (references matches, players)
15. `015_create_match_lineup.sql` — current lineup state (references matches, players, formation_positions)
16. `016_create_match_events.sql` — match events: goals, cards, substitutions, etc. (references matches, players)
17. `017_create_match_guests.sql` — guest players per match (references matches, players)

### Migration runner script (`scripts/migrate.php`)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

$db = \App\Repositories\Database::connection();
$migrationsDir = __DIR__ . '/../database/migrations/';

$files = glob($migrationsDir . '*.sql');
sort($files);

foreach ($files as $file) {
    echo "Running: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    $db->exec($sql);
    echo "  Done.\n";
}
echo "All migrations complete.\n";
```

Run with: `php scripts/migrate.php`

## Key schema requirements (verify against docs)

- All foreign keys must be defined with `ON DELETE` behavior per docs
- Match `state` column: ENUM or VARCHAR with values `planned`, `prepared`, `active`, `finished`
- `match_lineup`: must include `is_starter` flag, `x_coord`, `y_coord` (nullable), `position_id` (nullable for bench)
- `match_events`: must include `event_type` (goal_own, goal_opponent, yellow_card, red_card, substitution, penalty_scored, penalty_missed, etc. — per docs), `player_id` (nullable), `minute`, `match_id`
- `players`: must include active/inactive flag for soft deactivation
- `user_team_roles`: role ENUM must include exactly: `coach`, `trainer`, `administrator`, `team_manager`

# Out of Scope
- Seed data (prompt 04)
- Application code reading these tables (later prompts)
- Rate limit table (v0.2.0)
- Audit log table (v0.8.0)
- Shootout attempts table (v0.7.0)

# Architectural Rules
- Schema is the canonical source of truth — match the docs exactly
- Do not add columns "just in case" that are not in the domain model doc
- All foreign key references must use the correct primary key column names from the docs
- Character set: `utf8mb4`, collation: `utf8mb4_unicode_ci` for string columns

# Acceptance Criteria
- Running `php scripts/migrate.php` on a fresh database completes without SQL errors
- `SHOW TABLES` after migration returns all 17+ expected tables
- Foreign key constraints are present (verify with `SHOW CREATE TABLE match_lineup`)
- Migration order is correct — no foreign key violation on fresh run
- `match_lineup` has nullable `x_coord`, `y_coord`, and an `is_starter` flag
- `matches.state` column accepts only documented state values

# Verification
- Run `php scripts/migrate.php` on a fresh empty database
- Run `SHOW TABLES;` — confirm all expected tables present
- Run `DESCRIBE match_lineup;` — confirm column structure
- Run `DESCRIBE match_events;` — confirm `event_type` and `player_id` columns
- Run `php -l scripts/migrate.php` — no syntax errors

# Handoff Note
`04-seed-data.md` populates the database with the minimum working dataset for local development, including a developer account and enough players to run a full match.
