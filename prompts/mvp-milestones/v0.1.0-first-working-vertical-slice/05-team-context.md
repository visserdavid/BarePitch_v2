# Team Context and Player List

# Purpose
Implement the current user helper, the team context helper, and a minimal player list view. This establishes the app's core operational context: who is logged in and which team are they managing.

# Required Context
See `01-shared-context.md`. Schema from `03-database-foundation.md` and seed data from `04-seed-data.md` must be in place.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — role definitions
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — player list route spec
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — UI requirements for player list

# Scope

## Developer Login Bypass (`app/Http/Helpers/CurrentUser.php`)

For this milestone only, authentication is provided by a developer bypass:

```php
class CurrentUser {
    public static function get(): ?array {
        // Local dev bypass — MUST be removed/gated in v0.2.0
        if (APP_ENV === 'local') {
            // Return the seeded dev user from DB
            $db = \App\Repositories\Database::connection();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute(['dev@barepitch.local']);
            return $stmt->fetch() ?: null;
        }
        return null; // No real auth yet — handled in v0.2.0
    }

    public static function id(): ?int {
        return self::get()['id'] ?? null;
    }

    public static function hasRole(string $role): bool {
        // Stub — always returns true for dev user in this milestone
        // v0.2.0 will implement real role checking
        return APP_ENV === 'local';
    }
}
```

**Important**: This bypass is explicitly temporary. Document its removal as the first task of v0.2.0. Do not use it as a template for production auth.

## Team Context (`app/Http/Helpers/TeamContext.php`)

```php
class TeamContext {
    public static function getActiveTeam(): ?array {
        // In this milestone: return the first team the dev user has a role for
        $userId = CurrentUser::id();
        if (!$userId) return null;

        $db = \App\Repositories\Database::connection();
        $stmt = $db->prepare('
            SELECT t.* FROM teams t
            JOIN user_team_roles utr ON utr.team_id = t.id
            WHERE utr.user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getActiveSeason(): ?array {
        // Return the most recent season linked to the active team
        $team = self::getActiveTeam();
        if (!$team) return null;

        $db = \App\Repositories\Database::connection();
        $stmt = $db->prepare('
            SELECT s.* FROM seasons s
            JOIN team_season ts ON ts.season_id = s.id
            WHERE ts.team_id = ?
            ORDER BY s.start_date DESC
            LIMIT 1
        ');
        $stmt->execute([$team['id']]);
        return $stmt->fetch() ?: null;
    }
}
```

## Player List

### Route: `GET /players`
Add to `routes/web.php`:
```php
$router->get('/players', [\App\Http\Controllers\PlayerController::class, 'index']);
```

### `app/Http/Controllers/PlayerController.php`

```php
class PlayerController {
    public function index(): void {
        $team = TeamContext::getActiveTeam();
        $season = TeamContext::getActiveSeason();

        if (!$team || !$season) {
            render('errors/no-context.php');
            return;
        }

        $players = (new \App\Repositories\PlayerRepository())->getActiveForTeamSeason(
            $team['id'],
            $season['id']
        );

        render('players/index.php', [
            'team'    => $team,
            'season'  => $season,
            'players' => $players,
        ]);
    }
}
```

### `app/Repositories/PlayerRepository.php`

```php
public function getActiveForTeamSeason(int $teamId, int $seasonId): array {
    $db = Database::connection();
    $stmt = $db->prepare('
        SELECT p.*, psc.jersey_number, psc.position
        FROM players p
        JOIN player_season_context psc ON psc.player_id = p.id
        WHERE psc.team_id = ?
          AND psc.season_id = ?
          AND p.active = 1
        ORDER BY psc.jersey_number
    ');
    $stmt->execute([$teamId, $seasonId]);
    return $stmt->fetchAll();
}
```

### `app/Views/players/index.php`

Mobile-first list view:
- Page title: "Players — [Team Name]"
- List each player: jersey number, name, position
- Simple, clean, no extra decoration

### Navigation stub (`app/Views/layouts/nav.php`)

Simple navigation with links to:
- Home (`/`)
- Players (`/players`)
- Matches (`/matches`) — stub link, not yet functional

# Out of Scope
- Player creation, editing, deactivation (v0.3.0)
- Full authentication (v0.2.0)
- Team switching for multi-team users (v0.2.0)
- Role-based visibility differences (v0.2.0)

# Architectural Rules
- No SQL in the controller — delegate to `PlayerRepository`
- `TeamContext` queries the DB when no session exists (acceptable for this milestone's dev bypass; v0.2.0 will cache in session)
- `CurrentUser` bypass must be clearly commented as temporary

# Acceptance Criteria
- `GET /players` renders a list of the 18 seeded players
- Players are ordered by jersey number
- Page shows the team name
- Navigation stub shows Home, Players, Matches links
- Missing team/season context renders a safe "no context" page, not an error

# Verification
- PHP syntax check all new files
- Start server: `php -S localhost:8000 -t public/`
- Visit `http://localhost:8000/players` — confirm 18 players listed
- Verify team name shown in page title or heading

# Handoff Note
`06-match-creation.md` adds the match list and match creation flow, using the team and season context established here.
