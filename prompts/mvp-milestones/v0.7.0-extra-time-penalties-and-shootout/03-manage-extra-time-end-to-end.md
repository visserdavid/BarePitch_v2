# Manage Extra Time End to End

# Purpose
Implement extra time period transitions: start extra time after regular time ends, manage the two extra-time halves, and enforce the documented state machine.

# Required Context
See `01-shared-context.md`. Period management from v0.5.0. `match_periods` schema. `matches.extra_time_duration` set during match creation.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — when extra time is available, period state machine for extra time
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_periods.type` enum values for extra time periods

# Scope

## Routes
```php
$router->post('/matches/{id}/periods/start-extra-time',           [\App\Http\Controllers\LiveMatchController::class, 'startExtraTime']);
$router->post('/matches/{id}/periods/end-extra-time-first-half',  [\App\Http\Controllers\LiveMatchController::class, 'endExtraTimeFirstHalf']);
$router->post('/matches/{id}/periods/start-extra-time-second-half', [\App\Http\Controllers\LiveMatchController::class, 'startExtraTimeSecondHalf']);
$router->post('/matches/{id}/periods/end-extra-time-second-half', [\App\Http\Controllers\LiveMatchController::class, 'endExtraTimeSecondHalf']);
```

All require CSRF and `LiveMatchPolicy::canRegisterEvent()`.

## Extra time eligibility check

Before allowing extra time to start, validate:
1. Match is `active`
2. Regular time has ended: both `first_half` and `second_half` periods have `ended_at IS NOT NULL`
3. Extra time has not yet started: no period of type `extra_time_first_half` exists
4. `matches.extra_time_duration` is NOT null — if null, reject with "Extra time duration not configured for this match"
5. Per the critical behavior spec: extra time may only be started under documented conditions (e.g., tied score or specific match_type) — implement this check per the docs

Confirmation required for starting extra time (critical action — show confirmation modal before submitting).

## `LiveMatchService::startExtraTime(int $matchId, bool $confirmed): void`

```php
public function startExtraTime(int $matchId, bool $confirmed): void {
    if (!$confirmed) throw new \InvalidArgumentException('Confirmation required to start extra time.');

    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] !== 'active') throw new InvalidStateException('Match is not active.');
    if (empty($match['extra_time_duration'])) {
        throw new \InvalidArgumentException('Extra time duration not configured.');
    }

    // Check regular time ended
    $regularEnded = $this->periodRepository->bothRegularPeriodsEnded($matchId);
    if (!$regularEnded) throw new InvalidStateException('Regular time has not ended.');

    // Check extra time not already started
    if ($this->periodRepository->extraTimeStarted($matchId)) {
        throw new InvalidStateException('Extra time has already started.');
    }

    // Insert extra_time_first_half period
    $stmt = $this->db->prepare("
        INSERT INTO match_periods (match_id, type, started_at)
        VALUES (?, 'extra_time_first_half', NOW())
    ");
    $stmt->execute([$matchId]);
}
```

Period type values: use exact values from `docs/-01` (e.g., `extra_time_first_half`, `extra_time_second_half` — or whatever the schema defines).

## Remaining extra time period transitions

Follow the same pattern as regular time from v0.5.0:

**`endExtraTimeFirstHalf()`**: validate `extra_time_first_half` period exists with `ended_at IS NULL`; set `ended_at = NOW()`

**`startExtraTimeSecondHalf()`**: validate first extra half ended; insert `extra_time_second_half` period

**`endExtraTimeSecondHalf()`**: validate `extra_time_second_half` period exists with `ended_at IS NULL`; set `ended_at = NOW()`

All extra time period transitions are single-row updates — not transactional (but validate state before writing).

## Live screen update

Show extra time controls only when regular time has ended and extra time is appropriate. After all regular time periods end:
- Show "Start Extra Time" button (with confirmation) if `extra_time_duration` is set
- Show "Start Penalty Shootout" button (without extra time path, if docs allow this)

Hide regular time period buttons when extra time is active.

## Extra time duration display

Show the configured extra time duration on the live screen when extra time is active:
```html
<span>Extra Time — <?= e($match['extra_time_duration']) ?> min per half</span>
```

# Out of Scope
- Penalty shootout (prompt 04)
- Automatic suggestion of extra time based on score (coach decides)

# Architectural Rules
- Extra time start requires confirmation both client-side and server-side
- Duration must come from `matches.extra_time_duration` — not hardcoded
- State machine transitions from the critical behavior spec must be enforced

# Acceptance Criteria
- Extra time can be started only after both regular time halves have ended
- Extra time requires `extra_time_duration` to be set on the match
- Both extra-time halves can be started and ended
- Out-of-order extra-time transitions rejected
- Confirmation required for extra time start

# Verification
- PHP syntax check
- End both regular halves; click "Start Extra Time" → verify period created in DB
- Attempt to start extra time before regular halves end → verify rejection
- Walk through all four extra-time transitions

# Handoff Note
`04-run-penalty-shootout-end-to-end.md` implements the penalty shootout flow.
