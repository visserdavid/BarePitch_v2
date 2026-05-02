# Substitute Player End to End

# Purpose
Implement the substitution flow: selecting an outgoing active field player and an eligible incoming bench player, updating the lineup, and recording the substitution event.

# Required Context
See `01-shared-context.md`. Live match core from v0.5.0. `match_lineup` tracks current field/bench state.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — substitution eligibility rules
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — match_lineup schema, match_events.event_type for substitutions

# Scope

## Routes
```php
$router->get('/matches/{id}/substitution',       [\App\Http\Controllers\SubstitutionController::class, 'show']);
$router->post('/matches/{id}/events/substitution', [\App\Http\Controllers\SubstitutionController::class, 'store']);
```

## `GET /matches/{id}/substitution` (data endpoint)

Returns JSON or renders a modal/form with:
- Active field players (outgoing candidates): players in `match_lineup` where `is_starter=1` AND `sent_off=0`
- Eligible bench players (incoming candidates): players in `match_lineup` where `is_starter=0` AND `sent_off=0`

Authorization: coach/admin only.

## `SubstitutionRequest`

Validate:
- `outgoing_player_id`: required, integer
- `incoming_player_id`: required, integer
- `minute`: required, positive integer

## `SubstitutionService::perform(int $matchId, array $data): void`

Full transaction:

```php
public function perform(int $matchId, array $data): void {
    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] !== 'active') {
        throw new InvalidStateException('Match is not active.');
    }

    $outgoingId = (int)$data['outgoing_player_id'];
    $incomingId = (int)$data['incoming_player_id'];
    $minute     = (int)$data['minute'];

    // Validate outgoing player is active field player (not bench, not sent off)
    $outgoing = $this->lineupRepository->getFieldPlayer($matchId, $outgoingId);
    if (!$outgoing) {
        throw new \InvalidArgumentException('Outgoing player is not an active field player.');
    }

    // Validate incoming player is on bench (not on field, not sent off)
    $incoming = $this->lineupRepository->getBenchPlayer($matchId, $incomingId);
    if (!$incoming) {
        throw new \InvalidArgumentException('Incoming player is not an eligible bench player.');
    }
    if ($incoming['sent_off']) {
        throw new \InvalidArgumentException('Sent-off player cannot be substituted on.');
    }

    // Validate incoming is not already on field (duplicate prevention)
    if ($this->lineupRepository->isActiveFieldPlayer($matchId, $incomingId)) {
        throw new \InvalidArgumentException('Incoming player is already on the field.');
    }

    $db = Database::connection();
    $db->beginTransaction();
    try {
        // 1. Stop playing time for outgoing player
        $this->playingTimeService->stopPlayerTime($matchId, $outgoingId, $minute);

        // 2. Move outgoing from field to bench (set is_starter=0, clear position/coordinates)
        $this->lineupRepository->moveToB($matchId, $outgoingId);

        // 3. Move incoming from bench to field (set is_starter=1, assign outgoing's position and coordinates)
        $this->lineupRepository->moveToField($matchId, $incomingId, $outgoing['position_id'], $outgoing['x_coord'], $outgoing['y_coord']);

        // 4. Start playing time for incoming player
        $this->playingTimeService->startPlayerTime($matchId, $incomingId, $minute);

        // 5. Insert substitution event
        $this->eventRepository->insert([
            'match_id'           => $matchId,
            'event_type'         => 'substitution', // use exact value from docs
            'player_id'          => $outgoingId,    // outgoing
            'incoming_player_id' => $incomingId,    // if schema supports this field; otherwise use two events
            'minute'             => $minute,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
```

**Note on schema**: check `docs/-01` for how substitutions are stored in `match_events`. If the schema uses a single event with both outgoing and incoming player IDs, use that. If it uses two separate events (substitution_off, substitution_on), use those. Do not invent a new structure.

## Substitution UI (update `app/Views/matches/live.php`)

Add a substitution button to the `#live-actions` section (visible only when match is active and coach/admin):

```html
<button type="button" onclick="openSubstitutionModal()">Substitution</button>
```

The substitution modal shows two dropdowns:
1. Outgoing player (from field players list)
2. Incoming player (from bench list, filtered to exclude sent-off)

Submit as a `<form method="POST">` with CSRF.

JavaScript may dynamically update the incoming list when outgoing is selected (to prevent selecting the same player), but the server validates regardless.

# Out of Scope
- Playing time statistics display (v0.9.0)
- Red-card restrictions on incoming player (prompt 05 of this bundle handles the sent_off check — already included above)

# Architectural Rules
- Full substitution (5 steps) is one transaction
- `SubstitutionService` orchestrates all steps
- `PlayingTimeService` (from next prompt) handles time recording

# Acceptance Criteria
- Substitution moves outgoing to bench, incoming to field
- Outgoing player's position is assigned to incoming player
- Substitution appears in timeline
- Sent-off player cannot be selected as incoming (server-side rejection)
- Player not on field cannot be substituted off (server-side rejection)
- Player already on field cannot be substituted on (server-side rejection)
- Transaction: if any step fails, no partial change in DB

# Verification
- PHP syntax check
- Perform a substitution; verify DB lineup state (outgoing on bench, incoming on field)
- Attempt to substitute a sent-off player → verify server-side rejection

# Handoff Note
`03-track-playing-time-end-to-end.md` implements `PlayingTimeService` used in this prompt.
