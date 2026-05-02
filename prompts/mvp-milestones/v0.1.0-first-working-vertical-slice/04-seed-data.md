# Seed Data

# Purpose
Populate the local development database with a complete working dataset: one club, season, phase, team, developer account, formation, and enough players to run a full match preparation and live match flow.

# Required Context
See `01-shared-context.md`. Migrations from `03-database-foundation.md` must be installed. This prompt creates only data, no application code.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — valid enum values, role values, formation structure
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — minimum setup requirements

# Scope

## Seed Script (`database/seeds/seed_development.php`)

This script is for **local development only**. It must be clearly marked and must NOT be run in production or staging.

```php
<?php
// DEVELOPMENT SEED ONLY — do not run in production
if (getenv('APP_ENV') !== 'local') {
    die("Seed script can only run in local environment.\n");
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/app.php';

$db = \App\Repositories\Database::connection();
```

### Data to create

**Club** — insert one club:
- name: "Demo FC"

**Season** — insert one season:
- name: "2024-2025"
- start_date, end_date: per schema requirements

**Phase** — insert one phase:
- name: "Competitie"
- type: use the valid type enum from the domain model doc (e.g., `competition` or whatever the doc specifies)
- references the season above

**Team** — insert one team:
- name: "Demo FC First Team"
- references the club above

**Team-Season association** — link the team to the season and phase (per `team_season` table structure from schema)

**User** — insert one developer account:
- email: `dev@barepitch.local`
- name: "Dev Admin"
- No password (magic-link only; this account is used with the local dev bypass)

**User Team Role** — assign the developer user to the team with both `coach` AND `administrator` roles (two rows if the schema stores one role per row, or a combined record if it stores multiple):
- This gives the dev user full access to test all coach and admin flows

**Formation** — insert one formation (e.g., 4-3-3):
- name: "4-3-3"
- Insert `formation_positions` rows for each position in the formation (goalkeeper + 10 outfield positions, with position identifiers and grid coordinates per the schema)

**Players** — insert **18 players** (enough for 11 starters + 7 bench + room for guests):

For each player:
- name: realistic names (Player 1 through Player 18 is acceptable)
- jersey_number: 1–18
- position: distribute across GK (1), DEF (5), MID (6), FWD (4), remaining 2 as MID or FWD
- active: true
- Insert `player_season_context` rows linking each player to the team and season

## Runner script (`scripts/seed.php`)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';

if (APP_ENV !== 'local') {
    die("Seed script can only run in local environment.\n");
}

require_once __DIR__ . '/../database/seeds/seed_development.php';
echo "Seed complete.\n";
```

Run with: `php scripts/seed.php`

## Idempotency
The seed script should check if data already exists before inserting, or truncate and re-insert. Use `INSERT IGNORE` or a `DELETE FROM` + re-insert pattern to make it re-runnable.

# Out of Scope
- Production accounts
- Real email addresses
- Multiple clubs or seasons
- Player photos
- External guest players (created through UI in v0.3.0)

# Architectural Rules
- Seed script must refuse to run when `APP_ENV !== 'local'`
- All inserts must use PDO prepared statements (not raw string interpolation)
- The developer account password field (if it exists in the schema) is left null or set to a placeholder — authentication in this milestone uses the dev bypass

# Acceptance Criteria
- `php scripts/seed.php` completes without SQL errors
- `SELECT COUNT(*) FROM players;` returns 18
- `SELECT COUNT(*) FROM formation_positions;` returns 11 (one per position in the formation)
- `SELECT * FROM users WHERE email = 'dev@barepitch.local';` returns one row
- `SELECT * FROM user_team_roles WHERE user_id = <dev_user_id>;` returns at least one row with `coach` or `administrator` role
- Running the seed twice does not produce duplicate rows

# Verification
- Run `php scripts/seed.php` twice — no errors, no duplicate rows
- Verify player count: `SELECT COUNT(*) FROM players;` → 18
- Verify team-season link: `SELECT * FROM team_season;` → at least one row
- PHP syntax check: `php -l database/seeds/seed_development.php`

# Handoff Note
`05-team-context.md` builds the minimal team context UI on top of this data, including the player list and the developer account's session bypass.
