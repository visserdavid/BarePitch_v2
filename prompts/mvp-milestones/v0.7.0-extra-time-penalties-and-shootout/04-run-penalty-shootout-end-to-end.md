# Run Penalty Shootout End to End

# Purpose
Implement the penalty shootout: start action, attempt registration, round tracking, sudden death, automatic ending detection with confirmation, and manual ending.

# Required Context
See `01-shared-context.md`. `getEligiblePenaltyTakers()` from v0.6.0. `matches.extra_time_duration` from match creation.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — shootout state machine, automatic ending, round rules
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_shootout_attempts` schema

# Scope

## Schema verification

Check `docs/-01` for `match_shootout_attempts`. Expected structure:
- `id`, `match_id`, `team` (own/opponent enum), `player_id` (nullable for opponent attempts), `round_number` (integer), `attempt_order` (integer, order within the round), `outcome` (scored/missed), `is_sudden_death` (boolean or derived), `created_at`

Verify the exact column names and types from the docs. Do not invent structure.

## Routes
```php
$router->post('/matches/{id}/shootout/start',    [\App\Http\Controllers\ShootoutController::class, 'start']);
$router->post('/matches/{id}/shootout/attempts', [\App\Http\Controllers\ShootoutController::class, 'recordAttempt']);
$router->post('/matches/{id}/shootout/finish',   [\App\Http\Controllers\ShootoutController::class, 'finish']);
$router->get('/matches/{id}/shootout',           [\App\Http\Controllers\ShootoutController::class, 'show']);
```

All POST routes require CSRF and `LiveMatchPolicy::canRegisterEvent()`.

## `POST /matches/{id}/shootout/start`

`ShootoutService::start(int $matchId, bool $confirmed): void`

Eligibility per critical behavior spec — check when shootout is allowed:
- After regular time ends (without going to extra time), OR
- After extra time ends

Set `matches.shootout_started = 1` (or create an initial shootout record, per docs).
Confirmation required.

## `POST /matches/{id}/shootout/attempts`

`ShootoutRequest`:
- `team`: required, enum (e.g., `own`, `opponent`)
- `round_number`: required, positive integer
- `outcome`: required, enum `scored` or `missed`
- `player_id`: required when `team = own`; not required when `team = opponent`

`ShootoutService::recordAttempt(int $matchId, array $data): void`

```php
public function recordAttempt(int $matchId, array $data): void {
    $match = $this->matchRepository->findById($matchId);
    if (!$match['shootout_started']) {
        throw new InvalidStateException('Shootout has not started.');
    }
    if ($match['state'] === 'finished') {
        throw new InvalidStateException('Match is already finished.');
    }

    $team       = $data['team'];
    $round      = (int)$data['round_number'];
    $outcome    = $data['outcome'];
    $playerId   = isset($data['player_id']) ? (int)$data['player_id'] : null;

    // Validate taker eligibility for own team
    if ($team === 'own') {
        if (!$playerId) throw new \InvalidArgumentException('Player ID required for own team attempts.');
        $eligible = array_column($this->lineupRepository->getEligiblePenaltyTakers($matchId), 'player_id');
        if (!in_array($playerId, $eligible, true)) {
            throw new \InvalidArgumentException('Taker is not eligible (sent off or not in squad).');
        }
    }

    // Validate attempt_order uniqueness within round
    // attempt_order = count of existing attempts in this round + 1
    $attemptOrder = $this->shootoutRepository->countAttemptsInRound($matchId, $round) + 1;

    // Check for duplicate: same team, same round, same order? Should not happen with auto-increment but verify
    $exists = $this->shootoutRepository->attemptExists($matchId, $round, $team, $attemptOrder);
    if ($exists) {
        throw new \InvalidArgumentException('Duplicate attempt order in this round.');
    }

    // Is this sudden death? (round > 5 in standard shootout — verify against docs)
    $isSuddenDeath = ($round > 5); // use docs value

    $this->shootoutRepository->insert([
        'match_id'        => $matchId,
        'team'            => $team,
        'player_id'       => $playerId,
        'round_number'    => $round,
        'attempt_order'   => $attemptOrder,
        'outcome'         => $outcome,
        'is_sudden_death' => $isSuddenDeath ? 1 : 0,
        'created_at'      => date('Y-m-d H:i:s'),
    ]);

    // Check if shootout should automatically end
    $this->checkAutomaticEnding($matchId);
}
```

## Shootout score calculation

```php
public function getShootoutScore(int $matchId): array {
    return $this->shootoutRepository->calculateScore($matchId);
    // Returns: ['own' => int, 'opponent' => int]
}
```

**This score is NEVER added to the normal match score.** Display separately.

## Automatic ending detection

After each attempt, check:
```php
private function checkAutomaticEnding(int $matchId): void {
    $score = $this->getShootoutScore($matchId);
    $attempts = $this->shootoutRepository->getAttempts($matchId);
    // Per the critical behavior spec, determine if a winner is mathematically decided
    // If yes: $this->matchRepository->setShootoutAutoEnd($matchId, true)
    // The UI will then prompt for confirmation before actually finishing
}
```

## `POST /matches/{id}/shootout/finish`

`ShootoutService::finish(int $matchId, bool $confirmed): void`
- Validation: shootout started; match not already finished; confirmation provided
- Set match state = `finished`
- Set `finished_at = NOW()`

Confirmation required (critical action).

## Shootout display

`GET /matches/{id}/shootout` shows:
- Round-by-round attempt table: each row shows round, team, taker name (if own), outcome (✓ or ✗)
- Running shootout score (own X - Y opponent)
- Normal match score shown separately

In the match summary, show both scores:
```html
<div class="final-score">
    3 - 2
    <span class="penalties">(After penalties: 4 - 3)</span>
</div>
```

# Out of Scope
- Corrections to shootout attempts (v0.8.0)
- Public livestream of shootout (v0.8.0)

# Architectural Rules
- Shootout score is NEVER added to `match_events` or to the normal score
- Shootout is stored in a SEPARATE table (`match_shootout_attempts`)
- Automatic ending prompts confirmation — does not auto-finish
- Sent-off player eligibility checked via `getEligiblePenaltyTakers()`

# Acceptance Criteria
- Shootout can be started after regular time or extra time ends
- Attempts stored with round, order, taker (if own team), team, outcome
- Shootout goals do not alter normal match score
- Sent-off player cannot be selected as taker (server-side rejection)
- Duplicate attempt order within same round rejected
- Automatic ending detection presents confirmation before finishing
- Manual finish with confirmation transitions match to `finished`
- Shootout score displayed separately from normal score

# Verification
- PHP syntax check
- Start shootout; register 5 rounds for both teams; verify normal score unchanged
- Verify shootout score calculated separately
- Attempt to add sent-off player as taker → verify rejection
- Finish shootout with confirmation → verify state=`finished`

# Handoff Note
`05-authorization-and-transitions.md` performs the cross-cutting review for all v0.7.0 routes.
