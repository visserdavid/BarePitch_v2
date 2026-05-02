# Issue Card End to End

# Purpose
Implement yellow card and red card event registration. Yellow cards record a disciplinary event without removing the player. Red cards remove the player from the field, stop their playing time, and set the permanent `sent_off` flag.

# Required Context
See `01-shared-context.md`. `PlayingTimeService` from `03-track-playing-time-end-to-end.md`. `match_lineup.sent_off` field (verify against schema docs).

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — yellow and red card rules
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_events.event_type` values for cards

# Scope

## Routes
```php
$router->post('/matches/{id}/events/yellow-card', [\App\Http\Controllers\CardController::class, 'registerYellowCard']);
$router->post('/matches/{id}/events/red-card',    [\App\Http\Controllers\CardController::class, 'registerRedCard']);
```

Both require CSRF and `LiveMatchPolicy::canRegisterEvent()` (coach/admin).

## Yellow Card

`CardService::registerYellowCard(int $matchId, array $data): void`

```php
public function registerYellowCard(int $matchId, array $data): void {
    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] !== 'active') throw new InvalidStateException('Match is not active.');

    $playerId = (int)$data['player_id'];
    $minute   = (int)$data['minute'];

    // Validate player is active field player for this match
    if (!$this->lineupRepository->isActiveFieldPlayer($matchId, $playerId)) {
        throw new \InvalidArgumentException('Player is not an active field player.');
    }

    // Insert yellow_card event (single insert, no transaction needed)
    $this->eventRepository->insert([
        'match_id'   => $matchId,
        'event_type' => 'yellow_card', // use exact value from docs
        'player_id'  => $playerId,
        'minute'     => $minute,
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    // Yellow card does NOT remove player from field
    // Yellow card does NOT reduce field count
    // Two yellows = red? Only implement if explicitly documented in -05
}
```

## Red Card

`CardService::registerRedCard(int $matchId, array $data, bool $confirmed): void`

**Confirmation required**: the POST body must include `confirm=1` because this is a lineup-changing action.

```php
public function registerRedCard(int $matchId, array $data, bool $confirmed): void {
    if (!$confirmed) throw new \InvalidArgumentException('Red card confirmation required.');

    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] !== 'active') throw new InvalidStateException('Match is not active.');

    $playerId = (int)$data['player_id'];
    $minute   = (int)$data['minute'];

    // Validate player is active field player
    if (!$this->lineupRepository->isActiveFieldPlayer($matchId, $playerId)) {
        throw new \InvalidArgumentException('Player is not an active field player.');
    }

    $db = Database::connection();
    $db->beginTransaction();
    try {
        // 1. Insert red_card event
        $this->eventRepository->insert([
            'match_id'   => $matchId,
            'event_type' => 'red_card', // exact value from docs
            'player_id'  => $playerId,
            'minute'     => $minute,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 2. Set sent_off = 1 in match_lineup
        $this->lineupRepository->setSentOff($matchId, $playerId);

        // 3. Remove player from field (mark as not active field player)
        //    This could mean: set is_starter = 0, set position_id = null, set x_coord/y_coord = null
        //    Use the schema definition from docs for how "removed from field" is represented
        $this->lineupRepository->removeFromField($matchId, $playerId);

        // 4. Stop playing time
        $this->playingTimeService->stopPlayerTime($matchId, $playerId, $minute);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
```

## Timeline display

The live match timeline must show cards with appropriate visual treatment:
- Yellow card: yellow badge/icon, player name, minute
- Red card: red badge/icon, player name, minute

Cards must be distinguishable from goals and substitutions in the timeline.

## Red card UI (update live screen)

Add a red card button to the live match action controls. It must require deliberate confirmation:

```html
<form action="/matches/<?= e($match['id']) ?>/events/red-card" method="POST"
      onsubmit="return confirm('Issue a red card? This will remove the player from the field.')">
    <input type="hidden" name="_csrf" value="<?= e(\App\Http\Helpers\CsrfHelper::getToken()) ?>">
    <input type="hidden" name="confirm" value="1">
    <select name="player_id">
        <?php foreach ($fieldPlayers as $p): ?>
        <option value="<?= e($p['player_id']) ?>"><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="number" name="minute" min="1" max="200" placeholder="Minute">
    <button type="submit" class="btn-danger">Red Card</button>
</form>
```

# Out of Scope
- Two yellow cards = red card (only if explicitly in docs)
- Penalty restrictions for red-carded players (prompt 05)

# Architectural Rules
- Red card is transactional (event insert + sent_off flag + remove from field + stop playing time)
- `sent_off` flag is permanent within the match once set
- Confirmation required for red card (both client-side `confirm()` and server-side `confirm=1` check)

# Acceptance Criteria
- Yellow card inserted in timeline; player remains on field
- Red card inserted in timeline; `sent_off = 1` in `match_lineup`; player removed from field
- Playing time stops for red-carded player (exit minute recorded)
- Attempting to red-card a player not on field: rejected
- Red card requires `confirm=1` in POST body; missing returns validation error
- Transaction: if event insert succeeds but `sent_off` update fails, rollback

# Verification
- PHP syntax check
- Register yellow card → verify player still in field player list; event in timeline
- Register red card → verify player no longer in field player list; `sent_off=1` in DB; playing time recorded
- Attempt red card without confirm=1 → verify rejection

# Handoff Note
`05-enforce-red-card-restrictions-end-to-end.md` enforces all downstream restrictions from the `sent_off` flag.
