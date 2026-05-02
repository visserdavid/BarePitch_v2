# Manage Periods End to End

# Purpose
Implement match start and the four period transition routes: end first half, start second half, end second half. All transitions must follow the documented period state machine.

# Required Context
See `01-shared-context.md`. Schema includes `match_periods`. Critical behavior spec defines the valid period state machine.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — period state machine and transition rules
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — match state transitions

# Scope

## Routes
```php
$router->post('/matches/{id}/start',                [\App\Http\Controllers\LiveMatchController::class, 'start']);
$router->post('/matches/{id}/periods/end-first',    [\App\Http\Controllers\LiveMatchController::class, 'endFirstHalf']);
$router->post('/matches/{id}/periods/start-second', [\App\Http\Controllers\LiveMatchController::class, 'startSecondHalf']);
$router->post('/matches/{id}/periods/end-second',   [\App\Http\Controllers\LiveMatchController::class, 'endSecondHalf']);
```

All require CSRF and `LiveMatchPolicy::canRegisterEvent()` (coach/admin).

## `LiveMatchService::startMatch(int $matchId): void`

```php
public function startMatch(int $matchId): void {
    $match = $this->matchRepository->findById($matchId);

    if ($match['state'] === 'finished') {
        throw new InvalidStateException('This match is already finished.');
    }
    if ($match['state'] === 'active') {
        throw new InvalidStateException('Match is already active.');
    }
    if ($match['state'] !== 'prepared') {
        throw new InvalidStateException('Only prepared matches can be started. Current state: ' . $match['state']);
    }

    $db = Database::connection();
    $db->beginTransaction();
    try {
        // Create first period
        $stmt = $db->prepare("
            INSERT INTO match_periods (match_id, type, started_at)
            VALUES (?, 'first_half', NOW())
        ");
        $stmt->execute([$matchId]);

        // Update match state
        $stmt = $db->prepare("UPDATE matches SET state = 'active' WHERE id = ?");
        $stmt->execute([$matchId]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
```

Use the exact `type` value for first half from the docs (`first_half` or whatever the schema specifies).

## Period transition methods

Each transition follows the same pattern: validate the current period state, then create/update the period record.

### `endFirstHalf(int $matchId): void`
- Validate: match is `active`
- Validate: a `first_half` period exists with `ended_at IS NULL`
- Update: `SET ended_at = NOW()` for the first_half period

### `startSecondHalf(int $matchId): void`
- Validate: match is `active`
- Validate: first_half period exists with `ended_at IS NOT NULL`
- Validate: no second_half period exists yet
- Insert: new period with `type = 'second_half'`, `started_at = NOW()`

### `endSecondHalf(int $matchId): void`
- Validate: match is `active`
- Validate: second_half period exists with `ended_at IS NULL`
- Update: `SET ended_at = NOW()` for the second_half period

**Period type values**: use the exact string values from `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` for `match_periods.type`. Do not invent values like `"half_1"` if the docs say `"first_half"`.

## Current period helper

```php
public function getCurrentPeriod(int $matchId): ?array {
    $stmt = $db->prepare("
        SELECT * FROM match_periods
        WHERE match_id = ? AND ended_at IS NULL
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch() ?: null;
}
```

Used by the live screen to display the current period name and timer start time.

## Update live screen action controls

In `app/Views/matches/live.php`, show contextual period buttons based on current state:

```php
if ($currentPeriod === null && $match['state'] === 'active') {
    // Should not happen in normal flow
} elseif ($currentPeriod['type'] === 'first_half') {
    // Show "End First Half" button
} elseif ($currentPeriod === null /* first half ended */ && !$secondHalfStarted) {
    // Show "Start Second Half" button
} elseif ($currentPeriod['type'] === 'second_half') {
    // Show "End Second Half" button (and "Finish Match" becomes available after)
}
```

Each period transition button is a simple `<form method="POST">` with CSRF token and a confirmation step (JavaScript confirm or a two-step button for critical transitions per UI spec).

# Out of Scope
- Extra time periods (v0.7.0)
- Finish action (prompt 05)

# Architectural Rules
- Start match: must be transactional (state change + period creation in one transaction)
- Period transitions: not necessarily transactional (single-row updates) but must validate state before writing
- Controller never writes to `match_periods` directly — all period logic in `LiveMatchService`

# Acceptance Criteria
- `POST /matches/{id}/start` on `planned` match: rejected with clear error, state unchanged
- `POST /matches/{id}/start` on `prepared` match: state becomes `active`, first period created
- End first half: period `ended_at` set; second half button appears
- Start second half: second period created; cannot start if first half still active
- End second half: period `ended_at` set
- Out-of-order transition (e.g., end second half before first half ends): rejected
- All routes: 403 for trainer/team_manager; 403 for missing CSRF

# Verification
- PHP syntax check
- Walk through all four period transitions manually
- Attempt start on a planned match → verify rejection and state unchanged
- Verify `match_periods` rows after each transition

# Handoff Note
`05-finish-match-and-summary-end-to-end.md` adds the finish action and the finished match summary view.
