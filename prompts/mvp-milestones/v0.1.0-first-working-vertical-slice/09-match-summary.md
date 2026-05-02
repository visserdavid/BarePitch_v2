# Match Finish and Summary

# Purpose
Implement the match finish action and the finished match summary view. After a match is finished, its summary must remain viewable and the `finished` state must be terminal — no restart allowed.

# Required Context
See `01-shared-context.md`. Match is in `active` state with at least one period managed (from `08-live-match.md`).

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — finished state rules
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — state transition policy

# Scope

## Routes (add to `routes/web.php`)

```php
$router->post('/matches/{id}/finish',  [\App\Http\Controllers\LiveMatchController::class, 'finish']);
$router->get('/matches/{id}/summary',  [\App\Http\Controllers\MatchController::class, 'summary']);
```

## `POST /matches/{id}/finish`

`LiveMatchService::finishMatch(int $matchId, bool $confirmed): void`
- Validate: match state must be `active`
- Validate: `$confirmed` must be true (the POST body must include `confirm=1`)
- Transaction:
  - Ensure the current period has an `ended_at` set (or set it if not already done)
  - `UPDATE matches SET state = 'finished', finished_at = NOW() WHERE id = ?`
- Redirect to `GET /matches/{id}/summary`

**Confirmation requirement**: the finish button in the UI must require a deliberate action before POSTing — either a JavaScript confirmation dialog or a two-step button that reveals a confirmation form on first click. The server validates `confirm=1` in the POST body.

## `GET /matches/{id}/summary`

`MatchController::summary(int $id): void`
- Load match (must belong to active team)
- Load final score via `ScoreService::calculate($matchId)` (from match_events)
- Load full timeline of events
- Load lineup (starters and bench at match end)
- Load match metadata (opponent, phase, match_type, scheduled_at)
- Render summary view

## Summary View (`app/Views/matches/summary.php`)

Show:
- Final score: `[Home Score] - [Away Score]` (recalculated from events)
- Match metadata: vs opponent, home/away, date, phase
- Full event timeline: each event with minute, event type icon/label, player name
- Lineup section: starters and bench at the time of finish
- Link back to match list

## Terminal state enforcement

In `LiveMatchService` and `MatchPreparationService`, all state-change methods must check:
```php
if ($match['state'] === 'finished') {
    throw new \App\Domain\Exceptions\InvalidStateException(
        'This match is finished and cannot be modified.'
    );
}
```

Routes that must reject finished matches:
- `POST /matches/{id}/start` — reject finished
- `POST /matches/{id}/prepare` — reject finished
- `POST /matches/{id}/events/goal` — reject finished
- `POST /matches/{id}/periods/*` — reject finished
- `POST /matches/{id}/finish` — reject if already finished

## Score service (`app/Services/ScoreService.php`)

```php
class ScoreService {
    public static function calculate(int $matchId): array {
        $db = Database::connection();
        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN event_type IN ('goal_own', 'penalty_scored_own') THEN 1 ELSE 0 END) AS home_score,
                SUM(CASE WHEN event_type IN ('goal_opponent', 'penalty_scored_opponent') THEN 1 ELSE 0 END) AS away_score
            FROM match_events
            WHERE match_id = ?
        ");
        $stmt->execute([$matchId]);
        $result = $stmt->fetch();
        return [
            'home' => (int)($result['home_score'] ?? 0),
            'away' => (int)($result['away_score'] ?? 0),
        ];
    }
}
```

Use this in both the live match screen and the summary — consistent score derivation from the same source.

# Out of Scope
- Finished-match corrections (v0.8.0)
- Audit log (v0.8.0)
- Detailed statistics (v0.9.0)
- Ratings

# Architectural Rules
- `finished` state is terminal — no service method may transition away from it
- Score is always derived from events — `ScoreService::calculate()` is the single source of truth
- Confirmation required for finish action — server validates the confirm flag, not just the UI

# Acceptance Criteria
- Finishing an active match (with confirm=1) transitions state to `finished`
- Summary page shows correct final score
- Score correct after page refresh (recalculated from events)
- Attempting `POST /matches/{id}/start` on a finished match returns a safe error
- Attempting `POST /matches/{id}/prepare` on a finished match returns a safe error
- Attempting `POST /matches/{id}/events/goal` on a finished match returns a safe error
- Summary is viewable by the coach

# Verification
- PHP syntax check all new files
- End-to-end: create match → prepare → start → register goals → end periods → finish → view summary → verify score
- Attempt `POST /matches/{id}/finish` on a finished match — verify safe error
- Refresh summary — verify score unchanged (recalculated)

# Handoff Note
`10-security-and-authorization.md` hardens all routes in this milestone with CSRF, server-side authorization, and prepared statement audit.
