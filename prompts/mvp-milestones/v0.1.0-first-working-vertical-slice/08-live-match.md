# Live Match Screen

# Purpose
Implement the live match screen: start match, register own and opponent goals (with optional assist and zone), manage period transitions (end first half, start second half, end second half).

# Required Context
See `01-shared-context.md`. Match must be in `prepared` state (from `07-match-preparation.md`). Schema includes `match_events`, `match_periods`.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — match start rules, period transition state machine, score integrity
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — live match UI spec

# Scope

## Routes (add to `routes/web.php`)

```php
$router->get('/matches/{id}/live',                  [\App\Http\Controllers\LiveMatchController::class, 'show']);
$router->post('/matches/{id}/start',                [\App\Http\Controllers\LiveMatchController::class, 'start']);
$router->post('/matches/{id}/events/goal',          [\App\Http\Controllers\LiveMatchController::class, 'registerGoal']);
$router->post('/matches/{id}/periods/end-first',    [\App\Http\Controllers\LiveMatchController::class, 'endFirstHalf']);
$router->post('/matches/{id}/periods/start-second', [\App\Http\Controllers\LiveMatchController::class, 'startSecondHalf']);
$router->post('/matches/{id}/periods/end-second',   [\App\Http\Controllers\LiveMatchController::class, 'endSecondHalf']);
```

## `POST /matches/{id}/start`

`LiveMatchService::startMatch(int $matchId): void`
- Validate: match state must be `prepared` — reject `planned` or `active` with clear error
- Transaction:
  - Create first period: `INSERT INTO match_periods (match_id, type, started_at) VALUES (?, 'first_half', NOW())`
  - Update match: `UPDATE matches SET state = 'active' WHERE id = ?`
- Redirect to `GET /matches/{id}/live`

## `GET /matches/{id}/live` — Live Match Screen

Show:
- **Score header**: home score vs away score — computed from `match_events`; never from a stored counter
- **Current period**: name of the active period (first_half, second_half, etc.)
- **Current lineup**: field players in their positions; bench list
- **Timeline**: ordered list of match events (goals with minute, player name, event type)
- **Action controls**: Start Half 2, End Half 1, End Half 2 buttons (contextual — only show relevant action)
- **Goal registration form/modal**: inline form or modal with fields:
  - `type`: own/opponent
  - `scorer_player_id`: select from active field players (required for own goals)
  - `minute`: integer
  - `assist_player_id`: select from active field players (optional, for own goals only)
  - `zone`: optional 3×3 zone value (per docs zone enum — do not invent values)

Score calculation helper:
```php
public static function calculateScore(int $matchId): array {
    $db = Database::connection();
    $stmt = $db->prepare("
        SELECT
            SUM(CASE WHEN event_type = 'goal_own' THEN 1 ELSE 0 END) AS home_score,
            SUM(CASE WHEN event_type = 'goal_opponent' THEN 1 ELSE 0 END) AS away_score
        FROM match_events
        WHERE match_id = ?
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch();
}
```

**Never** update a `score_home` or `score_away` column as the source of truth — always recalculate from events.

## `POST /matches/{id}/events/goal`

`GoalEventService::register(int $matchId, array $data): void`
- Validate: match is `active`
- Validate `event_type`: `goal_own` or `goal_opponent` (per schema event_type enum)
- For `goal_own`: `scorer_player_id` required; player must currently be in `match_lineup` as a field player (not bench) for this match
- `assist_player_id`: optional, must be different from scorer, must also be an active field player
- `zone`: optional; if provided, must be a valid zone value from the docs (do NOT invent zone values)
- For `goal_opponent`: no scorer required
- Transaction: insert into `match_events`
- Score is derived from events on next page load — no counter to update

## Period Management Routes

Each period route follows the same pattern: validate match is active, validate correct current period state, create/update period record in `match_periods`.

**`POST /matches/{id}/periods/end-first`**: end first half — set `match_periods.ended_at` where `type='first_half'`  
**`POST /matches/{id}/periods/start-second`**: start second half — insert `match_periods` with `type='second_half'`  
**`POST /matches/{id}/periods/end-second`**: end second half — set `ended_at` for second_half period

Period transition validation: check the critical behavior spec for exactly which states are valid. Out-of-order transitions (e.g., starting second half when first half is still active) must be rejected with a safe error.

## Views

**`app/Views/matches/live.php`**:
- Score header: `[Home Score] - [Away Score]` — computed, never from a column
- Current period label
- Current lineup display (field + bench sections)
- Timeline: reverse-chronological list of events
- Goal registration form/modal (mobile-first)
- Period transition buttons (contextual, only valid next action shown)

# Out of Scope
- Substitutions, cards (v0.6.0)
- Penalties, extra time, shootout (v0.7.0)
- Livestream (v0.8.0)
- Finish match action (prompt 09)

# Architectural Rules
- Score is NEVER stored as an increment — always recalculated from `match_events`
- Period transitions go through `LiveMatchService` — controller never touches `match_periods` directly
- CSRF stub on all POSTs (full validation in prompt 10)

# Acceptance Criteria
- Starting a planned match returns an error; starting a prepared match sets state=`active`
- Own goal: score shows 1-0; opponent goal: score shows 0-1
- Score correct after page refresh (recalculated from events)
- Goal appears in timeline with minute
- Assist and zone persist when provided
- Invalid assist (same player as scorer) is rejected
- Invalid zone value is rejected
- First half can be ended; second half can be started and ended
- Out-of-order period transitions rejected with safe error

# Verification
- PHP syntax check all new files
- Start a prepared match, register 2 own goals and 1 opponent goal
- Refresh the page — verify score shows 2-1 (recalculated)
- Attempt to start a planned match — verify rejection
- Walk through all four period transitions

# Handoff Note
`09-match-summary.md` adds the finish action and the finished match summary view.
