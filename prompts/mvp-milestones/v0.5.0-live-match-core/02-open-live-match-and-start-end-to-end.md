# Open Live Match and Start End to End

# Purpose
Implement the live match screen — the read-only view components: score header, current period, lineup display, and timeline. No write actions yet; this establishes the display structure that action prompts build upon.

# Required Context
See `01-shared-context.md`. Match must be in `active` state. Schema includes `match_events`, `match_periods`, `match_lineup`.

# Required Documentation
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — live match screen UI spec
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — period types and display

# Scope

## Route
```php
$router->get('/matches/{id}/live', [\App\Http\Controllers\LiveMatchController::class, 'show']);
```

## `LiveMatchController::show(int $id)`
1. Load match — scoped to active team; 404 if not found
2. If match state is not `active`: redirect to `GET /matches/{id}` with info message
3. Authorization: `LiveMatchPolicy::canView()` — all roles can view; coach/admin can see action controls
4. Load:
   - Current score: `ScoreService::calculate($id)` → `['home' => int, 'away' => int]`
   - Current period: latest `match_periods` row where `ended_at IS NULL`
   - Field players: `match_lineup` rows where `is_starter = 1` and player not on bench for this match
   - Bench players: `match_lineup` rows where `is_starter = 0`
   - Timeline: `match_events` ordered by `minute ASC`, then `id ASC`
5. Pass all data to view

## `ScoreService::calculate(int $matchId): array`

```php
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
    $row = $stmt->fetch();
    return [
        'home' => (int)($row['home_score'] ?? 0),
        'away' => (int)($row['away_score'] ?? 0),
    ];
}
```

Use the exact `event_type` values from the schema docs — do not invent new type strings.

## `app/Views/matches/live.php`

Mobile-first layout sections:

**Score header** (sticky or prominent top section):
```html
<div class="score-header">
    <span class="team-name">Demo FC</span>
    <span class="score"><?= e($score['home']) ?> - <?= e($score['away']) ?></span>
    <span class="opponent"><?= e($match['opponent']) ?></span>
</div>
```

**Period indicator**: current period name (e.g., "First Half", "Second Half") and a client-side elapsed timer:
```html
<div class="period-display">
    <span class="period-name"><?= e($currentPeriod['type_label']) ?></span>
    <span class="match-timer" data-started-at="<?= e($currentPeriod['started_at']) ?>">0:00</span>
</div>
```
JavaScript timer: `setInterval` to count up from `started_at` timestamp to current time.

**Current lineup**:
- Grid layout showing field players by position
- Bench list below

**Timeline**:
- Reverse chronological or chronological per UI spec
- Each event: minute, event type icon/label, player name (for own-team events)

**Action controls section**: placeholder `<div id="live-actions">` where later prompts insert goal/period controls. Show an empty section now; no buttons yet.

# Out of Scope
- Goal registration form (prompt 03)
- Period transition buttons (prompt 04)
- Finish action (prompt 05)

# Architectural Rules
- Score computed by `ScoreService::calculate()` — never from a stored column
- All view output uses `e()` / `htmlspecialchars()`
- If match state is not `active`, redirect rather than showing an empty live screen

# Acceptance Criteria
- `GET /matches/{id}/live` loads for an active match without errors
- Score header shows `0 - 0` initially (no events yet)
- Period indicator shows current period name
- Lineup section shows starters and bench
- Timeline is empty but renders without error
- Redirects to match detail if match is not in `active` state

# Verification
- PHP syntax check
- Manually start a prepared match (via the start route from v0.1.0/prompt 08 or implemented in next prompt)
- Visit live screen — confirm all sections render
- Confirm score shows 0-0

# Handoff Note
`03-register-goal-end-to-end.md` adds the goal registration modal and POST route to the live screen.
