# Register Goal End to End

# Purpose
Implement goal registration for own goals and opponent goals, with optional assist and optional zone selection. Score must be re-derived from events after each goal, never incremented blindly.

# Required Context
See `01-shared-context.md`. Live match screen from `02-open-live-match-and-start-end-to-end.md`. `match_events` table in schema.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_events.event_type` values, zone values
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — goal eligibility rules, assist eligibility
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — goal registration modal UI

# Scope

## Route
```php
$router->post('/matches/{id}/events/goal', [\App\Http\Controllers\LiveMatchController::class, 'registerGoal']);
```

## `GoalEventRequest` (`app/Http/Requests/GoalEventRequest.php`)

Validate:
- `event_type`: required; must be a goal event type from docs (e.g., `goal_own` or `goal_opponent`) — use exact values
- `minute`: required, positive integer
- `scorer_player_id`: required when `event_type = goal_own`; must be integer; validated as active field player in Service
- `scorer_player_id`: forbidden when `event_type = goal_opponent` (opponent players not in system)
- `assist_player_id`: optional; only allowed when `event_type = goal_own`; if provided when opponent goal, reject
- `zone`: optional; if provided, must be a valid zone value from the docs (check `-01` for the zone enum — do NOT use integers 1-9 unless the docs specify that)

## `LiveMatchPolicy`
```php
class LiveMatchPolicy {
    public static function canRegisterEvent(): bool {
        return CurrentUser::hasAnyRole(['coach', 'administrator']);
    }
}
```

## `GoalEventService::register(int $matchId, array $data): void`

```php
public function register(int $matchId, array $data): void {
    $match = $this->matchRepository->findById($matchId);

    if ($match['state'] !== 'active') {
        throw new InvalidStateException('Match is not active.');
    }

    // Validate scorer eligibility for own goals
    if ($data['event_type'] === 'goal_own') {
        $isOnField = $this->lineupRepository->isActiveFieldPlayer($matchId, $data['scorer_player_id']);
        if (!$isOnField) {
            throw new \InvalidArgumentException('Scorer is not an active field player.');
        }
    }

    // Validate assist eligibility
    if (!empty($data['assist_player_id'])) {
        if ($data['event_type'] !== 'goal_own') {
            throw new \InvalidArgumentException('Assist is only available for own goals.');
        }
        if ($data['assist_player_id'] == $data['scorer_player_id']) {
            throw new \InvalidArgumentException('Assist player cannot be the same as the scorer.');
        }
        $isOnField = $this->lineupRepository->isActiveFieldPlayer($matchId, $data['assist_player_id']);
        if (!$isOnField) {
            throw new \InvalidArgumentException('Assist player is not an active field player.');
        }
    }

    // Insert event (single-row insert — no transaction needed)
    $this->eventRepository->insert([
        'match_id'          => $matchId,
        'event_type'        => $data['event_type'],
        'player_id'         => $data['scorer_player_id'] ?? null,
        'assist_player_id'  => $data['assist_player_id'] ?? null,
        'zone'              => $data['zone'] ?? null,
        'minute'            => (int)$data['minute'],
        'created_at'        => date('Y-m-d H:i:s'),
    ]);
    // Score is derived from events on next read — nothing to update here
}
```

## Goal registration UI (update `app/Views/matches/live.php`)

Add to the `#live-actions` section:

**Goal button**: opens a modal or inline form. The form should work without JavaScript (progressive enhancement):

```html
<form action="/matches/<?= e($match['id']) ?>/events/goal" method="POST">
    <input type="hidden" name="_csrf" value="<?= e(\App\Http\Helpers\CsrfHelper::getToken()) ?>">

    <label>Type
        <select name="event_type">
            <option value="goal_own">Our goal</option>
            <option value="goal_opponent">Opponent goal</option>
        </select>
    </label>

    <label>Scorer (our team)
        <select name="scorer_player_id">
            <?php foreach ($fieldPlayers as $p): ?>
            <option value="<?= e($p['player_id']) ?>"><?= e($p['name']) ?> (#<?= e($p['jersey_number']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Minute <input type="number" name="minute" min="1" max="200"></label>

    <label>Assist (optional)
        <select name="assist_player_id">
            <option value="">— none —</option>
            <?php foreach ($fieldPlayers as $p): ?>
            <option value="<?= e($p['player_id']) ?>"><?= e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Zone (optional)
        <select name="zone">
            <option value="">— none —</option>
            <!-- populate zone options from docs enum values -->
        </select>
    </label>

    <button type="submit">Register Goal</button>
</form>
```

JavaScript can hide/show scorer and assist fields based on the selected event_type, but the server validates regardless.

## Zone values

Use only the zone values defined in `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`. If the doc defines a 3×3 matrix with values like `top_left`, `top_center`, ... `bottom_right`, use those strings. If it defines integers, use those. Do not invent zone values.

# Out of Scope
- Penalty goals (v0.7.0)
- Substitutions (v0.6.0)
- Cards (v0.6.0)

# Architectural Rules
- Score is never written to a column after a goal — only re-read from events
- `GoalEventService` validates player eligibility before inserting the event
- CSRF on the POST route

# Acceptance Criteria
- Own goal: score increments by 1 for home side (re-derived from events)
- Opponent goal: score increments by 1 for away side
- Score correct after page refresh
- Goal appears in timeline with minute and player name (for own goals)
- Assist persists when provided; invalid assist (same player as scorer) rejected
- Assist on opponent goal rejected
- Invalid zone value rejected by validator
- Trainer/team-manager `POST` returns 403
- CSRF missing returns 403

# Verification
- PHP syntax check
- Register 2 own goals and 1 opponent goal → verify score 2-1 on live screen
- Refresh live screen → verify score still 2-1 (recalculated)
- Attempt to register own goal with assist = scorer → verify rejection

# Handoff Note
`04-manage-periods-end-to-end.md` implements match start and all period transition routes.
