# Register In-Match Penalty End to End

# Purpose
Implement penalty event registration during regular or extra time. Scored penalties update the match score; missed penalties do not. No assist is allowed for penalties.

# Required Context
See `01-shared-context.md`. Goal event infrastructure from v0.5.0. `getEligiblePenaltyTakers()` from v0.6.0.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — penalty rules
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_events.event_type` values for penalties

# Scope

## Route
```php
$router->post('/matches/{id}/events/penalty', [\App\Http\Controllers\LiveMatchController::class, 'registerPenalty']);
```

Requires CSRF and `LiveMatchPolicy::canRegisterEvent()`.

## `PenaltyEventRequest`

Validate:
- `outcome`: required, enum: `scored` or `missed`
- `taker_player_id`: required, integer
- `minute`: required, positive integer
- `zone`: optional; if provided, must be a valid zone value from docs (only applicable for scored penalties per docs)
- **`assist_player_id` is NOT a valid field** — if present in the request, validator must reject it with a clear error

## `PenaltyEventService::register(int $matchId, array $data): void`

```php
public function register(int $matchId, array $data): void {
    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] !== 'active') {
        throw new InvalidStateException('Match is not active.');
    }

    // Reject assist field
    if (isset($data['assist_player_id']) && $data['assist_player_id'] !== '') {
        throw new \InvalidArgumentException('Assist is not available for penalty kicks.');
    }

    $takerId  = (int)$data['taker_player_id'];
    $outcome  = $data['outcome'];
    $minute   = (int)$data['minute'];

    // Validate taker eligibility: must not be sent off; must be in match squad
    $eligibleTakers = $this->lineupRepository->getEligiblePenaltyTakers($matchId);
    $eligibleIds = array_column($eligibleTakers, 'player_id');
    if (!in_array($takerId, $eligibleIds, true)) {
        throw new \InvalidArgumentException('Taker is not eligible (sent off or not in squad).');
    }

    // Determine event_type based on outcome and team
    // Assume 'taker' is always our team player (opponent penalty taken by opponent — track separately if needed)
    if ($outcome === 'scored') {
        $eventType = 'penalty_scored_own'; // exact value from docs
    } else {
        $eventType = 'penalty_missed_own'; // exact value from docs
    }

    // Insert event
    $this->eventRepository->insert([
        'match_id'   => $matchId,
        'event_type' => $eventType,
        'player_id'  => $takerId,
        'zone'       => ($outcome === 'scored') ? ($data['zone'] ?? null) : null, // zone only for scored
        'minute'     => $minute,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // Score is re-derived from events (ScoreService::calculate() handles penalty_scored_own)
    // No explicit score update needed here
}
```

**Note on event types**: check `docs/-01` for the exact `event_type` strings. Possibilities: `penalty_scored`, `penalty_missed`, or with `_own`/`_opponent` suffixes. Use the exact documented values. If there is no separate type for opponent penalties, check the docs for how opponent penalty scoring is tracked.

## Score calculation update

Verify `ScoreService::calculate()` includes penalty_scored events in the count:
```sql
SUM(CASE WHEN event_type IN ('goal_own', 'penalty_scored_own') THEN 1 ELSE 0 END) AS home_score,
SUM(CASE WHEN event_type IN ('goal_opponent', 'penalty_scored_opponent') THEN 1 ELSE 0 END) AS away_score
```

Missed penalty events must NOT be counted.

## Penalty registration UI

Add to the live match action controls. Similar to goal registration modal but with:
- `outcome` selector (scored/missed)
- `taker_player_id` dropdown (populated from `getEligiblePenaltyTakers()`)
- `minute`
- `zone` (optional, for scored penalties)
- No assist field

# Out of Scope
- Opponent penalty tracking if not in docs
- Shootout penalty attempts (separate table, prompt 04)

# Architectural Rules
- `PenaltyEventService` must not update a score column — score is always re-derived
- `assist_player_id` in the request body must cause a validation rejection
- Zone stored only for scored penalties

# Acceptance Criteria
- Scored own penalty: normal match score increases by 1 (re-derived from events)
- Missed penalty: score unchanged; missed event in timeline
- Assist field in request: rejected with validation error
- Sent-off player as taker: rejected
- Player not in match squad as taker: rejected
- Zone persists when provided for scored penalty; zone ignored for missed penalty

# Verification
- PHP syntax check
- Register scored penalty → verify score increases and event_type is penalty_scored_own in DB
- Register missed penalty → verify score unchanged and event_type is penalty_missed_own in DB
- Submit request with assist_player_id → verify validation error

# Handoff Note
`03-manage-extra-time-end-to-end.md` implements extra time period management.
